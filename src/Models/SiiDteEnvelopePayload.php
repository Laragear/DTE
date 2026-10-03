<?php

namespace Laragear\Dte\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Laragear\Dte\Database\Factories\SiiDteEnvelopePayloadFactory;
use Laragear\Dte\Models\Concerns\HasXmlPayload;

/**
 * Stores the signed XML for an outbound DTE envelope.
 * ---
 * @see  SiiDteEnvelopePayloadFactory
 * @link database/migrations/2026_01_01_000005_create_sii_dte_envelope_payloads_table.php
 * ---
 * @mixin Builder<static>
 * ---
 * @method static SiiDteEnvelopePayloadFactory factory(callable|array|int|null $count = null, callable|array $state = [])
 * @method Builder<static>|static newQuery()
 * @method static Builder<static>|static query()
 * ---
 * @property-read int $id
 * ---
 * @property string $xml
 * ---
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 * ---
 * @method Builder<static> whereHasRepairs()
 * @method Builder<static> whereDoesntHaveRepairs()
 */
#[UseFactory(SiiDteEnvelopePayloadFactory::class)]
#[Fillable('xml')]
class SiiDteEnvelopePayload extends Model
{
    /** @use HasFactory<SiiDteEnvelopePayloadFactory> */
    use HasFactory;

    use HasXmlPayload;

    /*
     |--------------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------------
     */

    public SiiDteEnvelope $envelope {
        get => $this->getRelationValue(__PROPERTY__);
    }

    /**
     * Return the envelope owning this payload.
     *
     * @return BelongsTo<SiiDteEnvelope, static>
     */
    public function envelope(): BelongsTo
    {
        return $this->belongsTo(SiiDteEnvelope::class, 'sii_dte_envelope_id');
    }

    /*
     |--------------------------------------------------------------------------
     | Local scopes
     |--------------------------------------------------------------------------
     */

    /**
     * Scope a query to only include models that have SII repairs/responses.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereHasRepairs(Builder $query): Builder
    {
        return $query->whereNotNull('sii_response');
    }

    /**
     * Scope a query to only include models that do not have SII repairs/responses.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereDoesntHaveRepairs(Builder $query): Builder
    {
        return $query->whereNull('sii_response');
    }
}
