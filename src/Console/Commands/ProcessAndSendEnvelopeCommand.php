<?php

namespace Laragear\Dte\Console\Commands;

use const JSON_THROW_ON_ERROR;

use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\DateFactory;
use Laragear\Dte\Actions\CreateEnvelope\CreateEnvelope;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\EnvelopeStatus;
use Laragear\Dte\Events\EnvelopeSending;
use Laragear\Dte\Events\EnvelopeSent;
use Laragear\Dte\Gateways\BoletaRestGateway;
use Laragear\Dte\Gateways\UploadGateway;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteEnvelope;
use LogicException;
use Throwable;

use function blank;
use function is_numeric;
use function json_encode;

class ProcessAndSendEnvelopeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dte:process-envelope {envelope_id}
                            {--reclaim-stale : Reclaim envelopes stuck mid-send back to pending}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process an envelope, signs it, and send it to the SII';

    /**
     * Execute the console command.
     */
    public function handle(
        Dispatcher $event,
        DateFactory $date,
        CreateEnvelope $create,
        UploadGateway $upload,
        BoletaRestGateway $boletaUpload
    ): int {
        // Claims the envelope with a single conditional UPDATE before doing any work: a duplicate
        // run racing this one aborts instead of uploading twice. No transaction spans the build
        // and upload (the SII gateway call is external and long-lived). Other envelopes stuck
        // mid-send (Assembling, Sending) are reclaimed automatically first, so the previous
        // crash cannot wedge the queue behind one envelope.
        if ($this->option('reclaim-stale')) {
            $this->reclaimStaleEnvelopes($date);
            $this->reclaimStaleDtes($date);
        }

        $source = $this->envelope();
        $wasSigned = $source->status === EnvelopeStatus::Signed;

        $envelope = $this->claim($source, $date);

        if ($wasSigned) {
            $envelope->loadMissing('payload');
        }

        if (! $wasSigned || blank($envelope->payload?->xml)) {
            $envelope = $create->forEnvelope($envelope);
        }

        $envelope = $this->markAsSending($envelope, $date);

        $event->dispatch(new EnvelopeSending($envelope));

        try {
            $trackId = $this->uploadEnvelope($envelope, $upload, $boletaUpload);
        } catch (Throwable $e) {
            $this->markAsFailed($envelope, $date);

            $this->releaseOrFailDtes($envelope, $date, $e);

            throw $e;
        }

        $this->markAsUploaded($envelope, $trackId, $date);
        $this->markDtesAsSent($envelope, $date);

        $event->dispatch(new EnvelopeSent($envelope));

        $this->info("Envelope [{$envelope->getKey()}] processed and sent with Track ID: {$trackId}");

        return self::SUCCESS;
    }

    /**
     * Upload the envelope XML to the SII using the appropriate gateway.
     */
    protected function uploadEnvelope(
        SiiDteEnvelope $envelope,
        UploadGateway $upload,
        BoletaRestGateway $boletaUpload,
    ): string {
        return $envelope->type === 'boleta'
            ? $boletaUpload->upload($envelope, $envelope->payload->xml)
            : $upload->upload($envelope, $envelope->payload->xml);
    }

    /**
     * Mark the envelope as uploading before the SII gateway call.
     */
    protected function markAsSending(SiiDteEnvelope $envelope, DateFactory $date): SiiDteEnvelope
    {
        // Persisted so a crash during the external upload leaves Sending behind instead
        // of a stale Assembling: the stale recovery below can tell "built, upload
        // unknown" from "still building". No refresh here: the built payload
        // live only in memory until the upload succeeds.
        $envelope->update([
            'status' => EnvelopeStatus::Sending,
            'updated_at' => $date->now(),
        ]);

        return $envelope;
    }

    /**
     * Release DTEs back to the outbox with a bounded retry, or fail them to draft.
     */
    protected function releaseOrFailDtes(SiiDteEnvelope $envelope, DateFactory $date, Throwable $e): void
    {
        // Mirrors releaseOrRejectAllDtes(): the upload failure may repeat forever,
        // so the retry is bounded. Exhausted DTEs go back to Draft with the stored
        // reason, and folios possibly consumed by the SII are released.
        $envelope->loadMissing('dtes');

        $failure = [
            'stage' => 'upload',
            'exception' => $e::class,
            'error' => $e->getMessage(),
        ];

        [$exhausted, $retryable] = $envelope->dtes->partition(
            static fn (SiiDte $dte): bool => $dte->pack_retries + 1 >= config('dte.envelopes.max_retries', 3)
        );

        if ($retryable->isNotEmpty()) {
            $envelope->dtes()
                ->whereIn('id', $retryable->modelKeys())
                ->increment('pack_retries', 1, [
                    'sii_dte_envelope_id' => null,
                    'status' => DteStatus::Outbox,
                    'failure' => json_encode($failure, JSON_THROW_ON_ERROR),
                    'updated_at' => $date->now(),
                ]);
        }

        foreach ($exhausted as $dte) {
            $dte->failToDraft('upload', $e);
        }
    }

    /**
     * Mark the envelope as failed after an upload error.
     */
    protected function markAsFailed(SiiDteEnvelope $envelope, DateFactory $date): void
    {        // Terminal at envelope granularity, but folios were never consumed: the caller
        // detaches the paired DTEs back to Outbox so they may pack into a new envelope.
        $envelope->update([
            'status' => EnvelopeStatus::Failed,
            'updated_at' => $date->now(),
        ]);
    }

    /**
     * Update the envelope with the track ID and upload timestamp.
     */
    protected function markAsUploaded(SiiDteEnvelope $envelope, string $trackId, DateFactory $date): void
    {
        $envelope->update([
            'track_id' => $trackId,
            'status' => EnvelopeStatus::Uploaded,
            'poll_at' => $date->now(),
            'uploaded_at' => $date->now(),
        ]);
    }

    /**
     * Mark all child DTEs as sent.
     */
    protected function markDtesAsSent(SiiDteEnvelope $envelope, DateFactory $date): void
    {
        $now = $date->now();

        // Preload the DTEs so the sync below uses pre-update statuses.
        $envelope->loadMissing('dtes');

        // Only issue a single `UPDATE` to the database.
        $envelope->dtes()->where('status', DteStatus::Packed)->update([
            'status' => DteStatus::Sent,
            'updated_at' => $now,
        ]);

        // Force update and sync each model.
        foreach ($envelope->dtes as $dte) {
            if ($dte->status !== DteStatus::Packed) {
                continue;
            }

            $dte->forceFill([
                'status' => DteStatus::Sent,
                'updated_at' => $now,
            ]);

            $dte->syncOriginal();
        }
    }

    /**
     * Claim a pending or signed envelope for processing.
     *
     * @throws LogicException When another run already claimed the envelope.
     */
    protected function claim(SiiDteEnvelope $envelope, DateFactory $date): SiiDteEnvelope
    {
        $claimed = SiiDteEnvelope::query()
            ->whereKey($envelope->getKey())
            ->whereIn('status', [EnvelopeStatus::Pending, EnvelopeStatus::Signed])
            ->update(['status' => EnvelopeStatus::Assembling, 'updated_at' => $date->now()]);

        if ($claimed < 1) {
            throw new LogicException(
                "The envelope [{$envelope->getKey()}] is [{$envelope->status->value}] and cannot be processed twice."
            );
        }

        $envelope->forceFill(['status' => EnvelopeStatus::Assembling]);

        return $envelope->refresh();
    }

    /**
     * Reclaim DTEs stuck mid-compile back to Draft, storing the reason.
     *
     * @return int The number of reclaimed DTEs.
     */
    public function reclaimStaleDtes(DateFactory $date, int $staleMinutes = 30): int
    {
        // A crashed worker leaves the DTE wedged in Building or Signing with no
        // one to catch the failure. Each row goes through failToDraft() so the
        // folio guard and payload cleanup a bulk UPDATE cannot do are applied.
        // ponytail: a genuinely long compile older than the window gets reclaimed
        // too — the same residual race reclaimStaleEnvelopes already accepts.
        $stale = SiiDte::query()
            ->whereIn('status', [DteStatus::Building, DteStatus::Signing])
            ->where('updated_at', '<=', $date->now()->subMinutes($staleMinutes))
            ->get();

        /** @var SiiDte $dte */
        foreach ($stale as $dte) {
            $dte->failToDraft('stale', "Compilation did not finish within [{$staleMinutes}] minutes; reclaimed.");
        }

        return $stale->count();
    }

    /**
     * Reclaim envelopes stuck mid-send back to Pending.
     *
     * Covers both failure windows: Assembling (crashed while building)
     * and Sending (crashed during the SII upload, where reception is
     * unknown and the upload must be retried). Returns the number of
     * reclaimed envelopes.
     */
    public function reclaimStaleEnvelopes(DateFactory $date, int $staleMinutes = 30): int
    {
        // A crash between the claim and the upload leaves the envelope wedged; without
        // this sweep it would never be processed again. Sending is included because
        // the upload outcome is unknown after a crash (safe to rebuild and re-upload),
        // the claim below guards against duplicates racing the retry.
        return SiiDteEnvelope::query()
            ->whereIn('status', [EnvelopeStatus::Assembling, EnvelopeStatus::Sending])
            ->where('updated_at', '<=', $date->now()->subMinutes($staleMinutes))
            ->update(['status' => EnvelopeStatus::Pending, 'updated_at' => $date->now()]);
    }

    /**
     * Retrieves the envelope from the command argument.
     */
    protected function envelope(): SiiDteEnvelope
    {
        /** @var SiiDteEnvelope|numeric-string $envelope */
        $envelope = $this->argument('envelope_id');

        if (is_numeric($envelope)) {
            $envelope = SiiDteEnvelope::findOrFail($envelope);
        }

        return $envelope;
    }
}
