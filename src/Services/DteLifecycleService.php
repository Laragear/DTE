<?php

namespace Laragear\Dte\Services;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Events\Dispatcher;
use Laragear\Dte\Actions\CompileDte\Compile;
use Laragear\Dte\Actions\PersistDte\PersistDte;
use Laragear\Dte\Builders\DocumentBuilder;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Events\DteCreated;
use Laragear\Dte\Events\DteCreating;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteEnvelope;
use LogicException;
use Psr\Log\LoggerInterface;
use Throwable;
use function value;

class DteLifecycleService
{
    /**
     * Create a new DTE Lifecycle Service instance.
     */
    public function __construct(
        protected ConsoleKernel $artisan,
        protected Repository $config,
        protected LoggerInterface $log,
        protected Dispatcher $events,
        protected PersistDte $persist,
        protected Compile $compile,
        protected PackDtesService $pack,
    ) {
        //
    }

    /**
     * Persist the builder input as draft or pending.
     */
    public function persist(DocumentBuilder $builder, bool $isUpdate): SiiDte
    {
        $this->ensureUpdateHasModel($builder, $isUpdate);
        $this->ensureDraftRespectsImmutability($builder, $isUpdate);

        $this->events->dispatch(new DteCreating($builder));

        $dte = $this->persist->handle($builder, $isUpdate);

        $this->events->dispatch(new DteCreated($dte));

        return $dte;
    }

    /**
     * Compile the document XML inline or queued.
     */
    public function compile(SiiDte $dte, bool $sync = false): SiiDte
    {
        $dte = $this->promoteDraftForCompilation($dte);

        if ($dte->status->isCompiled()) {
            return $dte;
        }

        return $sync ? $this->compileNow($dte) : $this->compileQueued($dte);
    }

    /**
     * Pack the document into an exclusive envelope and dispatch it.
     *
     * @param  (Closure(SiiDte $dte, SiiDteEnvelope $envelope): mixed)|mixed  $sync
     */
    public function send(SiiDte $dte, mixed $sync = false): SiiDteEnvelope
    {
        // Runs three sequential stages, never nested: inline compilation (its
        // own folio transaction), the exclusive-envelope transaction, then
        // envelope processing. Never wrap this method in an outer transaction.
        $dte->refresh()->loadMissing('payload');

        $this->ensureNotAlreadyInEnvelope($dte);
        $this->ensureNotTerminalState($dte);
        $this->ensureNotDraftState($dte);
        $this->compileIfNecessary($dte);

        $envelope = $this->createEnvelopeInTransaction($dte);

        return $this->dispatchEnvelope($envelope, $dte, $sync);
    }

    /**
     * Ensure an update targets a hydrated document.
     */
    protected function ensureUpdateHasModel(DocumentBuilder $builder, bool $isUpdate): void
    {
        if ($isUpdate && $builder->dte() === null) {
            throw new LogicException('Cannot build a document builder that has not been hydrated.');
        }
    }

    /**
     * Ensure a draft persist never unlocks an immutable document.
     */
    protected function ensureDraftRespectsImmutability(DocumentBuilder $builder, bool $isUpdate): void
    {
        $dte = $builder->dte();

        if ($isUpdate && $dte !== null && $this->persistsAsDraft($builder) && $dte->status !== DteStatus::Draft) {
            throw new LogicException(
                "Only a draft DTE can be persisted as draft. The DTE [{$dte->getKey()}] is [{$dte->status->value}]."
            );
        }
    }

    /**
     * Whether the builder input would persist as a draft.
     */
    protected function persistsAsDraft(DocumentBuilder $builder): bool
    {
        return $builder->isDrafting();
    }

    /**
     * Promote a draft into pending before compilation.
     */
    protected function promoteDraftForCompilation(SiiDte $dte): SiiDte
    {
        if ($dte->status === DteStatus::Draft) {
            $dte->transitionTo(DteStatus::Pending);
        }

        return $dte;
    }

    /**
     * Compile the document inline and return it refreshed.
     */
    protected function compileNow(SiiDte $dte): SiiDte
    {
        return $this->compile->forDte($dte->refresh());
    }

