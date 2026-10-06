<?php

namespace Laragear\Dte\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Support\Collection;
use Illuminate\Support\DateFactory;
use Laragear\Dte\Actions\CompileDte\Compile;
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\EnvelopeStatus;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Services\Exceptions\PackClaimException;
use Laragear\Rut\Rut;
use LogicException;
use Psr\Log\LoggerInterface;
use Throwable;

use function value;

class PackDtesService
{
    /**
     * Create a new Pack DTEs Service instance.
     */
    public function __construct(
        protected LoggerInterface $log,
        protected Kernel $artisan,
        protected Repository $config,
        protected DateFactory $date,
        protected ConfigurationManager $configManager,
    ) {
        //
    }

    /**
     * Manually pack the given documents into envelopes, bypassing batch thresholds.
     *
     * @param  array<int>|Collection<int>|EloquentBuilder<SiiDte>  $dtes
     * @param  (Closure(SiiDteEnvelope $envelope): mixed)|mixed  $sync
     * @return Collection<int, SiiDteEnvelope>
     */
    public function packManual(
        array|Collection|EloquentBuilder|SiiDte $dtes,
        mixed $sync = false,
    ): Collection {
        // Accepts DTE IDs, a Collection of IDs or models, or an Eloquent builder. Documents
        // are fresh-loaded, compiled inline when needed, validated, auto-split by issuer
        // and receipt family (mixed types within a family share one envelope), chunked
        // by the configured limits, and dispatched queued or sync.
        $ids = $this->resolveManualIds($dtes);

        if ($ids === []) {
            return collect();
        }

        $models = $this->fetchManualDtes($ids);

        $this->ensureManualDtesExist($ids, $models);
        $this->prepareManualDtesForPack($models);
        $this->ensureManualDtesPackable($models);

        $envelopes = collect();
        $delayCounter = 0;

        foreach ($this->groupByIssuerAndReceiver($models) as $group) {
            foreach ($group->chunk($this->maximumForGroup($group)) as $chunk) {
                /** @var EloquentCollection<int, SiiDte> $chunk */
                $chunk = new EloquentCollection($chunk->all());

                // Runs one transaction per envelope chunk, never nested: do not wrap this
                // method in an outer transaction. A concurrent pack or send racing this
                // call aborts its chunk with a PackClaimException instead of overwriting.
                $envelope = $this->createEnvelope($chunk);

                $envelopes->push($this->dispatchManual($envelope, $sync, $delayCounter++));
            }
        }

        return $envelopes;
    }

    /**
     * Manually pack the given documents and process the envelopes synchronously.
     *
     * @param  array<int>|Collection<int>|EloquentBuilder<SiiDte>  $dtes
     * @return Collection<int, SiiDteEnvelope>
     */
    public function packManualSync(array|Collection|EloquentBuilder $dtes): Collection
    {
        return $this->packManual($dtes, true);
    }

