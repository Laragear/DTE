<?php

namespace Laragear\Dte\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\DateFactory;
use Illuminate\Support\LazyCollection;
use Laragear\Dte\Enums\IecvStatus;
use Laragear\Dte\Jobs\PollIecvTrackIdJob;
use Laragear\Dte\Models\SiiIecv;

class PollIecvStatusCommand extends Command
{
    /**
     * Maximum delay (in seconds) a queued job may use.
     *
     * Uses the hard ceiling for AWS-based queue backends (SQS/SNS).
     */
    protected const int MAX_QUEUE_DELAY_SECONDS = 900;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dte:poll-iecv-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queries SII for the TrackID status of uploaded books, updating them to Accepted or Rejected';

    /**
     * Execute the console command.
     */
    public function handle(Repository $config, DateFactory $date): int
    {
        $backoffSeconds = $config->get('dte.envelopes.backoff_seconds', 60);

        $queueConnection = $config->get('dte.queue.track.connection');
        $queueName = $config->get('dte.queue.track.name', 'default');

        $delayCounter = 0;
        $dispatchedCount = 0;

        foreach ($this->books($date) as $book) {
            $delay = min(self::MAX_QUEUE_DELAY_SECONDS, $delayCounter * $backoffSeconds);

            // Claim the book with a single conditional UPDATE so overlapping
            // scheduler runs cannot dispatch duplicate jobs for it: only the
            // run whose UPDATE affects the row dispatches the job.
            $leased = SiiIecv::query()
                ->whereKey($book->getKey())
                ->where('poll_at', '<=', $date->now())
                ->update(['poll_at' => $date->now()->addSeconds($delay)]);

            if ($leased < 1) {
                continue;
            }

            PollIecvTrackIdJob::dispatch($book)
                ->onConnection($queueConnection)
                ->onQueue($queueName)
                ->delay($delay);

            $delayCounter++;
            $dispatchedCount++;
        }

        $this->info("Dispatched {$dispatchedCount} polling jobs for uploaded books.");

        return self::SUCCESS;
    }

    /**
     * Retrieve pollable books.
     *
     * @return LazyCollection<int, SiiIecv>
     */
    protected function books(DateFactory $date): LazyCollection
    {
        return SiiIecv::query()
            ->where('status', IecvStatus::Uploaded)
            ->whereNotNull('track_id')
            ->where('poll_at', '<=', $date->now())
            ->orderByDesc('id')
            ->cursor();
    }
}
