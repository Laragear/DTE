<?php

namespace Tests\Unit\Services;

use Illuminate\Foundation\Console\QueuedCommand;
use Illuminate\Support\Facades\Queue;
use Laragear\Dte\Actions\CompileDte\Compile;
use Laragear\Dte\Actions\CreateEnvelope\CreateEnvelope;
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Data\CompanyData;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\EnvelopeStatus;
use Laragear\Dte\Facades\SiiEnvelope;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Models\SiiDteEnvelopePayload;
use Laragear\Dte\Models\SiiDtePayload;
use Laragear\Dte\Services\Exceptions\PackClaimException;
use Laragear\Dte\Services\PackDtesService;
use Laragear\Rut\Facades\Generator;
use LogicException;
use Mockery;
use Mockery\MockInterface;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\DatabaseTestCase;

class PackManualTest extends DatabaseTestCase
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

    public function test_packs_given_ids_queued(): void
    {
        $queue = Queue::fake();

        $dtes = SiiDte::factory()
            ->count(2)
            ->has(SiiDtePayload::factory(), 'payload')
            ->create([
                'issuer_rut' => Generator::asCompanies()->makeOne(),
                'status' => DteStatus::Outbox,
            ]);

        $envelopes = SiiEnvelope::packManual($dtes->modelKeys());

        static::assertCount(1, $envelopes);
        static::assertInstanceOf(SiiDteEnvelope::class, $envelopes->first());
        static::assertSame(EnvelopeStatus::Pending, $envelopes->first()->status);
        static::assertEquals(2, $envelopes->first()->dtes()->count());
        static::assertSame(DteStatus::Packed, $dtes->first()->refresh()->status);

        $queue->assertPushed(QueuedCommand::class, 1);
    }

    public function test_accepts_models_collection_and_query_builder(): void
    {
        Queue::fake();

        $dtes = SiiDte::factory()
            ->count(2)
            ->has(SiiDtePayload::factory(), 'payload')
            ->create([
                'issuer_rut' => Generator::asCompanies()->makeOne(),
                'status' => DteStatus::Outbox,
            ]);

        $fromModels = app(PackDtesService::class)->packManual($dtes);

        static::assertCount(1, $fromModels);

        $more = SiiDte::factory()
            ->count(1)
            ->has(SiiDtePayload::factory(), 'payload')
            ->create(['status' => DteStatus::Outbox]);

        $fromQuery = app(PackDtesService::class)->packManual(SiiDte::query()->whereKey($more->modelKeys()));

        static::assertCount(1, $fromQuery);
        static::assertTrue($fromQuery->first()->dtes()->first()->is($more->first()));
    }

    public function test_sync_processes_envelope_inline(): void
    {
        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'status' => DteStatus::Outbox,
        ]);

        $this->mock(CreateEnvelope::class, function (MockInterface $mock): void {
            $mock->expects('forEnvelope')->andReturnUsing(function (SiiDteEnvelope $envelope) {
                $envelope->setRelation('payload', SiiDteEnvelopePayload::factory()->make(['xml' => 'signed-xml']));

                return $envelope;
            });
        });

        $envelopes = SiiEnvelope::packManual([$dte->getKey()], true);

        static::assertCount(1, $envelopes);
        static::assertSame(EnvelopeStatus::Uploaded, $envelopes->first()->status);
        static::assertSame("fake-track-id-{$envelopes->first()->getKey()}", $envelopes->first()->track_id);
        static::assertSame(DteStatus::Sent, $dte->refresh()->status);
    }

    public function test_splits_by_issuer_and_keeps_receipts_apart(): void
    {
        Queue::fake();

        $rut = Generator::asCompanies()->makeOne();

        SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'issuer_rut' => $rut,
            'document_type' => DteType::Invoice,
            'status' => DteStatus::Outbox,
        ]);
        SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'issuer_rut' => $rut,
            'document_type' => DteType::CreditNote,
            'status' => DteStatus::Outbox,
        ]);
        SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'issuer_rut' => $rut,
            'document_type' => DteType::Receipt,
            'status' => DteStatus::Outbox,
        ]);
        $other = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'document_type' => DteType::Invoice,
            'status' => DteStatus::Outbox,
        ]);

        $envelopes = SiiEnvelope::packManual(SiiDte::query());

        static::assertCount(3, $envelopes);

        $mixed = $envelopes->first(fn (SiiDteEnvelope $envelope) => $envelope->dtes()->count() === 2);

        static::assertNotNull($mixed);
        static::assertSame('dte', $mixed->type);
        static::assertTrue($mixed->dtes->pluck('document_type')->contains(DteType::Invoice));
        static::assertTrue($mixed->dtes->pluck('document_type')->contains(DteType::CreditNote));

        static::assertSame($other->issuer_rut->formatRaw(), $envelopes->last()->issuer_rut->formatRaw());
    }

    public function test_respects_chunk_limits(): void
    {
        Queue::fake();

        $this->config('dte.envelopes.max.documents', 2);

        $dtes = SiiDte::factory()
            ->count(3)
            ->has(SiiDtePayload::factory(), 'payload')
            ->create([
                'issuer_rut' => Generator::asCompanies()->makeOne(),
                'document_type' => DteType::Invoice,
                'status' => DteStatus::Outbox,
            ]);

        $envelopes = SiiEnvelope::packManual($dtes->modelKeys());

        static::assertCount(2, $envelopes);
        static::assertSame(2, $envelopes->first()->dtes()->count());
        static::assertSame(1, $envelopes->last()->dtes()->count());
    }

    public function test_returns_empty_collection_for_empty_input(): void
    {
        static::assertTrue(SiiEnvelope::packManual([])->isEmpty());
    }

    public function test_throws_for_missing_ids(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('do not exist');

        SiiEnvelope::packManual([999999]);
    }

    public function test_compiles_draft_dte_before_packing(): void
    {
        Queue::fake();

        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'issuer_rut' => Generator::asCompanies()->makeOne(),
            'status' => DteStatus::Draft,
        ]);

        $this->mock(Compile::class, function (MockInterface $mock) use ($dte): void {
            $mock
                ->expects('forDte')
                ->withArgs(fn (SiiDte $sent): bool => $sent->is($dte))
                ->andReturnUsing(function (SiiDte $sent): SiiDte {
                    $sent->forceFill(['status' => DteStatus::Outbox])->save();

                    return $sent;
                });
        });

        $envelopes = SiiEnvelope::packManual([$dte->getKey()]);

        static::assertCount(1, $envelopes);
        static::assertSame(DteStatus::Packed, $dte->refresh()->status);
    }

    public function test_compiles_pending_dte_before_packing(): void
    {
        Queue::fake();

        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'issuer_rut' => Generator::asCompanies()->makeOne(),
            'status' => DteStatus::Pending,
        ]);

        $this->mock(Compile::class, function (MockInterface $mock) use ($dte): void {
            $mock
                ->expects('forDte')
                ->withArgs(fn (SiiDte $sent): bool => $sent->is($dte))
                ->andReturnUsing(function (SiiDte $sent): SiiDte {
                    $sent->forceFill(['status' => DteStatus::Outbox])->save();

                    return $sent;
                });
        });

        $envelopes = SiiEnvelope::packManual([$dte->getKey()]);

        static::assertCount(1, $envelopes);
        static::assertSame(DteStatus::Packed, $dte->refresh()->status);
    }

    public function test_throws_when_compilation_does_not_leave_compiled(): void
    {
        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'status' => DteStatus::Pending,
        ]);

        $this->mock(Compile::class, function (MockInterface $mock) use ($dte): void {
            $mock->expects('forDte')->andReturn($dte->refresh());
        });

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('could not be compiled before packing');

        SiiEnvelope::packManual([$dte->getKey()]);

        $this->assertDatabaseCount('sii_dte_envelopes', 0);
    }

    public function test_packs_failed_draft_without_opt_in(): void
    {
        Queue::fake();

        // A DTE that failed is a Draft with a stored failure reason. The folio
        // may have been consumed, so failToDraft() released it and a fresh one
        // is allocated on compile. No opt-in is required anymore.
        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'issuer_rut' => Generator::asCompanies()->makeOne(),
            'status' => DteStatus::Draft,
            'folio' => null,
            'sii_caf_id' => null,
            'pack_retries' => 1,
            'failure' => ['stage' => 'upload', 'exception' => null, 'error' => 'boom'],
        ]);

        $this->mock(Compile::class, function (MockInterface $mock) use ($dte): void {
            $mock
                ->expects('forDte')
                ->withArgs(fn (SiiDte $sent): bool => $sent->is($dte))
                ->andReturnUsing(function (SiiDte $sent): SiiDte {
                    $sent->forceFill(['status' => DteStatus::Outbox])->save();

                    return $sent;
                });
        });

        $envelopes = SiiEnvelope::packManual([$dte->getKey()]);

        static::assertCount(1, $envelopes);
        static::assertSame(DteStatus::Packed, $dte->refresh()->status);
    }

    public static function providesUnpackableStatuses(): array
    {
        return [
            DteStatus::Building->value => [DteStatus::Building],
            DteStatus::Signing->value => [DteStatus::Signing],
            DteStatus::RequiresCaf->value => [DteStatus::RequiresCaf],
            DteStatus::Packed->value => [DteStatus::Packed],
            DteStatus::Sent->value => [DteStatus::Sent],
            DteStatus::Accepted->value => [DteStatus::Accepted],
            DteStatus::Rejected->value => [DteStatus::Rejected],
            DteStatus::Annulled->value => [DteStatus::Annulled],
        ];
    }

    #[DataProvider('providesUnpackableStatuses')]
    public function test_throws_for_unpackable_statuses(DteStatus $status): void
    {
        $dte = SiiDte::factory()->create(['status' => $status]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('can be packed manually');

        SiiEnvelope::packManual([$dte->getKey()]);

        $this->assertDatabaseCount('sii_dte_envelopes', 0);
    }

    public function test_throws_for_already_enveloped_dte(): void
    {
        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'status' => DteStatus::Outbox,
            'sii_dte_envelope_id' => 999,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('without an envelope');

        SiiEnvelope::packManual([$dte->getKey()]);
    }

    public function test_rolls_back_envelope_on_partial_claim(): void
    {
        $dtes = SiiDte::factory()
            ->count(2)
            ->has(SiiDtePayload::factory(), 'payload')
            ->create(['status' => DteStatus::Outbox]);

        $dtes->last()->forceFill(['sii_dte_envelope_id' => 999])->save();

        try {
            SiiEnvelope::packManual($dtes->modelKeys());

            static::fail('Expected LogicException was not thrown.');
        } catch (LogicException $e) {
            static::assertStringContainsString('without an envelope', $e->getMessage());
            $this->assertDatabaseCount('sii_dte_envelopes', 0);
        }
    }

    public function test_pack_manual_sync_processes_envelope_inline(): void
    {
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

        $envelopes = app(PackDtesService::class)->packManualSync([$dte->getKey()]);

        static::assertCount(1, $envelopes);
        static::assertSame(EnvelopeStatus::Uploaded, $envelopes->first()->status);
        static::assertSame(DteStatus::Sent, $dte->refresh()->status);
    }

    public function test_throws_when_dte_linked_before_final_check(): void
    {
        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'status' => DteStatus::Pending,
        ]);

        $this->mock(Compile::class, function (MockInterface $mock): void {
            $mock->expects('forDte')->andReturnUsing(function (SiiDte $sent): SiiDte {
                // A concurrent pack links the DTE before the final check runs.
                $sent->forceFill(['status' => DteStatus::Outbox, 'sii_dte_envelope_id' => 999])->save();

                return $sent;
            });
        });

        try {
            SiiEnvelope::packManual([$dte->getKey()]);

            static::fail('Expected LogicException was not thrown.');
        } catch (LogicException $e) {
            static::assertStringContainsString('Only compiled DTEs without an envelope can be packed.',
                $e->getMessage());
            static::assertStringContainsString('in envelope [999]', $e->getMessage());
            $this->assertDatabaseCount('sii_dte_envelopes', 0);
        }
    }

    public function test_pack_failure_logs_and_rolls_back(): void
    {
        Queue::fake();

        $dte = SiiDte::factory()->has(
            SiiDtePayload::factory()->state([]), 'payload'
        )->create(['status' => DteStatus::Outbox]);

        $this->app->make(ConfigurationManager::class)->setIssuerResolver(null);

        $this
            ->mock(LoggerInterface::class)
            ->shouldReceive('error')
            ->once()
            ->with(
                'Batch envelope pack failed, rolling back the batch linkage.',
                Mockery::on(static fn (array $context): bool => $context['flow'] === 'batch-pack'
                    && $context['dte_ids'] === [$dte->getKey()]
                    && $context['exception'] === RuntimeException::class),
            );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('No Issuer resolver has been registered.');

        SiiEnvelope::packManual([$dte->getKey()]);

        $this->assertDatabaseCount('sii_dte_envelopes', 0);
    }

    public function test_pack_claim_race_logs_and_rolls_back(): void
    {
        Queue::fake();

        $dte = SiiDte::factory()->has(
            SiiDtePayload::factory()->state([]), 'payload'
        )->create(['status' => DteStatus::Outbox]);

        ConfigurationManager::setCompany(function () use ($dte) {
            // A concurrent send resolves the DTE after our checks but before
            // the conditional claim runs, so the bulk update links nothing.
            SiiDte::query()->whereKey($dte->getKey())->update(['status' => DteStatus::Accepted]);

            return CompanyData::make(
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
            );
        });

        $this
            ->mock(LoggerInterface::class)
            ->shouldReceive('error')
            ->once()
            ->with(
                'Batch envelope pack failed, rolling back the batch linkage.',
                Mockery::on(static fn (array $context): bool => $context['flow'] === 'batch-pack'
                    && $context['dte_ids'] === [$dte->getKey()]
                    && $context['exception'] === PackClaimException::class),
            );

        try {
            SiiEnvelope::packManual([$dte->getKey()]);

            static::fail('Expected PackClaimException was not thrown.');
        } catch (PackClaimException $e) {
            static::assertStringContainsString('Only [0] of [1] DTEs could be linked', $e->getMessage());
            $this->assertDatabaseCount('sii_dte_envelopes', 0);
        }
    }
}
