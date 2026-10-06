<?php

namespace Tests\Unit\Models;

use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDtePayload;
use Mockery;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\DatabaseTestCase;

class SiiDteReplicateForRetryTest extends DatabaseTestCase
{
    public function test_replicates_for_retry(): void
    {
        $original = SiiDte::factory()->create([
            'status' => DteStatus::Rejected,
            'pack_retries' => 3,
            'rejected_at' => now(),
            'folio' => 123,
        ]);

        $payload = new SiiDtePayload(['header_issuer' => ['some' => 'data']]);
        $original->payload()->save($payload);

        $clone = $original->replicateForRetry();
        $clone->load('payload');

        static::assertNotEquals($original->id, $clone->id);
        static::assertEquals(DteStatus::Draft, $clone->status);
        static::assertNull($clone->folio);
        static::assertNull($clone->sii_caf_id);
        static::assertNull($clone->sii_dte_envelope_id);
        static::assertNull($clone->rejected_at);
        static::assertEquals(0, $clone->pack_retries);
        static::assertEquals(['some' => 'data'], $clone->payload->header_issuer->toArray());
    }

    public function test_replication_failure_logs_and_rolls_back(): void
    {
        $original = SiiDte::factory()->create(['status' => DteStatus::Rejected]);

        $this->mock(LoggerInterface::class)
            ->shouldReceive('error')
            ->once()
            ->with(
                'DTE replication failed, rolling back the cloned draft.',
                Mockery::on(static fn(array $context): bool => $context['flow'] === 'dte-replication'
                    && $context['original_id'] === $original->getKey()
                    && $context['exception'] === RuntimeException::class),
            );

        $payload = $this->mock(SiiDtePayload::class);
        $payload->shouldReceive('replicate')->once()->andThrow(RuntimeException::class, 'Payload failure.');
        $original->setRelation('payload', $payload);

        try {
            $original->replicateForRetry();

            static::fail('Expected replication to fail.');
        } catch (RuntimeException $e) {
            static::assertSame('Payload failure.', $e->getMessage());
        }

        static::assertDatabaseCount('sii_dtes', 1);
    }
}
