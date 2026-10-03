<?php

namespace Laragear\Dte\Models\Concerns;

use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Models\SiiDte;
use Psr\Log\LoggerInterface;
use Throwable;
use function app;
use function array_merge;

trait HasDocumentReplication
{
    /**
     * Cloned fields exempt from the original document.
     *
     * @var list<string>
     */
    protected const array EXCEPT_FROM_REPLICATION = [
        'sii_caf_id',
        'sii_dte_envelope_id',
        'folio',
        'status',
        'repairs',
        'pack_retries',
        'acknowledged_at',
        'accepted_at',
        'rejected_at',
    ];

    /**
     * Clone this document into an editable draft for retry.
     *
     * @param  list<string>  $except  Additional fields to exclude from replication.
     */
    public function replicateForRetry(array $except = []): static
    {
        $clone = $this->replicate(array_merge($except, self::EXCEPT_FROM_REPLICATION));

        return $this->finalizeClonedDraft($clone);
    }

    /**
     * Set clone status to Draft and persist payload if loaded.
     */
    protected function finalizeClonedDraft(SiiDte $clone): static
    {
        // Single transaction of the replication flow: the clone row and its
        // payload row land together, never orphaned.
        try {
            return $clone->getConnection()->transaction(function () use ($clone): static {
                $clone->status = DteStatus::Draft;

                $clone->save();

                $this->hydratePayloadOntoClone($clone);

                return $clone;
            });
        } catch (Throwable $e) {
            app(LoggerInterface::class)->error('DTE replication failed, rolling back the cloned draft.', [
                'flow' => 'dte-replication',
                'original_id' => $this->getKey(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Replicate the original's payload onto the cloned document.
     */
    protected function hydratePayloadOntoClone(SiiDte $clone): void
    {
        if ($this->relationLoaded('payload') || $this->payload) {
            $payloadClone = $this->payload->replicate(['sii_dte_id']);

            $clone->payload()->save($payloadClone);
        }
    }
}
