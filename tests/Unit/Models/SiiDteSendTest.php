<?php

namespace Tests\Unit\Models;

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
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Models\SiiDteEnvelopePayload;
use Laragear\Dte\Models\SiiDtePayload;
use LogicException;
use Mockery\MockInterface;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\DatabaseTestCase;

class SiiDteSendTest extends DatabaseTestCase
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

    public function test_sends_compiled_dte_in_exclusive_envelope_queued(): void
    {
        $queue = Queue::fake();

        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'status' => DteStatus::Outbox,
        ]);

        $envelope = $dte->send();

        static::assertInstanceOf(SiiDteEnvelope::class, $envelope);
        static::assertSame(EnvelopeStatus::Pending, $envelope->status);
        static::assertSame('dte', $envelope->type);
        static::assertSame(1, $envelope->dtes()->count());
        static::assertTrue($envelope->dtes()->first()->is($dte));
        static::assertTrue($dte->refresh()->envelope->is($envelope));
        static::assertSame(DteStatus::Packed, $dte->refresh()->status);

        $queue->assertPushed(QueuedCommand::class, 1);
    }

    public function test_sends_receipt_dte_in_boleta_envelope(): void
    {
        Queue::fake();

        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'document_type' => DteType::Receipt,
            'status' => DteStatus::Outbox,
        ]);

        $envelope = $dte->send();

        static::assertSame('boleta', $envelope->type);
    }

    public function test_sends_compiled_dte_sync(): void
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

        $envelope = $dte->send(true);

        static::assertSame(EnvelopeStatus::Uploaded, $envelope->status);
        static::assertSame("fake-track-id-{$envelope->getKey()}", $envelope->track_id);
        static::assertSame(DteStatus::Sent, $dte->refresh()->status);
    }

    public function test_send_sync_uses_send_with_true(): void
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

        $envelope = $dte->sendSync();

        static::assertSame(EnvelopeStatus::Uploaded, $envelope->status);
        static::assertSame(DteStatus::Sent, $dte->refresh()->status);
    }

    public function test_sends_sync_with_truthy_closure(): void
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

        $seen = [];

        $envelope = $dte->send(function (SiiDte $sent, SiiDteEnvelope $created) use ($dte, &$seen): bool {
            $seen = [$sent->is($dte), $created instanceof SiiDteEnvelope];

            return true;
        });

        static::assertSame([true, true], $seen);
        static::assertSame(EnvelopeStatus::Uploaded, $envelope->status);
    }

    public function test_compiles_pending_dte_before_sending(): void
    {
        Queue::fake();

        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'status' => DteStatus::Pending,
        ]);

        $this->mock(Compile::class, function (MockInterface $mock) use ($dte): void {
            $mock
                ->expects('forDte')
                ->withArgs(fn(SiiDte $sent): bool => $sent->is($dte))
                ->andReturnUsing(function (SiiDte $sent): SiiDte {
                    $sent->forceFill(['status' => DteStatus::Outbox])->save();

                    return $sent;
                });
        });

        $envelope = $dte->send();

        static::assertSame(EnvelopeStatus::Pending, $envelope->status);
        static::assertTrue($dte->refresh()->envelope->is($envelope));
    }

    public function test_queues_envelope_on_configured_connection_and_queue(): void
    {
        $queue = Queue::fake();

        $this->config('dte.queue.envelope.connection', 'database');
        $this->config('dte.queue.envelope.name', 'dte-queue');

        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'status' => DteStatus::Outbox,
        ]);

        $dte->send();

        $queue->assertPushed(QueuedCommand::class, function (QueuedCommand $job): bool {
            return $job->connection === 'database' && $job->queue === 'dte-queue';
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Sad paths
    |--------------------------------------------------------------------------
    */

    public function test_throws_when_dte_already_has_envelope(): void
    {
        $dte = SiiDte::factory()->create([
            'status' => DteStatus::Outbox,
            'sii_dte_envelope_id' => 999,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs("The DTE [{$dte->getKey()}] already belongs to envelope [999].");

        $dte->send();
    }

    public function test_throws_when_dte_is_draft(): void
    {
        $dte = SiiDte::factory()->create(['status' => DteStatus::Draft]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs("The DTE [{$dte->getKey()}] is a draft and cannot be sent. Build it with build() first.");

        $dte->send();
    }

    public static function providesTerminalStatuses(): array
    {
        return [
            DteStatus::Accepted->value => [DteStatus::Accepted],
            DteStatus::Rejected->value => [DteStatus::Rejected],
            DteStatus::Annulled->value => [DteStatus::Annulled],
        ];
    }

    #[DataProvider('providesTerminalStatuses')]
    public function test_throws_when_dte_in_terminal_state(DteStatus $status): void
    {
        $dte = SiiDte::factory()->create(['status' => $status]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains("The DTE [{$dte->getKey()}] is in a terminal state [{$status->value}]");

        $dte->send();
    }

    public function test_rejected_message_hints_replicate_for_retry(): void
    {
        $dte = SiiDte::factory()->create(['status' => DteStatus::Rejected]);

        try {
            $dte->send();

            static::fail('Expected LogicException was not thrown.');
        } catch (LogicException $e) {
            static::assertStringContainsString('replicateForRetry()', $e->getMessage());
        }
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
        $this->expectExceptionMessageIs("The DTE [{$dte->getKey()}] could not be compiled before sending.");

        $dte->send();
    }

    public function test_does_not_create_envelope_when_compile_fails(): void
    {
        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'status' => DteStatus::Pending,
        ]);

        $this->mock(Compile::class, function (MockInterface $mock): void {
            $mock->expects('forDte')->andThrow(new LogicException('Only pending or building DTE documents may be compiled.'));
        });

        try {
            $dte->send();

            static::fail('Expected LogicException was not thrown.');
        } catch (LogicException) {
            $this->assertDatabaseCount('sii_dte_envelopes', 0);
        }
    }
}
