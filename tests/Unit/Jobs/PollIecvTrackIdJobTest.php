<?php

namespace Tests\Unit\Jobs;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Bus;
use Laragear\Dte\Data\IecvTrackStatus;
use Laragear\Dte\Enums\IecvStatus;
use Laragear\Dte\Events\IecvAccepted;
use Laragear\Dte\Events\IecvRejected;
use Laragear\Dte\Gateways\IecvStatusGateway;
use Laragear\Dte\Jobs\PollIecvTrackIdJob;
use Laragear\Dte\Models\SiiIecv;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\DatabaseTestCase;
use Throwable;

class PollIecvTrackIdJobTest extends DatabaseTestCase
{
    protected function gatewayReturning(IecvTrackStatus $status): IecvStatusGateway
    {
        $gateway = $this->mock(IecvStatusGateway::class);
        $gateway->expects('trackStatus')->andReturn($status);

        return $gateway;
    }

    public function test_marks_the_book_accepted_on_epr(): void
    {
        $book = SiiIecv::factory()->uploaded()->create();

        $dispatched = [];
        $this->app->make(Dispatcher::class)->listen(
            IecvAccepted::class,
            static function (IecvAccepted $event) use (&$dispatched): void {
                $dispatched[] = $event;
            }
        );

        $job = $this->app->make(PollIecvTrackIdJob::class, [
            'book' => $book,
        ]);

        $this->app->call($job->handle(...), [
            'gateway' => $this->gatewayReturning(new IecvTrackStatus(sendState: 'EPR', bookState: 'CTR')),
        ]);

        $fresh = $book->fresh();

        static::assertSame(IecvStatus::Accepted, $fresh->status);
        static::assertNotNull($fresh->accepted_at);
        static::assertCount(1, $dispatched);
    }

    public function test_marks_the_book_rejected_and_persists_errors_on_rsc(): void
    {
        $book = SiiIecv::factory()->uploaded()->create();

        $dispatched = [];
        $this->app->make(Dispatcher::class)->listen(
            IecvRejected::class,
            static function (IecvRejected $event) use (&$dispatched): void {
                $dispatched[] = $event;
            }
        );

        $job = $this->app->make(PollIecvTrackIdJob::class, [
            'book' => $book,
        ]);

        $this->app->call($job->handle(...), [
            $this->gatewayReturning(new IecvTrackStatus(sendState: 'RSC', errors: ['schema invalid'])),
        ]);

        $fresh = $book->fresh();

        static::assertSame(IecvStatus::Rejected, $fresh->status);
        static::assertNotNull($fresh->rejected_at);
        static::assertSame(['schema invalid'], $fresh->errors);
        static::assertCount(1, $dispatched);
    }

    public function test_reschedules_the_poll_when_still_processing(): void
    {
        Bus::fake();

        $book = SiiIecv::factory()->uploaded()->create();

        $job = $this->app->make(PollIecvTrackIdJob::class, [
            'book' => $book,
        ]);

        $this->app->call($job->handle(...), [
            'gateway' => $this->gatewayReturning(new IecvTrackStatus(sendState: 'PRD')),
        ]);

        static::assertSame(IecvStatus::Uploaded, $book->fresh()->status);
        static::assertTrue($book->fresh()->poll_at->isFuture());

        Bus::assertDispatched(PollIecvTrackIdJob::class);
    }

    public function test_keeps_errors_when_the_book_is_accepted_with_warnings(): void
    {
        $book = SiiIecv::factory()->uploaded()->create();

        $job = $this->app->make(PollIecvTrackIdJob::class, [
            'book' => $book,
        ]);

        $this->app->call($job->handle(...), [
            'gateway' => $this->gatewayReturning(new IecvTrackStatus(sendState: 'EPR', errors: ['document 3 has a reparo'])),

        ]);

        static::assertSame(IecvStatus::Accepted, $book->fresh()->status);
        static::assertSame(['document 3 has a reparo'], $book->fresh()->errors);
    }

