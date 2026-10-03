<?php

namespace Laragear\Dte\Models;

use Closure;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsFluent;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use Laragear\Dte\Builders\AecCessionBuilder;
use Laragear\Dte\Caf\Exceptions\CafNotFoundException;
use Laragear\Dte\Database\Factories\SiiDteFactory;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\Concerns\HasDocumentType;
use Laragear\Dte\Models\Concerns\HasSiiStatus;
use Laragear\Dte\Pdf\PdfBuilder;
use Laragear\Dte\Services\DteLifecycleService;
use Laragear\Rut\Eloquent\RutAttribute;
use Laragear\Rut\Rut;
use LogicException;
use function app;
use function config;
use function filled;

/**
 * Stores the queryable header and lifecycle of an emitted DTE.
 * ---
 *
 * @see  SiiDteFactory
 * @link database/migrations/2026_01_01_000002_create_sii_dtes_table.php
 * ---
 *
 * @mixin Builder<static>
 * ---
 *
 * @method static SiiDteFactory<static> factory(callable|array|int|null $count = null, callable|array $state = [])
 * @method Builder<static>|static newQuery()
 * @method static Builder<static>|static query()
 *                                               ---
 *
 * @property-read int $id
 * ---
 * @property Rut $issuer_rut
 * @property Rut $receiver_rut
 * @property DteType $document_type
 * @property int|null $folio
 * @property Fluent|null $metadata
 * @property int $pack_retries
 * @property Carbon|null $issued_on
 * @property int $amount_net
 * @property int $amount_exempt
 * @property int $amount_taxes
 * @property int $amount_total
 * @property DteStatus $status
 * @property array<string, int>|null $taxes
 * @property bool $iva_common_use
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $rejected_at
 *                                    ---
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 * ---
 *
 * @method Builder<static>|Builder whereHasRepairs()
 * @method Builder<static>|Builder whereDoesntHaveRepairs()
 * @method static static annulFolio(string $reason = '')
 */
#[UseFactory(SiiDteFactory::class)]
#[Fillable(
    'issuer_rut',
    'receiver_rut',
    'document_type',
    'folio',
    'pack_retries',
    'metadata',
    'issued_on',
    'amount_net',
    'amount_exempt',
    'amount_taxes',
    'taxes',
    'iva_common_use',
    'amount_total',
    'status',
    'repairs',
)]
class SiiDte extends Model
{
    use Concerns\HasCorrections;
    use Concerns\HasDocumentReplication;
    use Concerns\HasRetries;
    use HasDocumentType;

    /** @use HasFactory<SiiDteFactory> */
    use HasFactory;

    use HasSiiStatus;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'pack_retries' => 'integer',
        'document_type' => DteType::class,
        'metadata' => AsFluent::class,
        'issued_on' => 'date',
        'status' => DteStatus::class,
        'repairs' => 'array',
        'taxes' => 'array',
        'iva_common_use' => 'boolean',
        'acknowledged_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /*
     |--------------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------------
     */

    public ?SiiCaf $caf {
        get => $this->getRelationValue(__PROPERTY__);
    }

    /**
     * @return BelongsTo<SiiCaf, static>
     */
    public function caf(): BelongsTo
    {
        return $this->belongsTo(SiiCaf::class, 'sii_caf_id');
    }

    public ?SiiDteEnvelope $envelope {
        get => $this->getRelationValue(__PROPERTY__);
    }

    /**
     * @return BelongsTo<SiiDteEnvelope, static>
     */
    public function envelope(): BelongsTo
    {
        return $this->belongsTo(SiiDteEnvelope::class, 'sii_dte_envelope_id');
    }

    public ?SiiDtePayload $payload {
        get => $this->getRelationValue(__PROPERTY__);
    }

    /**
     * @return HasOne<SiiDtePayload, static>
     */
    public function payload(): HasOne
    {
        return $this->hasOne(SiiDtePayload::class);
    }

    /** @var EloquentCollection<int, SiiAecCession> */
    public EloquentCollection $aecCessions {
        get => $this->getRelationValue(__PROPERTY__);
    }

    /**
     * @return HasMany<SiiAecCession, static>
     */
    public function aecCessions(): HasMany
    {
        return $this->hasMany(SiiAecCession::class);
    }

    /** @var EloquentCollection<int, SiiDteReference> */
    public EloquentCollection $references {
        get => $this->getRelationValue(__PROPERTY__);
    }

    /**
     * @return HasMany<SiiDteReference, static>
     */
    public function references(): HasMany
    {
        return $this->hasMany(SiiDteReference::class, 'sii_dte_id');
    }

    /** @var EloquentCollection<int, SiiDteReference> */
    public EloquentCollection $referencedBy {
        get => $this->getRelationValue(__PROPERTY__);
    }

    /**
     * @return HasMany<SiiDteReference, static>
     */
    public function referencedBy(): HasMany
    {
        return $this->hasMany(SiiDteReference::class, 'target_dte_id');
    }

    /**
     * Pending or accepted annulment reference blocking new annulments.
     */
    public function hasConflictingAnnulmentCreditNote(): ?SiiDteReference
    {
        /** @var Builder<SiiDteReference> $query */
        $query = $this->referencedBy()->newQuery();

        return $query
            ->where('reference_code', 1)
            ->whereHas('dte', static function (Builder $query): void {
                $query->creditNotes()->whereIn('status', DteStatus::annulmentConflictingValues());
            })
            ->first();
    }

    /*
     |--------------------------------------------------------------------------
     | Local scopes
     |--------------------------------------------------------------------------
     */