    /**
     * Queue the compilation command and return the document.
     */
    protected function compileQueued(SiiDte $dte): SiiDte
    {
        $this->artisan
            ->queue('dte:compile', ['dte_id' => $dte->getKey()])
            ->onConnection($this->config->get('dte.queue.dte.connection'))
            ->onQueue($this->config->get('dte.queue.dte.name'));

        return $dte;
    }

    /**
     * Verify the DTE does not already belong to an envelope.
     */
    protected function ensureNotAlreadyInEnvelope(SiiDte $dte): void
    {
        if ($dte->getAttribute('sii_dte_envelope_id') !== null) {
            throw new LogicException(
                "The DTE [{$dte->getKey()}] already belongs to envelope [{$dte->getAttribute('sii_dte_envelope_id')}]."
            );
        }
    }

    /**
     * Ensure the DTE is not in a terminal state.
     */
    protected function ensureNotTerminalState(SiiDte $dte): void
    {
        if ($dte->status->isTerminalState()) {
            throw new LogicException(
                "The DTE [{$dte->getKey()}] is in a terminal state [{$dte->status->value}] and cannot be sent."
                .($dte->status === DteStatus::Rejected ? ' Clone it with replicateForRetry() first.' : '')
            );
        }
    }

    /**
     * Ensure the DTE is not a draft awaiting build.
     */
    protected function ensureNotDraftState(SiiDte $dte): void
    {
        if ($dte->status === DteStatus::Draft) {
            throw new LogicException(
                "The DTE [{$dte->getKey()}] is a draft and cannot be sent. Build it with build() first."
            );
        }
    }

    /**
     * Compile the DTE inline when it lacks a signed XML payload.
     */
    protected function compileIfNecessary(SiiDte $dte): void
    {
        if ($dte->status->isCompiled()) {
            return;
        }

        $this->compile($dte->refresh(), true);

        if ($dte->refresh()->status->isNotCompiled()) {
            throw new LogicException("The DTE [{$dte->getKey()}] could not be compiled before sending.");
        }
    }

    /**
     * Create an exclusive envelope within a database transaction.
     */
    protected function createEnvelopeInTransaction(SiiDte $dte): SiiDteEnvelope
    {
        // Single transaction of the send flow. Locks the DTE row so concurrent
        // sends serialize instead of minting duplicate envelopes.
        try {
            return $dte->getConnection()->transaction(fn(): SiiDteEnvelope => $this->claimExclusiveEnvelope($dte));
        } catch (Throwable $e) {
            $this->log->error('Exclusive envelope creation failed, rolling back to the pre-send state.', [
                'flow' => 'dte-send',
                'dte_id' => $dte->getKey(),
                'status' => $dte->status->value,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Lock the row and attach a fresh exclusive envelope.
     */
    protected function claimExclusiveEnvelope(SiiDte $dte): SiiDteEnvelope
    {
        $dte->refreshForUpdate();

        if ($dte->getAttribute('sii_dte_envelope_id') !== null) {
            throw new LogicException(
                "The DTE [{$dte->getKey()}] already belongs to envelope [{$dte->getAttribute('sii_dte_envelope_id')}]."
            );
        }

        if ($dte->status->isNotCompiled()) {
            throw new LogicException("The DTE [{$dte->getKey()}] is not compiled and cannot be sent.");
        }

        $envelope = $this->pack->createExclusiveEnvelope($dte);
        $dte->envelope()->associate($envelope);
        $dte->transitionTo(DteStatus::Packed);
        $dte->setRelation('envelope', $envelope);

        return $envelope;
    }

    /**
     * Dispatch envelope processing either synchronously or queued.
     *
     * @param  (Closure(SiiDte $dte, SiiDteEnvelope $envelope): mixed)|mixed  $sync
     */
    protected function dispatchEnvelope(SiiDteEnvelope $envelope, SiiDte $dte, mixed $sync): SiiDteEnvelope
    {
        if (value($sync, $dte, $envelope)) {
            $this->artisan->call('dte:process-envelope', ['envelope_id' => $envelope->getKey()]);

            return $envelope->fresh()->loadMissing('dtes');
        }

        $this->artisan
            ->queue('dte:process-envelope', ['envelope_id' => $envelope->getKey()])
            ->onConnection($this->config->get('dte.queue.envelope.connection'))
            ->onQueue($this->config->get('dte.queue.envelope.name'));

        return $envelope;
    }
}
