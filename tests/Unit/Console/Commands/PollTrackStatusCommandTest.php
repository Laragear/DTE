<?php

namespace Tests\Unit\Console\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\DateFactory;
use Illuminate\Support\Facades\Queue;
use Laragear\Dte\Enums\EnvelopeStatus;
use Laragear\Dte\Jobs\PollEnvelopeTrackIdJob;
use Laragear\Dte\Models\SiiDteEnvelope;
use Mockery\MockInterface;
use Tests\DatabaseTestCase;

class PollTrackStatusCommandTest extends DatabaseTestCase
{
    use RefreshDatabase;

    /*
     |--------------------------------------------------------------------------
     | Happy paths
     |--------------------------------------------------------------------------
     */

    public function test_dispatches_jobs_for_uploaded_envelopes_with_track_id(): void
    {
        $queue = Queue::fake();

        $this->config('dte.queue.track.connection', 'database');
        $this->config('dte.queue.track.name', 'dte-queue');

        // Should be queued (poll_at in the past)
        $envelope1 = SiiDteEnvelope::factory()->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '123456789',
            'poll_at' => now()->subMinute(),
        ]);

        $envelope2 = SiiDteEnvelope::factory()->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '987654321',
            'poll_at' => now()->subMinute(),
        ]);

        // Should be ignored (no track id)
        SiiDteEnvelope::factory()->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => null,
            'poll_at' => now()->subMinute(),
        ]);

        // Should be ignored (wrong status)
        SiiDteEnvelope::factory()->create([
            'status' => EnvelopeStatus::Pending,
            'track_id' => '555555555',
            'poll_at' => now()->subMinute(),
        ]);

        // Should be ignored (poll_at is in the future — not yet due)
        SiiDteEnvelope::factory()->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '666666666',
            'poll_at' => now()->addMinutes(5),
        ]);

        $this
            ->artisan('dte:poll-track-status')
            ->expectsOutput('Dispatched 2 polling jobs for uploaded envelopes.')
            ->assertSuccessful();

        $queue->assertPushed(PollEnvelopeTrackIdJob::class, 2);

        $queue->assertPushed(
            PollEnvelopeTrackIdJob::class,
            static function (PollEnvelopeTrackIdJob $job) use ($envelope1): bool {
                return $job->envelope->id === $envelope1->id;
            },
        );

        $queue->assertPushed(
            PollEnvelopeTrackIdJob::class,
            static function (PollEnvelopeTrackIdJob $job) use ($envelope2): bool {
                return $job->envelope->id === $envelope2->id;
            },
        );
    }

    public function test_caps_poll_delay_to_fifteen_minutes(): void
    {
        $queue = Queue::fake();

        $this->config('dte.queue.track.connection', 'database');
        $this->config('dte.queue.track.name', 'dte-queue');
        $this->config('dte.envelopes.backoff_seconds', 2000);

        SiiDteEnvelope::factory()->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '111111111',
            'poll_at' => now()->subMinute(),
        ]);

        SiiDteEnvelope::factory()->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '222222222',
            'poll_at' => now()->subMinute(),
        ]);

        $this
            ->artisan('dte:poll-track-status')
            ->assertSuccessful();

        $jobs = $queue->pushed(PollEnvelopeTrackIdJob::class);

        static::assertCount(2, $jobs);

        // Without the cap, at least one job would be delayed 2000 seconds.
        $maxDelay = $jobs->max(fn(PollEnvelopeTrackIdJob $job) => (int) $job->delay);

        static::assertLessThanOrEqual(905, $maxDelay);
        static::assertGreaterThan(0, $maxDelay);
    }

    public function test_skips_envelope_when_lease_is_lost(): void
    {
        $queue = Queue::fake();

        $this->config('dte.queue.track.connection', 'database');
        $this->config('dte.queue.track.name', 'dte-queue');

        SiiDteEnvelope::factory()->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '123456789',
            'poll_at' => now()->subMinute(),
        ]);

        // The cursor sees the envelope, but the conditional UPDATE finds no
        // leasable row, as if a concurrent run claimed it first.
        $this->mock(DateFactory::class, function (MockInterface $mock): void {
            $calls = 0;

            $mock->shouldReceive('now')->andReturnUsing(static function () use (&$calls) {
                $calls++;

                return $calls === 1 ? now()->addMinute() : now()->subHour();
            });
        });

        $this
            ->artisan('dte:poll-track-status')
            ->expectsOutput('Dispatched 0 polling jobs for uploaded envelopes.')
            ->assertSuccessful();

        $queue->assertNotPushed(PollEnvelopeTrackIdJob::class);
    }
}
