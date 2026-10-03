<?php

namespace Tests\Unit\Console\Commands;

use Illuminate\Foundation\Console\QueuedCommand;
use Illuminate\Support\Facades\Queue;
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Data\CompanyData;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Enums\DteEnvironment;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Environment\EnvironmentResolver;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Models\SiiDtePayload;
use Laragear\Rut\Facades\Generator;
use Laragear\Rut\Rut;
use Override;
use Tests\DatabaseTestCase;

class PackReadyDtesCommandTest extends DatabaseTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        ConfigurationManager::setCompany(fn() => CompanyData::make(
            IssuerData::make(
                '76.123.456-0',
                'Test Company',
                'Software',
                ['620100'],
                'Test Address 123',
                'Santiago',
                '2025-01-01',
                76000,
                'Santiago',
                '+56212345678',
                'test@example.com',
                'Casa Matriz',
            ),
            '76.123.456-0',
        ));
    }

    /*
     |--------------------------------------------------------------------------
     | Happy paths
     |--------------------------------------------------------------------------
     */

    public function test_packs_signed_dtes_with_dynamic_sender_and_issuer_resolution(): void
    {
        Queue::fake();

        $this->config([
            'dte.environment' => DteEnvironment::Local->value,
            'dte.envelopes.max.documents' => 1,
        ]);

        $this->app->make(EnvironmentResolver::class)->flush();

        ConfigurationManager::resolveIssuerUsing(function () {
            return IssuerData::make(
                '76.123.456-0',
                'Dynamic Company',
                'Activity',
                '1234',
                'Addr',
                'Com',
                '2024-05-05',
                12345,
            );
        });
        ConfigurationManager::resolveSenderUsing(function (Rut $issuer) {
            return '12.345.678-5';
        });

        $rut1 = Generator::asCompanies()->makeOne();

        SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'issuer_rut' => $rut1,
            'status' => DteStatus::Outbox,
        ]);

        $this->artisan('dte:pack-ready')->assertSuccessful();

        $envelope = SiiDteEnvelope::first();

        static::assertEquals('123456785', $envelope->sender_rut->formatRaw());
        static::assertEquals('2024-05-05', $envelope->resolution_date->format('Y-m-d'));
        static::assertEquals(12345, $envelope->resolution_number);
    }

    public function test_packs_signed_dtes_by_issuer_rut_and_dispatches_processing(): void
    {
        $queue = Queue::fake();

        $this->config('dte.environment', DteEnvironment::Local->value);
        $this->app->make(EnvironmentResolver::class)->flush();
        $this->config('dte.envelopes.max.documents', 2);
        $this->config('dte.envelopes.max_holding_minutes', 30);
        $this->config('dte.queue.envelope.connection', 'database');
        $this->config('dte.queue.envelope.name', 'dte-queue');

        $rut1 = Generator::asCompanies()->makeOne();
        $rut2 = Generator::asCompanies()->makeOne();

        // 2 items for rut1 -> meets max_documents (2) -> exactly 1 envelope with 2 DTEs
        SiiDte::factory()
            ->count(2)
            ->has(SiiDtePayload::factory(), 'payload')
            ->create([
                'issuer_rut' => $rut1,
                'status' => DteStatus::Outbox,
            ]);

        // 3 items for rut2, older than max_holding_minutes -> will be packed into 2 envelopes (one with 2, one with 1)
        SiiDte::factory()
            ->count(3)
            ->has(SiiDtePayload::factory(), 'payload')
            ->create([
                'issuer_rut' => $rut2,
                'status' => DteStatus::Outbox,
                'updated_at' => now()->subMinutes(35),
            ]);

        // Ignored DTEs
        SiiDte::factory()->create([
            'status' => DteStatus::Pending,
        ]);
        SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'status' => DteStatus::Outbox,
            'sii_dte_envelope_id' => 999, // Will be ignored as it has an envelope
        ]);

        $this->artisan('dte:pack-ready')->assertSuccessful();

        $envelopes = SiiDteEnvelope::all();

        static::assertCount(3, $envelopes); // 1 for rut1, 2 for rut2

        $rut1Envelopes = $envelopes->filter(fn($e) => (string) $e->issuer_rut === (string) $rut1);

        static::assertCount(1, $rut1Envelopes);

        $firstEnvelope = $rut1Envelopes->first();

        static::assertEquals(2, $firstEnvelope->dtes()->count());
        static::assertEquals(
            Rut::parse('76.123.456-0')->formatRaw(),
            $firstEnvelope->sender_rut->formatRaw(),
        );
        static::assertEquals('2025-01-01', $firstEnvelope->resolution_date->format('Y-m-d'));
        static::assertEquals(76000, $firstEnvelope->resolution_number);

        $rut2Envelopes = $envelopes->filter(fn($e) => (string) $e->issuer_rut === (string) $rut2);

        static::assertCount(2, $rut2Envelopes);
        static::assertEquals(2, $rut2Envelopes->first()->dtes()->count());
        static::assertEquals(1, $rut2Envelopes->last()->dtes()->count());

        $queue->assertPushed(QueuedCommand::class, 3);
    }

    public function test_does_nothing_when_no_signed_dtes(): void
    {
        SiiDte::factory()->create([
            'status' => DteStatus::Pending,
        ]);

        $this
            ->artisan('dte:pack-ready')
            ->expectsOutput('No signed DTEs available to pack.')
            ->assertSuccessful();

        $this->assertDatabaseCount('sii_dte_envelopes', 0);
    }

    public function test_does_nothing_when_dtes_do_not_meet_thresholds(): void
    {
        $this->config('dte.environment', DteEnvironment::Local->value);
        $this->app->make(EnvironmentResolver::class)->flush();
        $this->config('dte.envelopes.max.documents', 5);
        $this->config('dte.envelopes.max_holding_minutes', 30);

        // Only 1 DTE, and it's fresh (not older than max_holding_minutes)
        SiiDte::factory()->create([
            'issuer_rut' => Generator::asCompanies()->makeOne(),
            'status' => DteStatus::Outbox,
            'updated_at' => now()->subMinutes(10), // Less than 30 minutes
        ]);

        $this
            ->artisan('dte:pack-ready')
            ->expectsOutput('No signed DTEs available to pack.')
            ->assertSuccessful();

        $this->assertDatabaseCount('sii_dte_envelopes', 0);
    }
}
