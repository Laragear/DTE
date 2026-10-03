<?php

namespace Laragear\Dte\Jobs;

use DateTimeInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\DateFactory;
use Illuminate\Support\Str;
use Laragear\Dte\Data\TrackStatus;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\EnvelopeStatus;
use Laragear\Dte\Events\DteAccepted;
use Laragear\Dte\Events\DteRejected;
use Laragear\Dte\Events\EnvelopeAccepted;
use Laragear\Dte\Events\EnvelopeRejected;
use Laragear\Dte\Gateways\BoletaRestGateway;
use Laragear\Dte\Gateways\Exceptions\TokenInvalidException;
use Laragear\Dte\Gateways\SoapGateway;
use Laragear\Dte\Gateways\TokenStatus;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Support\TokenAuthenticator;
use Laragear\Dte\Support\XmlDomFactory as Xml;
use Psr\Log\LoggerInterface;
use Throwable;
use function blank;
use function max;

#[Backoff([30, 60, 60 * 2, 60 * 5, 60 * 10])]
#[Tries(5)]
#[Timeout(60 * 2)]
class PollEnvelopeTrackIdJob implements ShouldQueue
{
    use Queueable;

    /**
     * Config key toggling the automatic interchange submission after acceptance.
     */
    protected const string AUTO_SEND_INTERCHANGE_CONFIG = 'dte.dim.auto_send_interchange';

