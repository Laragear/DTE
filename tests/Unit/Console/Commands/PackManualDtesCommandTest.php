<?php

namespace Tests\Unit\Console\Commands;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laragear\Dte\Actions\CreateEnvelope\CreateEnvelope;
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Data\CompanyData;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Models\SiiDteEnvelopePayload;
use Laragear\Dte\Models\SiiDtePayload;
use Laragear\Rut\Facades\Generator;
use Mockery\MockInterface;
use Override;
use Tests\DatabaseTestCase;

class PackManualDtesCommandTest extends DatabaseTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        ConfigurationManager::setCompany(fn () => CompanyData::make(
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

    public function test_packs_given_ids_and_reports_envelopes(): void
    {
        Queue::fake();

        $dtes = SiiDte::factory()
            ->count(2)
            ->has(SiiDtePayload::factory(), 'payload')
            ->create([
                'issuer_rut' => Generator::asCompanies()->makeOne(),
                'status' => DteStatus::Outbox,
            ]);

        $this
            ->artisan('dte:pack-manual', ['ids' => $dtes->modelKeys()])
            ->assertSuccessful();

        $this->assertDatabaseCount('sii_dte_envelopes', 1);
    }

    public function test_reports_when_nothing_to_pack(): void
    {
        $this
            ->artisan('dte:pack-manual', ['ids' => []])
            ->expectsOutput('No DTEs to pack.')
            ->assertSuccessful();
    }

    public function test_passes_sync_option(): void
    {
        Event::fake();

        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'status' => DteStatus::Outbox,
        ]);

        $this->mock(CreateEnvelope::class, function (MockInterface $mock): void {
            $mock->expects('forEnvelope')->andReturnUsing(function (SiiDteEnvelope $envelope) {
                $envelope->setRelation(
                    'payload',
                    SiiDteEnvelopePayload::factory()->make([
                        'sii_dte_envelope_id' => $envelope->getKey(),
                        'xml' => 'signed-xml',
                    ])
                );

                return $envelope;
            });
        });

        $this
            ->artisan('dte:pack-manual', [
                'ids' => [$dte->getKey()],
                '--sync' => true,
            ])
            ->assertSuccessful();

        $this->assertDatabaseCount('sii_dte_envelopes', 1);
    }
}