    public function test_skips_books_that_are_not_uploaded(): void
    {
        Bus::fake();

        $book = SiiIecv::factory()->create(['status' => IecvStatus::Pending]);

        // The gateway must never be reached for a book that is not pollable.
        $gateway = $this->mock(IecvStatusGateway::class);
        $gateway->expects('trackStatus')->zeroOrMoreTimes()->andReturn(new IecvTrackStatus(sendState: 'EPR'));

        $job = $this->app->make(PollIecvTrackIdJob::class, [
            'book' => $book,
        ]);

        $this->app->call($job->handle(...), [
            'gateway' => $gateway,
        ]);

        static::assertSame(IecvStatus::Pending, $book->fresh()->status);

        Bus::assertNothingDispatched();
    }

    public function test_does_not_overwrite_a_book_resolved_by_a_concurrent_poll(): void
    {
        $book = SiiIecv::factory()->uploaded()->create();

        // The gateway resolves the book while the job is waiting on the lock.
        $gateway = $this->mock(IecvStatusGateway::class);
        $gateway->expects('trackStatus')->andReturnUsing(function () use ($book): IecvTrackStatus {
            $book->update(['status' => IecvStatus::Accepted]);

            return new IecvTrackStatus(sendState: 'EPR');
        });

        $job = $this->app->make(PollIecvTrackIdJob::class, [
            'book' => $book,
        ]);

        $this->app->call($job->handle(...), [
            'gateway' => $gateway,
        ]);

        static::assertSame(IecvStatus::Accepted, $book->fresh()->status);
    }

    public function test_warns_and_keeps_the_status_on_an_unknown_sii_state(): void
    {
        $book = SiiIecv::factory()->uploaded()->create();

        $log = $this->mock(LoggerInterface::class);
        $log->expects('warning')
            ->once()
            ->withArgs(static fn (string $message): bool => str_contains($message, 'Unknown SII book status'));

        $job = $this->app->make(PollIecvTrackIdJob::class, [
            'book' => $book,
        ]);

        $this->app->call($job->handle(...), [
            'gateway' => $this->gatewayReturning(new IecvTrackStatus(sendState: 'XXX')),
            'log' => $log,
        ]);

        static::assertSame(IecvStatus::Uploaded, $book->fresh()->status);
    }

    public function test_logs_and_rethrows_when_the_gateway_fails(): void
    {
        $book = SiiIecv::factory()->uploaded()->create();

        $gateway = $this->mock(IecvStatusGateway::class);
        $gateway->expects('trackStatus')->andThrow(new RuntimeException('SII is down.'));

        $log = $this->mock(LoggerInterface::class);
        $log->expects('error')
            ->once()
            ->withArgs(static fn (string $message): bool => str_contains($message, 'SII is down.'));

        $job = $this->app->make(PollIecvTrackIdJob::class, [
            'book' => $book,
        ]);

        try {
            $this->app->call($job->handle(...), [
                'gateway' => $gateway,
                'log' => $log,
            ]);

            static::fail('The polling failure was not rethrown.');
        } catch (RuntimeException $e) {
            static::assertSame('SII is down.', $e->getMessage());
        }

        static::assertSame(IecvStatus::Uploaded, $book->fresh()->status);
    }

    public function test_logs_and_rethrows_when_the_status_decision_fails(): void
    {
        $book = SiiIecv::factory()->uploaded()->create();

        // Fail the update from inside the locked transaction so the job's rollback
        // path runs: the decision closure writes, the event listener throws, and the
        // book must be left on its pre-decision state.
        SiiIecv::updating(static function (SiiIecv $book): void {
            throw new RuntimeException('Database write refused.');
        });

        $log = $this->mock(LoggerInterface::class);
        $log->expects('error')
            ->once()
            ->withArgs(static fn (string $message): bool => str_contains($message, 'rolling back'));
        $log->expects('error')
            ->once()
            ->withArgs(static fn (string $message): bool => str_contains($message, 'Failed to poll TrackID'));

        $job = $this->app->make(PollIecvTrackIdJob::class, [
            'book' => $book,
        ]);

        try {
            $this->app->call($job->handle(...), [
                'gateway' => $this->gatewayReturning(new IecvTrackStatus(sendState: 'EPR')),
                'log' => $log,
            ]);

            static::fail('The decision failure was not rethrown.');
        } catch (Throwable $e) {
            static::assertSame('Database write refused.', $e->getMessage());
        }

        static::assertSame(IecvStatus::Uploaded, $book->fresh()->status);
    }
}