    /**
     * Create a new job instance.
     */
    public function __construct(
        public SiiDteEnvelope $envelope,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(
        LoggerInterface $log,
        Dispatcher $event,
        SoapGateway $gateway,
        BoletaRestGateway $boletaGateway,
        TokenAuthenticator $authenticator,
        ConfigRepository $config,
        Xml $factory,
        DateFactory $date,
    ): void {
        if ($this->shouldNotPoll()) {
            return;
        }

        try {
            if ($this->envelope->type === 'boleta') {
                $this->processBoletaTrackIdStatus(
                    $boletaGateway->trackStatus($this->envelope), $event, $config, $log, $date
                );
            } else {
                $this->processTrackIdStatus(
                    $this->queryTrackIdStatus($gateway, $authenticator), $event, $config, $log, $factory, $date
                );
            }
        } catch (Throwable $e) {
            $log->error("Failed to poll TrackID {$this->envelope->track_id}: {$e->getMessage()}");

            throw $e;
        }
    }

    /**
     * Run an envelope status decision inside a locked transaction.
     */
    protected function decide(LoggerInterface $log, callable $decision): bool
    {
        // Re-checks the envelope is still pollable after locking: a duplicate
        // job racing this one loses here and aborts without writing. Returns
        // whether the decision was applied. Job dispatches registered via
        // afterCommit fire only when the decision commits.
        try {
            return $this->envelope->getConnection()->transaction(function () use ($log, $decision): bool {
                $this->envelope->refreshForUpdate();

                if ($this->shouldNotPoll()) {
                    $log->warning('Skipping envelope status decision: envelope already resolved by a concurrent poll.',
                        [
                            'flow' => 'poll-track-id',
                            'envelope_id' => $this->envelope->getKey(),
                            'status' => $this->envelope->status->value,
                        ]);

                    return false;
                }

                $decision();

                return true;
            });
        } catch (Throwable $e) {
            $log->error('Envelope status decision failed, rolling back to the pre-decision state.', [
                'flow' => 'poll-track-id',
                'envelope_id' => $this->envelope->getKey(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Processes the SII boleta track ID status response.
     *
     * @see https://www4c.sii.cl/bolcoreinternetui/api/openapi.yaml (X-Retry-After header)
     */
    protected function processBoletaTrackIdStatus(
        TrackStatus $status,
        Dispatcher $event,
        ConfigRepository $config,
        LoggerInterface $log,
        DateFactory $date,
    ): void {
        if ($status->isProcessed()) {
            $this->handleBoletaAccepted($event, $config, $log, $date, $status->raw);
        } elseif ($status->isRejected()) {
            $this->handleRejected($event, $config, $log, $date);
        } elseif ($status->isProcessing()) {
            // Use X-Retry-After header value for the delay (SII rate limiting).
            $this->handleProcessing($config, $date, $status->retryAfter);
        } else {
            $log->warning(
                "Unknown SII boleta track ID status received for track ID {$this->envelope->track_id}: ".
                json_encode($status->raw)
            );
        }
    }

    /**
     * Handles the accepted boleta envelope status and parses DTE rejections if any.
     */
    protected function handleBoletaAccepted(
        Dispatcher $event,
        ConfigRepository $config,
        LoggerInterface $log,
        DateFactory $date,
        array $status
    ): void {
        $applied = $this->decide($log, function () use ($event, $date, $status): void {
            $this->parseBoletaProcessedEnvelopeDtes($event, $date, $status);

            $this->envelope->status = EnvelopeStatus::Accepted;
            $this->envelope->accepted_at = $date->now();
            $this->envelope->save();

            $event->dispatch(new EnvelopeAccepted($this->envelope));
        });

        if ($applied && $config->get(static::AUTO_SEND_INTERCHANGE_CONFIG, true)) {
            $this->envelope->getConnection()->afterCommit(function (): void {
                SendInterchangeEnvelopeJob::dispatch($this->envelope);
            });
        }
    }

    /**
     * Parses the Boleta JSON response to check if any DTE was rejected or repaired.
     */
    protected function parseBoletaProcessedEnvelopeDtes(Dispatcher $event, DateFactory $date, array $status): void
    {
        $rechazados = 0;
        $reparos = 0;

        foreach ($status['estadistica'] ?? [] as $stat) {
            $rechazados += (int) ($stat['rechazados'] ?? 0);
            $reparos += (int) ($stat['reparos'] ?? 0);
        }

        if ($rechazados > 0 || $reparos > 0) {
            $this->saveEnvelopeRepairs($status, json_encode($status));

            foreach ($this->envelope->dtes as $dte) {
                $this->envelope->getConnection()->afterCommit(
                    static fn(): mixed => PollDteStatusJob::dispatch($dte)
                );
            }
        } else {
            $this->updateEnvelopeDtes($date, $event);
        }
    }

    /**
     * Checks if this envelope should not be polled.
     */
    protected function shouldNotPoll(): bool
    {
        return $this->envelope->status !== EnvelopeStatus::Uploaded
            || blank($this->envelope->track_id);
    }

    /**
     * Queries the SII for the current status of the track ID.
     */
    protected function queryTrackIdStatus(SoapGateway $gateway, TokenAuthenticator $authenticator): string
    {
        // Uses the authenticator's retryWithFreshToken() loop: on
        // TokenInvalidException (SII returned 001/002/003), the authenticator
        // refreshes the token and retries (up to 3 total attempts before the
        // exception propagates and the queue backoff handles the delay).
        $issuer = $this->envelope->issuer_rut;

        return $authenticator->retryWithFreshToken(function () use ($gateway, $issuer, $authenticator): string {
            $token = $authenticator->token($issuer);

            $response = $gateway->query($token, 'QueryEstUp', 'getEstUp', [
                'RutCompany' => $issuer->num,
                'DvCompany' => $issuer->vd,
                'TrackId' => $this->envelope->track_id,
                'Token' => $token->value,
            ]);

            $xml = is_object($response) ? $response->getEstUpResult ?? '' : (string) $response;

            // SII returns 001/002/003 for an invalid token (signal the trait to refresh).
            if ($this->isTokenInvalidStatus($xml)) {
                throw new TokenInvalidException('SII SOAP token was invalidated (001/002/003).');
            }

            return $xml;
        }, $issuer);
    }

    /**
     * Processes the SII track ID status response.
     */
    protected function processTrackIdStatus(
        string $xml,
        Dispatcher $event,
        ConfigRepository $config,
        LoggerInterface $log,
        Xml $factory,
        DateFactory $date,
    ): void {
        if (Str::contains($xml, '<ESTADO>EPR</ESTADO>')) {
            $this->handleAccepted($event, $config, $log, $factory, $date, $xml);
        } elseif ($this->isRejectedStatus($xml)) {
            $this->handleRejected($event, $config, $log, $date);
        } elseif ($this->isProcessingStatus($xml)) {
            $this->handleProcessing($config, $date);
        } else {
            $log->warning("Unknown SII track ID status received for track ID {$this->envelope->track_id}: ".$xml);
        }
    }

    /**
     * Checks if the XML response indicates an invalid/expired SOAP token.
     */
    protected function isTokenInvalidStatus(string $xml): bool
    {
        // SII returns 001 (inactive), 002 (invalid) or 003 (invalid) for a
        // token that must be refreshed by re-authenticating.
        return Str::contains($xml, array_map(
            static fn(string $code) => '<ESTADO>'.$code.'</ESTADO>',
            TokenStatus::INVALID_CODES,
        ));
    }

    /**
     * Handles the accepted envelope status and parses DTE rejections if any.
     */
    protected function handleAccepted(
        Dispatcher $event,
        ConfigRepository $config,
        LoggerInterface $log,
        Xml $factory,
        DateFactory $date,
        string $xml
    ): void {
        $applied = $this->decide($log, function () use ($event, $factory, $date, $xml): void {
            $this->parseProcessedEnvelopeDtes($event, $factory, $date, $xml);

            $this->envelope->status = EnvelopeStatus::Accepted;
            $this->envelope->accepted_at = $date->now();
            $this->envelope->save();

            $event->dispatch(new EnvelopeAccepted($this->envelope));
        });

        if ($applied && $config->get(static::AUTO_SEND_INTERCHANGE_CONFIG, true)) {
            $this->envelope->getConnection()->afterCommit(
                fn(): mixed => SendInterchangeEnvelopeJob::dispatch($this->envelope)
            );
        }
    }

    /**
     * Parses the EPR XML response to check if any DTE was rejected or repaired.
     */
    protected function parseProcessedEnvelopeDtes(Dispatcher $event, Xml $factory, DateFactory $date, string $xml): void
    {
        $simple = $factory->simpleXml($xml);

        $body = $simple->children('SII', true)->RESP_BODY ?? null;

        // If there are rejections or reparos, we query the state of every DTE individually
        $rechazados = (int) ($body?->children()->RECHAZADOS ?? 0);
        $reparos = (int) ($body?->children()->REPAROS ?? 0);

        if ($rechazados > 0 || $reparos > 0) {
            $friendly = json_decode(json_encode($simple), true);
            $this->saveEnvelopeRepairs($friendly, $xml);

            foreach ($this->envelope->dtes as $dte) {
                // Dispatch a job to query the specific DTE status from SII with a job.
                $this->envelope->getConnection()->afterCommit(static function () use ($dte): void {
                    PollDteStatusJob::dispatch($dte);
                });
            }
        } else {
            $this->updateEnvelopeDtes($date, $event);
        }
    }

    /**
     * Saves the SII repairs and the raw response to the envelope and its payload.
     */
    protected function saveEnvelopeRepairs(array $friendly, string $raw): void
    {
        $this->envelope->repairs = $friendly;

        if ($this->envelope->relationLoaded('payload') || $this->envelope->payload) {
            $this->envelope->payload->update(['sii_response' => $raw]);
        }
    }

    /**
     * Handles the rejected envelope status.
     */
    protected function handleRejected(
        Dispatcher $event,
        ConfigRepository $config,
        LoggerInterface $log,
        DateFactory $date
    ): void {
        $this->decide($log, function () use ($event, $config, $date): void {
            $this->envelope->status = EnvelopeStatus::Rejected;
            $this->envelope->rejected_at = $date->now();
            $this->envelope->save();

            $event->dispatch(new EnvelopeRejected($this->envelope));

            $this->releaseOrRejectAllDtes($event, $config, $date);
        });
    }

    /**
     * Release rejected DTEs for re-packing, or reject them when retries end.
     */
    protected function releaseOrRejectAllDtes(Dispatcher $event, ConfigRepository $config, DateFactory $date): void
    {
        $maxRetries = $config->get('dte.envelopes.max_retries', 3);
        $now = $date->now();

        // Preload the DTEs so the split below uses fresh rows.
        $this->envelope->loadMissing('dtes');

        $retryable = $this->envelope->dtes->filter(fn(SiiDte $dte): bool => $dte->pack_retries < $maxRetries);
        $rejected = $this->envelope->dtes->filter(fn(SiiDte $dte): bool => $dte->pack_retries >= $maxRetries);

        $this->releaseRetryableDtes($retryable, $now);
        $this->rejectExhaustedDtes($rejected, $now, $event);
    }

    /**
     * Release DTEs back to the outbox for a future retry.
     *
     * @param  EloquentCollection<int, SiiDte>  $retryable
     */
    protected function releaseRetryableDtes(EloquentCollection $retryable, DateTimeInterface $now): void
    {
        if ($retryable->isEmpty()) {
            return;
        }

        $this->envelope->dtes()
            ->whereIn('id', $retryable->modelKeys())
            ->increment('pack_retries', 1, [
                'sii_dte_envelope_id' => null,
                'status' => DteStatus::Outbox,
                'updated_at' => $now,
            ]);

        foreach ($retryable as $dte) {
            $dte->forceFill([
                'pack_retries' => $dte->pack_retries + 1,
                'sii_dte_envelope_id' => null,
                'status' => DteStatus::Outbox,
                'updated_at' => $now,
            ]);

            $dte->syncOriginal();
        }
    }

    /**
     * Reject DTEs that exhausted retries for manual handling.
     *
     * @param  EloquentCollection<int, SiiDte>  $rejected
     */
    protected function rejectExhaustedDtes(
        EloquentCollection $rejected,
        DateTimeInterface $now,
        Dispatcher $event
    ): void {
        if ($rejected->isEmpty()) {
            return;
        }

        $this->envelope->dtes()
            ->whereIn('id', $rejected->modelKeys())
            ->update([
                'status' => DteStatus::Rejected,
                'rejected_at' => $now,
                'updated_at' => $now,
            ]);

        foreach ($rejected as $dte) {
            $dte->forceFill([
                'status' => DteStatus::Rejected,
                'rejected_at' => $now,
                'updated_at' => $now,
            ]);
            $dte->syncOriginal();

            $event->dispatch(new DteRejected($dte));
        }
    }

    /**
     * Reschedule a processing envelope after the mandated delay.
     */
    protected function handleProcessing(ConfigRepository $config, DateFactory $date, int $retryAfter = 0): void
    {
        // Seconds to wait before next poll (from X-Retry-After header for) Use
        // X-Retry-After header value if provided (boleta REST API), otherwise
        // use the configured floor based on envelope size (SOAP API).
        $delay = $date->now()->addSeconds(
            $retryAfter > 0 ? $retryAfter : $this->effectiveDelay($config, $this->envelopeSizeBytes())
        );

        // Prevent the cron from re-polling until the delay elapses.
        $this->envelope->update([
            'poll_at' => $delay,
        ]);

        // Dispatch a fresh job after the delay so the worker picks it up. The new dispatch
        // avoids the endless-loop concern: `poll_at` is already bumped into the future above,
        // so the cron-based PollTrackStatusCommand won't re-pick this envelope until then.
        static::dispatch($this->envelope)->delay($delay);
    }

    /**
     * Compute the minimum delay before the next status poll.
     */
    protected function effectiveDelay(ConfigRepository $config, int $envelopeSizeBytes): int
    {
        if ($envelopeSizeBytes < 30 * 1024) {
            return 120 + max(0, (int) $config->get('dte.polling.delay_under_30kb', 0));
        }

        return 360 + max(0, (int) $config->get('dte.polling.delay_over_30kb', 0));
    }

    /**
     * Returns the size in bytes of the uploaded envelope XML.
     */
    protected function envelopeSizeBytes(): int
    {
        if ($this->envelope->relationLoaded('payload') && $this->envelope->payload?->xml) {
            return strlen($this->envelope->payload->xml);
        }

        // When the payload is not available, assumes the larger (30 KB) bucket, so the
        // delay is never shorter than SII actually requires.
        return 30 * 1024;
    }

    /**
     * Checks if the XML response contains a rejected status code.
     */
    protected function isRejectedStatus(string $xml): bool
    {
        return Str::contains($xml, [
            '<ESTADO>RSC</ESTADO>',
            '<ESTADO>RCT</ESTADO>',
            '<ESTADO>REC</ESTADO>',
            '<ESTADO>RCH</ESTADO>',
            '<ESTADO>RPR</ESTADO>',
            '<ESTADO>RFR</ESTADO>',
        ]);
    }

    /**
     * Checks if the XML response contains a processing status code.
     */
    protected function isProcessingStatus(string $xml): bool
    {
        return Str::contains($xml, [
            '<ESTADO>PRD</ESTADO>',
            '<ESTADO>SOK</ESTADO>',
            '<ESTADO>CRT</ESTADO>',
        ]);
    }

    /**
     * Updates the DTE contained in the envelope.
     */
    protected function updateEnvelopeDtes(DateFactory $date, Dispatcher $event): void
    {
        $now = $date->now();

        // Preload the DTEs so the guard below uses pre-update statuses.
        $this->envelope->loadMissing('dtes');

        // Only issue a single `UPDATE` to the database.
        $this->envelope->dtes()->whereIn('status', DteStatus::nonTerminalValues())->update([
            'status' => DteStatus::Accepted->value,
            'accepted_at' => $now,
            'updated_at' => $now,
        ]);

        // Force update and sync each model and dispatch the event.
        foreach ($this->envelope->dtes as $dte) {
            if ($dte->status->isTerminalState()) {
                continue;
            }

            $dte->forceFill([
                'status' => DteStatus::Accepted,
                'accepted_at' => $now,
                'updated_at' => $now,
            ]);

            $dte->syncOriginal();

            $event->dispatch(new DteAccepted($dte));
        }
    }
}