    /**
     * Only records carrying SII repairs.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereHasRepairs(Builder $query): Builder
    {
        return $query->whereNotNull('repairs');
    }

    /**
     * Only records without SII repairs.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereDoesntHaveRepairs(Builder $query): Builder
    {
        return $query->whereNull('repairs');
    }

    /*
     |--------------------------------------------------------------------------
     | Attributes
     |--------------------------------------------------------------------------
     */

    /**
     * Map the issuer columns into a Rut value object.
     */
    protected function issuerRut(): Attribute
    {
        return RutAttribute::for('issuer');
    }

    /**
     * Map the receiver columns into a Rut value object.
     */
    protected function receiverRut(): Attribute
    {
        return RutAttribute::for('receiver');
    }

    /**
     * Read-only item lines sourced from the stored payload.
     */
    protected function items(): Attribute
    {
        return Attribute::get(function (): Collection {
            $this->loadMissing('payload');

            return $this->payload?->data->items ?? collect();
        });
    }

    /*
     |--------------------------------------------------------------------------
     | Helpers
     |--------------------------------------------------------------------------
     */

    /**
     * Whether this DTE is locked against edits and deletion.
     */
    public function isReadOnly(): bool
    {
        // Only drafts can be modified or deleted. Once the DTE leaves the
        // draft state, a worker may pick it up at any moment, so it locks.
        return $this->status !== DteStatus::Draft;
    }

    /**
     * Whether this DTE still accepts edits and deletion.
     */
    public function isNotReadOnly(): bool
    {
        return !$this->isReadOnly();
    }

    /**
     * Whether the SII accepted this document with attached repairs.
     */
    public function isAcceptedWithRepairs(): bool
    {
        return $this->status === DteStatus::Accepted
            && filled($this->repairs);
    }

    /**
     * Whether the SII accepted this document cleanly.
     */
    public function isNotAcceptedWithRepairs(): bool
    {
        return !$this->isAcceptedWithRepairs();
    }

    /*
     |--------------------------------------------------------------------------
     | Retryability
     |--------------------------------------------------------------------------
     */

    /**
     * Whether this DTE admits any retry path.
     */
    public function isRetryable(): bool
    {
        return $this->isRetryableWithSameFolio() || $this->isReplicable();
    }

    /**
     * Whether every retry path is exhausted for this DTE.
     */
    public function isNotRetryable(): bool
    {
        return !$this->isRetryable();
    }

    /**
     * Whether the SII has not consumed this folio yet.
     */
    public function isRetryableWithSameFolio(): bool
    {
        return $this->status->isRetryableWithSameFolio();
    }

    /**
     * Whether the SII already consumed this folio.
     */
    public function isNotRetryableWithSameFolio(): bool
    {
        return !$this->isRetryableWithSameFolio();
    }

    /**
     * Whether the SII consumed this folio and a new one is needed.
     */
    public function isReplicable(): bool
    {
        return $this->status === DteStatus::Rejected;
    }

    /**
     * Whether this folio remains usable for retries.
     */
    public function isNotReplicable(): bool
    {
        return !$this->isReplicable();
    }

    /**
     * Remaining envelope pack attempts, null outside packable states.
     */
    public function remainingPackRetries(): ?int
    {
        if ($this->status->isNotRetryable()) {
            return null;
        }

        return max(0, config('dte.envelopes.max_retries', 3) - $this->pack_retries);
    }

    /*
     |--------------------------------------------------------------------------
     | Lifecycle
     |--------------------------------------------------------------------------
     */

    /**
     * Pack this document into an exclusive envelope for SII submission.
     *
     * @param  (Closure(static $dte, SiiDteEnvelope $envelope): mixed)|mixed  $sync
     */
    public function send(mixed $sync = false): SiiDteEnvelope
    {
        return app(DteLifecycleService::class)->send($this, $sync);
    }

    /**
     * Send this document synchronously in an exclusive envelope.
     */
    public function sendSync(): SiiDteEnvelope
    {
        return $this->send(sync: true);
    }

    /*
     |--------------------------------------------------------------------------
     | AEC Cession
     |--------------------------------------------------------------------------
     */

    /**
     * Start a credit cession flow for this document.
     */
    public function cede(): AecCessionBuilder
    {
        return app(AecCessionBuilder::class)->forDte($this);
    }

    /*
     |--------------------------------------------------------------------------
     | PDF Generation
     |--------------------------------------------------------------------------
     */

    /**
     * Start the PDF generation flow for this document.
     */
    public function pdf(): PdfBuilder
    {
        return app(PdfBuilder::class)->forDte($this);
    }

    /*
     |--------------------------------------------------------------------------
     | Folio annulment
     |--------------------------------------------------------------------------
     */

    /**
     * Annul this document folio through its CAF.
     */
    public function annulFolio(string $reason = ''): static
    {
        // Folios below the CAF pointer skip the allocation guard.
        if ($this->folio === null) {
            throw new LogicException('The DTE has no folio to annul.');
        }

        if ($this->caf === null) {
            throw new CafNotFoundException("No CAF is set for the document [$this->getKey()].");
        }

        $this->caf->annulFolios([$this->folio], $reason, validateAllocated: false);

        return $this;
    }

    /**
     * Whether this document folio was annulled.
     */
    public function isFolioAnnuled(): bool
    {
        if ($this->caf === null || $this->folio === null) {
            return false;
        }

        return $this->caf->folios->isAnnuled($this->folio);
    }
}