    /**
     * Resolve the manual input into a deduplicated list of DTE IDs.
     *
     * @param  array<int>|Collection<int>|EloquentBuilder<SiiDte>  $dtes
     * @return list<int>
     */
    protected function resolveManualIds(array|Collection|EloquentBuilder|SiiDte $dtes): array
    {
        if ($dtes instanceof EloquentBuilder) {
            $dtes = $dtes->pluck($dtes->getModel()->getKeyName());
        }

        return EloquentCollection::wrap($dtes)
            ->map(static fn ($item): int => (int) ($item instanceof SiiDte ? $item->getKey() : $item))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Fresh-load the requested DTEs with the columns needed for grouping.
     *
     * @param  list<int>  $ids
     * @return EloquentCollection<int, SiiDte>
     */
    protected function fetchManualDtes(array $ids): EloquentCollection
    {
        return SiiDte::query()
            ->with('payload')
            ->whereIn('id', $ids)
            ->get([
                'id',
                'issuer_num',
                'issuer_vd',
                'status',
                'sii_dte_envelope_id',
                'sii_caf_id',
                'folio',
                'pack_retries',
                'acknowledged_at',
                'document_type',
                'updated_at',
            ]);
    }

    /**
     * Ensure every requested ID resolved to a row.
     *
     * @param  list<int>  $ids
     * @param  EloquentCollection<int, SiiDte>  $models
     */
    protected function ensureManualDtesExist(array $ids, EloquentCollection $models): void
    {
        if ($models->count() === count($ids)) {
            return;
        }

        $missing = collect($ids)->diff($models->modelKeys())->values()->all();

        throw new LogicException(
            'The DTEs ['.implode(', ', $missing).'] do not exist and cannot be packed.'
        );
    }

    /**
     * Finalize and compile DTEs until every document is ready to pack.
     *
     * @param  EloquentCollection<int, SiiDte>  $models
     */
    protected function prepareManualDtesForPack(EloquentCollection $models): void
    {
        // Transforms run inline and outside any transaction: Drafts compile
        // synchronously and Pending recompiles, so the later claim only ever
        // sees compiled documents.
        $this->rejectUnpackableDtes($models);

        foreach ($models as $dte) {
            $this->compileDraftForPack($dte);
        }

        foreach ($models as $dte) {
            $this->compileUncompiledForPack($dte->refresh());
        }
    }

    /**
     * Reject documents that can never be packed manually.
     *
     * @param  EloquentCollection<int, SiiDte>  $models
     */
    protected function rejectUnpackableDtes(EloquentCollection $models): void
    {
        $invalid = $models
            ->filter($this->isUnpackable(...))
            ->map($this->describeUnpackable(...))
            ->values()
            ->all();

        if ($invalid !== []) {
            throw new LogicException(
                'Only draft, pending, signed or outbox DTEs without an envelope can be packed manually. Invalid: '.implode(', ',
                    $invalid).'.'
            );
        }
    }

    /**
     * Whether the DTE is linked, uncompilable, sent or terminal.
     */
    protected function isUnpackable(SiiDte $dte): bool
    {
        if ($dte->getAttribute('sii_dte_envelope_id') !== null) {
            return true;
        }

        return $dte->status->isNotManuallyPackable();
    }

    /**
     * Describe why a DTE cannot be packed manually.
     */
    protected function describeUnpackable(SiiDte $dte): string
    {
        $label = "[{$dte->getKey()}] is [{$dte->status->value}]";

        if ($dte->getAttribute('sii_dte_envelope_id') !== null) {
            return $label." in envelope [{$dte->getAttribute('sii_dte_envelope_id')}]";
        }

        if ($dte->status === DteStatus::Rejected) {
            return $label.' (clone it with replicateForRetry() first)';
        }

        return $label;
    }

    /**
     * Resolve the lifecycle lazily to avoid a circular dependency.
     */
    protected function dteLifecycle(): DteLifecycleService
    {
        return app(DteLifecycleService::class);
    }

    /**
     * Compile a draft DTE synchronously.
     */
    protected function compileDraftForPack(SiiDte $dte): void
    {
        if ($dte->status !== DteStatus::Draft) {
            return;
        }

        $this->dteLifecycle()->compile($dte->refresh(), true);
    }

    /**
     * Compile a pending or failed DTE, requiring a compiled result.
     */
    protected function compileUncompiledForPack(SiiDte $dte): void
    {
        if ($dte->status->isNotCompilableForPack()) {
            return;
        }

        app(Compile::class)->forDte($dte);

        $status = $dte->refresh()->status;

        if ($status->isNotAwaitingEnvelope()) {
            throw new LogicException(
                "The DTE [{$dte->getKey()}] could not be compiled before packing (is [{$status->value}])."
            );
        }
    }

    /**
     * Ensure every DTE is compiled and unlinked before packing.
     *
     * @param  EloquentCollection<int, SiiDte>  $models
     */
    protected function ensureManualDtesPackable(EloquentCollection $models): void
    {
        $invalid = $models
            ->map(fn (SiiDte $dte): SiiDte => $dte->refresh())
            ->filter(static fn (SiiDte $dte): bool => $dte->getAttribute('sii_dte_envelope_id') !== null
                || $dte->status->isNotAwaitingEnvelope())
            ->map(static fn (SiiDte $dte): string => "[{$dte->getKey()}] is [{$dte->status->value}]"
                .($dte->getAttribute('sii_dte_envelope_id') !== null
                    ? " in envelope [{$dte->getAttribute('sii_dte_envelope_id')}]"
                    : ''))
            ->values()
            ->all();

        if ($invalid !== []) {
            throw new LogicException(
                'Only compiled DTEs without an envelope can be packed. Invalid: '.implode(', ', $invalid).'.'
            );
        }
    }

    /**
     * Maximum documents for a homogeneous receipt-family group.
     *
     * @param  Collection<int, SiiDte>  $group
     */
    protected function maximumForGroup(Collection $group): int
    {
        $first = $group->first();

        if ($first->document_type->isReceipt()) {
            return $this->config->get('dte.envelopes.max.receipts', 50);
        }

        return $this->config->get('dte.envelopes.max.documents', 20);
    }

    /**
     * Dispatch a manually created envelope synchronously or queued.
     *
     * @param  (Closure(SiiDteEnvelope $envelope): mixed)|mixed  $sync
     */
    protected function dispatchManual(SiiDteEnvelope $envelope, mixed $sync, int $delayCounter): SiiDteEnvelope
    {
        if (value($sync, $envelope)) {
            $this->artisan->call('dte:process-envelope', ['envelope_id' => $envelope->getKey()]);

            return $envelope->fresh()->loadMissing('dtes');
        }

        $this->dispatchEnvelope($envelope, $delayCounter);

        return $envelope;
    }

    /**
     * Group ready DTEs into envelopes and dispatch them. Returns envelope count.
     */
    public function pack(): int
    {
        $dtes = $this->fetchReadyDtes();

        if ($dtes->isEmpty()) {
            return 0;
        }

        $envelopesCreated = 0;
        $delayCounter = 0;
        $maxHoldingMinutes = $this->config->get('dte.envelopes.max_holding_minutes', 30);

        foreach ($this->groupByIssuerAndReceiver($dtes) as $group) {
            $oldest = $group->first();
            $maximum = $this->maximumForGroup($group);

            $holdingMinutes = $this->date->now()->diffInMinutes($oldest->updated_at);

            if ($group->count() >= $maximum || $holdingMinutes >= $maxHoldingMinutes) {
                foreach ($group->chunk($maximum) as $chunk) {
                    $envelope = $this->createEnvelope($chunk);
                    $this->dispatchEnvelope($envelope, $delayCounter++);
                    $envelopesCreated++;
                }
            }
        }

        return $envelopesCreated;
    }

    /**
     * Fetch the DTEs that are compiled but without an envelope.
     *
     * @return EloquentCollection<int, SiiDte>
     */
    protected function fetchReadyDtes(): EloquentCollection
    {
        // The payloads are not eager-loaded here: each row would drag its signed
        // XML from disk, and the envelope creation loads the one it needs itself.
        return SiiDte::query()
            ->whereIn('status', DteStatus::awaitingEnvelopeValues())
            ->whereNull('sii_dte_envelope_id')
            ->oldest('updated_at')
            ->get([
                'id',
                'issuer_num',
                'issuer_vd',
                'status',
                'sii_dte_envelope_id',
                'document_type',
                'updated_at',
            ]);
    }

    /**
     * Returns a Collection of DTE grouped by issuer and receipt family.
     *
     * @param  EloquentCollection<int, SiiDte>  $dtes
     * @return Collection<string, Collection<int, SiiDte>>
     */
    protected function groupByIssuerAndReceiver(EloquentCollection $dtes): Collection
    {
        // Mixed types within the same family share one envelope: the SII EnvioDTE
        // schema allows up to 20 SubTotDTE entries and EnvioBOLETA allows both
        // 39 and 41. Receipts (boletas) never mix with other DTE types.
        return $dtes->groupBy(static function (SiiDte $dte): string {
            $family = $dte->document_type->isReceipt() ? 'boleta' : 'dte';

            return $dte->issuer_rut->num.'-'.$family;
        });
    }

    /**
     * Creates a DTE envelope on the database.
     *
     * @param  EloquentCollection<int, SiiDte>  $dtes
     */
    protected function createEnvelope(EloquentCollection $dtes): SiiDteEnvelope
    {
        // Single transaction of the batch-pack flow. The bulk update is a
        // conditional claim: only still-unlinked, still-compiled DTEs are
        // linked, so a concurrent exclusive send racing this batch is never
        // overwritten.
        $first = $dtes->first()->load('payload:id,sii_dte_id,header_issuer');

        try {
            return SiiDte::query()->getConnection()->transaction(function () use ($dtes, $first): SiiDteEnvelope {
                $envelope = $this->createEnvelopeInDatabase(
                    $first->issuer_rut, $first->document_type->isReceipt(), $first->payload->header_issuer->toArray()
                );

                // We will update the envelope of each DTE on one query instead of saving each.
                $linked = SiiDte::query()
                    ->whereIn('id', $dtes->modelKeys())
                    ->whereNull('sii_dte_envelope_id')
                    ->whereIn('status', DteStatus::awaitingEnvelopeValues())
                    ->update([
                        'sii_dte_envelope_id' => $envelope->getKey(),
                        'status' => DteStatus::Packed,
                    ]);

                if ($linked !== $dtes->count()) {
                    throw new PackClaimException(
                        "Only [$linked] of [{$dtes->count()}] DTEs could be linked to envelope [{$envelope->getKey()}]."
                    );
                }

                return $envelope;
            });
        } catch (Throwable $e) {
            $this->log->error('Batch envelope pack failed, rolling back the batch linkage.', [
                'flow' => 'batch-pack',
                'dte_ids' => $dtes->modelKeys(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Creates an exclusive DTE envelope on the database for a single document.
     */
    public function createExclusiveEnvelope(SiiDte $dte): SiiDteEnvelope
    {
        // The DTE is not attached here so the caller can attach it atomically
        // within its own transaction.
        $dte->loadMissing('payload:id,sii_dte_id,header_issuer');

        return $this->createEnvelopeInDatabase(
            $dte->issuer_rut, $dte->document_type->isReceipt(), $dte->payload?->header_issuer->toArray() ?? []
        );
    }

    /**
     * Create a new SII DTE Envelope to be sent.
     */
    protected function createEnvelopeInDatabase(Rut $issuer, bool $isReceipt, array $issuerData): SiiDteEnvelope
    {
        // If the issuer data doesn't have resolution date or number, we resort to use
        // the issuer itself. If that fails, when creating the envelope, the database
        // will scream because there will be no resolution to save into that DB row.
        if (! isset($issuerData['resolution_date']) || ! isset($issuerData['resolution_number'])) {
            $dynamicIssuer = $this->configManager->getIssuer($issuer);

            $issuerData = [
                'resolution_date' => $dynamicIssuer->resolutionDate,
                'resolution_number' => $dynamicIssuer->resolutionNumber,
            ];
        }

        return SiiDteEnvelope::create([
            'issuer_rut' => $issuer,
            'sender_rut' => $this->configManager->getSender($issuer),
            'type' => $isReceipt ? 'boleta' : 'dte',
            'resolution_date' => $issuerData['resolution_date'],
            'resolution_number' => $issuerData['resolution_number'],
            'status' => EnvelopeStatus::Pending,
        ]);
    }

    /**
     * Dispatches the job that compiles and sends the envelope.
     */
    protected function dispatchEnvelope(SiiDteEnvelope $envelope, int $delayCounter): void
    {
        $backoffSeconds = $this->config->get('dte.envelopes.backoff_seconds', 60);
        $delay = min($this->config->get('dte.envelopes.max_backoff'), $delayCounter * $backoffSeconds);

        $this->artisan
            ->queue('dte:process-envelope', ['envelope_id' => $envelope])
            ->onConnection($this->config->get('dte.queue.envelope.connection'))
            ->onQueue($this->config->get('dte.queue.envelope.name'))
            ->when($delay > 0, function (PendingDispatch $job) use ($delay) {
                return $job->delay($this->date->now()->addSeconds($delay));
            });
    }
}
