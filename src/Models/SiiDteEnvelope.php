<?php

namespace Laragear\Dte\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Laragear\Dte\Database\Factories\SiiDteEnvelopeFactory;
use Laragear\Dte\Enums\EnvelopeStatus;
use Laragear\Dte\Models\Concerns\HasSiiStatus;
use Laragear\Rut\Eloquent\RutAttribute;
use Laragear\Rut\Rut;
use function filled;

/**
 * Tracks an outbound DTE envelope and its SII submission lifecycle.
 * ---
 * @see  SiiDteEnvelopeFactory
 * @link database/migrations/2026_01_01_000004_create_sii_dte_envelopes_table.php
 * ---
 * @mixin Builder<static>
 * ---
 * @method static SiiDteEnvelopeFactory factory(callable|array|int|null $count = null, callable|array $state = [])
 * @method Builder<static>|static newQuery()
 * @method static Builder<static>|static query()
 * @method null|static first(array|string $columns = ['*'])
 * ---
 * @property-read int $id
 * ---
 * @property Rut $issuer_rut
 * @property Rut $sender_rut
 * @property string $type
 * @property string|null $track_id
 * @property Carbon $resolution_date
 * @property int $resolution_number
 * @property EnvelopeStatus $status
 * @property Carbon|null $uploaded_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $poll_at
 * ---
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 * ---
 * @method Builder<static>|Builder whereHasRepairs()
 * @method Builder<static>|Builder whereDoesntHaveRepairs()
 */
#[UseFactory(SiiDteEnvelopeFactory::class)]
#[Fillable(
    'issuer_rut',
    'sender_rut',
    'type',
    'track_id',
    'resolution_date',
    'resolution_number',
    'status',
    'repairs',
    'poll_at',
    'uploaded_at',
)]
class SiiDteEnvelope extends Model
{
    /** @use HasFactory<SiiDteEnvelopeFactory> */
    use HasFactory;
    use HasSiiStatus;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string|class-string>
     */
    protected $casts = [
        'resolution_date' => 'date',
        'resolution_number' => 'integer',
        'status' => EnvelopeStatus::class,
        'repairs' => 'array',
        'uploaded_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'poll_at' => 'datetime',
    ];

    /*
     |--------------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------------
     */

    public ?SiiDteEnvelopePayload $payload {
        get => $this->getRelationValue(__PROPERTY__);
    }

    /**
     * Return the signed XML payload for this envelope.
     *
     * @return HasOne<SiiDteEnvelopePayload, static>
     */
    public function payload(): HasOne
    {
        return $this->hasOne(SiiDteEnvelopePayload::class);
    }

    /** @var EloquentCollection<int, SiiDte> */
    public EloquentCollection $dtes {
        get => $this->getRelationValue(__PROPERTY__);
    }

    /**
     * Return the documents contained by this envelope.
     *
     * @return HasMany<SiiDte, static>
     */
    public function dtes(): HasMany
    {
        return $this->hasMany(SiiDte::class);
    }

    /** @var EloquentCollection<int, SiiDtePayload> */
    public EloquentCollection $dtePayloads {
        get => $this->getRelationValue(__PROPERTY__);
    }

    /**
     * Return the document payloads contained by this envelope.
     *
     * @return HasManyThrough<SiiDtePayload, SiiDte, $this>
     */
    public function dtePayloads(): HasManyThrough
    {
        return $this->hasManyThrough(SiiDtePayload::class, SiiDte::class);
    }

    /** @var EloquentCollection<int, SiiInterchangeLog> */
    public EloquentCollection $interchangeLogs {
        get => $this->getRelationValue(__PROPERTY__);
    }

    /**
     * Return the interchange events associated with this envelope.
     *
     * @return HasMany<SiiInterchangeLog, static>
     */
    public function interchangeLogs(): HasMany
    {
        return $this->hasMany(SiiInterchangeLog::class);
    }

    /*
     |--------------------------------------------------------------------------
     | Local scopes
     |--------------------------------------------------------------------------
     */

    /**
     * Scope a query to only include models that have SII repairs.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereHasRepairs(Builder $query): Builder
    {
        return $query->whereNotNull('repairs');
    }

    /**
     * Scope a query to only include models that do not have SII repairs.
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
     * Access/Mutate the "issuer_rut" attribute.
     */
    protected function issuerRut(): Attribute
    {
        return RutAttribute::for('issuer');
    }

    /**
     * Access/Mutate the "sender_rut" attribute.
     */
    protected function senderRut(): Attribute
    {
        return RutAttribute::for('sender');
    }

    /*
     |--------------------------------------------------------------------------
     | Helpers
     |--------------------------------------------------------------------------
     */

    /**
     * Checks if the current envelope is a receipt.
     */
    public function isReceipt(): bool
    {
        return $this->attributes['type'] === 'boleta';
    }

    /**
     * Checks if the current envelope is not a receipt but any other DTE type.
     */
    public function isNotReceipt(): bool
    {
        return !$this->isReceipt();
    }

    /**
     * Check if the envelope was accepted but contains SII repairs/rejections.
     */
    public function isAcceptedWithRepairs(): bool
    {
        return $this->status === EnvelopeStatus::Accepted
            && filled($this->repairs);
    }

    /**
     * Check if the envelope was accepted without any repairs/rejections.
     */
    public function isNotAcceptedWithRepairs(): bool
    {
        return !$this->isAcceptedWithRepairs();
    }

    /*
     |--------------------------------------------------------------------------
     | Helper
     |--------------------------------------------------------------------------
     */

    /**
     * Creates an envelope for the given documents.
     */
    public function forDte(iterable $dte): static
    {
        return new static;
    }
}
