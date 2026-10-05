<?php

namespace Laragear\Dte\Jobs;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\DateFactory;
use Laragear\Dte\Data\IecvTrackStatus;
use Laragear\Dte\Enums\IecvStatus;
use Laragear\Dte\Events\IecvAccepted;
use Laragear\Dte\Events\IecvRejected;
use Laragear\Dte\Gateways\IecvStatusGateway;
use Laragear\Dte\Models\SiiIecv;
use Psr\Log\LoggerInterface;
use Throwable;

use function blank;
use function implode;

#[Backoff([30, 60, 60 * 2, 60 * 5, 60 * 10])]
#[Tries(5)]
#[Timeout(60 * 2)]
class PollIecvTrackIdJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public SiiIecv $book,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(
        LoggerInterface $log,
        Dispatcher $event,
        IecvStatusGateway $gateway,
        DateFactory $date,
    ): void {
        if ($this->shouldNotPoll()) {
            return;
        }

        try {
            $this->process(
                $gateway->trackStatus($this->book), $event, $log, $date
            );
        } catch (Throwable $e) {
            $log->error("Failed to poll TrackID {$this->book->track_id}: {$e->getMessage()}");

            throw $e;
        }
    }

    /**
     * Checks if this book should not be polled.
     */
    protected function shouldNotPoll(): bool
    {
        return $this->book->status !== IecvStatus::Uploaded || blank($this->book->track_id);
    }

    /**
     * Apply the SII verdict to the book.
     */
    protected function process(
        IecvTrackStatus $status,
        Dispatcher $event,
        LoggerInterface $log,
        DateFactory $date,
    ): void {
        if ($status->isAccepted()) {
            $this->handleAccepted($status, $event, $log, $date);
        } elseif ($status->isRejected()) {
            $this->handleRejected($status, $event, $log, $date);
        } elseif ($status->isProcessing()) {
            $this->handleProcessing($log, $date);
        } else {
            $log->warning("Unknown SII book status received for track ID {$this->book->track_id}: ".$status->raw);
        }
    }

    /**
     * Run a book status decision inside a locked transaction.
     */
    protected function decide(LoggerInterface $log, callable $decision): bool
    {
        // Re-checks the book is still pollable after locking: a duplicate job
        // racing this one loses here and aborts without writing.
        try {
            return $this->book->getConnection()->transaction(function () use ($log, $decision): bool {
                $this->book->refreshForUpdate();

                if ($this->shouldNotPoll()) {
                    $log->warning('Skipping book status decision: book already resolved by a concurrent poll.', [
                        'flow' => 'poll-iecv-track-id',
                        'book_id' => $this->book->getKey(),
                        'status' => $this->book->status->value,
                    ]);

                    return false;
                }

                $decision();

                return true;
            });
        } catch (Throwable $e) {
            $log->error('Book status decision failed, rolling back to the pre-decision state.', [
                'flow' => 'poll-iecv-track-id',
                'book_id' => $this->book->getKey(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handles the accepted book status, keeping any reported errors.
     */
    protected function handleAccepted(
        IecvTrackStatus $status,
        Dispatcher $event,
        LoggerInterface $log,
        DateFactory $date,
    ): void {
        $this->decide($log, function () use ($status, $event, $date, $log): void {
            $this->book->update([
                'status' => IecvStatus::Accepted,
                'accepted_at' => $date->now(),
                // The file is accepted but the book may still carry errors; keep them.
                'errors' => $status->errors ?: null,
            ]);

            if ($status->hasBookErrors()) {
                $log->warning('The SII accepted the book but reported errors: '.implode('; ', $status->errors), [
                    'book_id' => $this->book->getKey(),
                    'track_id' => $this->book->track_id,
                ]);
            }

            $event->dispatch(new IecvAccepted($this->book));
        });
    }

    /**
     * Handles the rejected book status, persisting the SII errors.
     */
    protected function handleRejected(
        IecvTrackStatus $status,
        Dispatcher $event,
        LoggerInterface $log,
        DateFactory $date,
    ): void {
        $this->decide($log, function () use ($status, $event, $date): void {
            $this->book->update([
                'status' => IecvStatus::Rejected,
                'rejected_at' => $date->now(),
                'errors' => $status->errors ?: null,
            ]);

            $event->dispatch(new IecvRejected($this->book));
        });
    }

    /**
     * Reschedule a processing book after the mandated delay.
     */
    protected function handleProcessing(LoggerInterface $log, DateFactory $date): void
    {
        // Seconds to wait before the next poll. Books are whole-period files and
        // are never smaller than a document envelope, so the larger delay floor
        // SII mandates for >= 30 KB applies.
        $delay = $date->now()->addSeconds(360);

        // Prevent the cron from re-polling until the delay elapses.
        $this->book->update(['poll_at' => $delay]);

        $log->debug('The SII is still processing the book, rescheduling the poll.', [
            'book_id' => $this->book->getKey(),
            'track_id' => $this->book->track_id,
            'poll_at' => $delay,
        ]);

        // Dispatch a fresh job after the delay. `poll_at` is already bumped into
        // the future above, so the cron-based PollIecvStatusCommand will not
        // re-pick this book until then.
        static::dispatch($this->book)->delay($delay);
    }
}
