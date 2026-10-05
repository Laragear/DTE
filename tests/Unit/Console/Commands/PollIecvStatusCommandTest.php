<?php

namespace Tests\Unit\Console\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laragear\Dte\Enums\IecvStatus;
use Laragear\Dte\Jobs\PollIecvTrackIdJob;
use Laragear\Dte\Models\SiiIecv;
use Tests\DatabaseTestCase;

class PollIecvStatusCommandTest extends DatabaseTestCase
{
    use RefreshDatabase;

    public function test_dispatches_jobs_for_uploaded_books_with_track_id(): void
    {
        Queue::fake();

        $this->config('dte.queue.track.connection', 'database');
        $this->config('dte.queue.track.name', 'dte-queue');

        $book = SiiIecv::factory()->uploaded('123456789')->create([
            'poll_at' => now()->subMinute(),
        ]);

        // No track id: not pollable.
        SiiIecv::factory()->create([
            'status' => IecvStatus::Uploaded,
            'track_id' => null,
            'poll_at' => now()->subMinute(),
        ]);

        // Wrong status: not awaiting a verdict.
        SiiIecv::factory()->create([
            'status' => IecvStatus::Accepted,
            'track_id' => '555555555',
            'poll_at' => now()->subMinute(),
        ]);

        // Not yet due.
        SiiIecv::factory()->uploaded('222222222')->create([
            'poll_at' => now()->addDay(),
        ]);

        $this->artisan('dte:poll-iecv-status')
            ->expectsOutput('Dispatched 1 polling jobs for uploaded books.')
            ->assertSuccessful();

        Queue::assertPushed(PollIecvTrackIdJob::class, 1);
    }

    public function test_dispatches_nothing_when_no_book_is_due(): void
    {
        Queue::fake();

        $this->artisan('dte:poll-iecv-status')
            ->expectsOutput('Dispatched 0 polling jobs for uploaded books.')
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_leases_the_first_book_without_a_delay(): void
    {
        Queue::fake();

        $this->config('dte.envelopes.backoff_seconds', 300);

        $book = SiiIecv::factory()->uploaded()->create([
            'poll_at' => now()->subMinute(),
        ]);

        $this->artisan('dte:poll-iecv-status')->assertSuccessful();

        // The delay counter starts at zero, so the first book is leased at "now".
        static::assertTrue($book->fresh()->poll_at->isBetween(
            now()->subMinute(),
            now()->addSecond(),
        ));
    }

    public function test_skips_a_book_that_is_no_longer_due_when_the_lease_runs(): void
    {
        Queue::fake();

        $book = SiiIecv::factory()->uploaded()->create([
            'poll_at' => now()->subMinute(),
        ]);

        // The read cursor yields the book as due, but by the time the conditional
        // lease UPDATE runs the book is scheduled again (an overlapping scheduler
        // run won the race), so the UPDATE matches no row and it must be skipped.
        $connection = SiiIecv::query()->getConnection();

        $raced = false;

        $connection->listen(static function ($query) use ($connection, $book, &$raced): void {
            // One shot only: the racing UPDATE below emits its own query event.
            if ($raced || ! str_contains($query->sql, 'select * from "sii_iecvs"')) {
                return;
            }

            $raced = true;

            $connection->table('sii_iecvs')->where('id', $book->getKey())->update([
                'poll_at' => now()->addHour(),
            ]);
        });

        $this->artisan('dte:poll-iecv-status')
            ->expectsOutput('Dispatched 0 polling jobs for uploaded books.')
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_applies_a_growing_backoff_to_later_books(): void
    {
        Queue::fake();

        $this->config('dte.envelopes.backoff_seconds', 300);

        $newest = SiiIecv::factory()->uploaded('111111111')->create([
            'poll_at' => now()->subMinute(),
        ]);

        $oldest = SiiIecv::factory()->uploaded('222222222')->create([
            'poll_at' => now()->subMinute(),
        ]);

        $this->artisan('dte:poll-iecv-status')->assertSuccessful();

        // Books are processed newest-first, so the higher id is leased first and
        // takes no delay, while the next one receives the backoff.
        static::assertTrue($newest->fresh()->poll_at->greaterThan($oldest->fresh()->poll_at));
    }
}
