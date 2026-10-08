<?php

namespace Tests\Unit\Models;

use Illuminate\Support\Facades\Event;
use Laragear\Dte\Actions\CompileDte\Compile;
use Laragear\Dte\Actions\CompileDte\Pipes\BuildXml;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Events\DteFailed;
use Laragear\Dte\Models\SiiCaf;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDtePayload;
use LogicException;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\DatabaseTestCase;

class SiiDteFailureRecoveryTest extends DatabaseTestCase
{
    public function test_compile_failure_resets_dte_to_draft_with_reason(): void
    {
        Event::fake([DteFailed::class]);

        $caf = SiiCaf::factory()->create();

        $dte = SiiDte::factory()->has(SiiDtePayload::factory()->state(['xml' => 'partial']), 'payload')->create([
            'status' => DteStatus::Pending,
            'folio' => 1,
            'sii_caf_id' => $caf->getKey(),
        ]);

        $this->mock(BuildXml::class, function (MockInterface $mock): void {
            $mock->expects('handle')->andThrow(new LogicException('Invalid document structure.'));
        });

        try {
            app(Compile::class)->forDte($dte->refresh());
            static::fail('The compile exception was not thrown.');
        } catch (LogicException $e) {
            static::assertSame('Invalid document structure.', $e->getMessage());
        }

        $fresh = $dte->fresh();

        static::assertSame(DteStatus::Draft, $fresh->status);
        static::assertTrue($fresh->hasFailure());
        static::assertSame('compile', $fresh->failure['stage']);
        static::assertSame(LogicException::class, $fresh->failure['exception']);
        static::assertSame('Invalid document structure.', $fresh->failure['error']);
        static::assertNull($fresh->payload->xml);

        Event::assertDispatched(DteFailed::class);
    }

    public function test_pre_claim_compile_failure_leaves_document_untouched(): void
    {
        // The claim is lost when the status is not Pending: another process
        // owns the document, so this run must not reset it to draft.
        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'status' => DteStatus::Outbox,
        ]);

        try {
            app(Compile::class)->forDte($dte->refresh());
            static::fail('The compile exception was not thrown.');
        } catch (LogicException $e) {
            static::assertStringContainsString('may be compiled', $e->getMessage());
        }

        $fresh = $dte->fresh();

        static::assertSame(DteStatus::Outbox, $fresh->status);
        static::assertTrue($fresh->hasNoFailure());
    }

    public function test_recompile_consumes_the_stored_failure(): void
    {
        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'status' => DteStatus::Draft,
            'failure' => ['stage' => 'compile', 'exception' => null, 'error' => 'previous failure'],
        ]);

        try {
            app(Compile::class)->forDte($dte->transitionTo(DteStatus::Pending)->refresh());
        } catch (LogicException) {
            // The compile itself may fail on a bare document; the claim already cleared the reason.
        }

        static::assertTrue($dte->fresh()->hasNoFailure());
    }

    public function test_fail_to_draft_keeps_folio_when_not_consumed(): void
    {
        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'status' => DteStatus::Building,
            'folio' => 123,
            'pack_retries' => 0,
            'acknowledged_at' => null,
        ]);

        $returned = $dte->failToDraft('compile', 'something broke');

        static::assertSame(DteStatus::Draft, $returned->status);
        static::assertSame(123, $dte->fresh()->folio);
    }

    public function test_fail_to_draft_releases_folio_when_consumed(): void
    {
        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'status' => DteStatus::Building,
            'folio' => 123,
            'pack_retries' => 2,
        ]);

        $dte->failToDraft('stale', 'reclaimed');

        $fresh = $dte->fresh();

        static::assertSame(DteStatus::Draft, $fresh->status);
        static::assertNull($fresh->folio);
        static::assertNull($fresh->sii_caf_id);
        static::assertSame('stale', $fresh->failure['stage']);
    }

    public function test_fail_to_draft_skips_terminal_documents(): void
    {
        Event::fake([DteFailed::class]);

        $dte = SiiDte::factory()->create([
            'status' => DteStatus::Accepted,
            'accepted_at' => now(),
        ]);

        $returned = $dte->failToDraft('compile', 'boom');

        static::assertSame(DteStatus::Accepted, $returned->fresh()->status);
        static::assertTrue($returned->fresh()->hasNoFailure());

        Event::assertNotDispatched(DteFailed::class);
    }

    public function test_second_failure_overwrites_the_reason(): void
    {
        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'status' => DteStatus::Building,
        ]);

        $dte->failToDraft('compile', 'first failure');

        // The draft may be rebuilt (Draft compiles on pack), so a second
        // failure records the latest reason over the previous one.
        $pending = $dte->fresh();
        $pending->transitionTo(DteStatus::Pending);

        $pending->failToDraft('upload', 'second failure');

        $fresh = $dte->fresh();

        static::assertSame('upload', $fresh->failure['stage']);
        static::assertSame('second failure', $fresh->failure['error']);
    }

    public function test_fail_to_draft_detaches_the_envelope(): void
    {
        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'status' => DteStatus::Packed,
            'sii_dte_envelope_id' => 999,
        ]);

        $dte->failToDraft('upload', 'boom');

        $fresh = $dte->fresh();

        static::assertNull($fresh->sii_dte_envelope_id);
        static::assertSame(DteStatus::Draft, $fresh->status);
    }

    public function test_fail_to_draft_logs_and_rethrows_when_the_transaction_fails(): void
    {
        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'status' => DteStatus::Pending,
        ]);

        Event::listen(DteFailed::class, static fn () => throw new RuntimeException('recovery blew up'));

        $this->mock(LoggerInterface::class)->expects('error')->once()->withArgs(static function (string $message, array $context): bool {
            static::assertSame('DTE failure recovery failed, rolling back to the pre-failure state.', $message);
            static::assertSame('dte-failure-recovery', $context['flow']);
            static::assertSame($context['exception'], RuntimeException::class);
            static::assertSame($context['message'], 'recovery blew up');

            return true;
        });

        try {
            $dte->failToDraft('compile', 'boom');
            static::fail('The transaction exception was not rethrown.');
        } catch (RuntimeException $e) {
            static::assertSame('recovery blew up', $e->getMessage());
        }

        $fresh = $dte->fresh();

        static::assertSame(DteStatus::Pending, $fresh->status);
        static::assertTrue($fresh->hasNoFailure());
    }
}
