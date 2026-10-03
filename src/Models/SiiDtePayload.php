<?php

namespace Laragear\Dte\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Laragear\Dte\Casts\AsDocumentPayload;
use Laragear\Dte\Data\DocumentPayload;
use Laragear\Dte\Database\Factories\SiiDtePayloadFactory;
use Laragear\Dte\Models\Concerns\HasXmlPayload;

/**
 * Stores builder input and signed XML outside the DTE ledger.
 * ---
 * @see  SiiDtePayloadFactory
 * @link database/migrations/2026_01_01_000003_create_sii_dte_payloads_table.php
 * ---
 * @mixin Builder<static>
 * ---
 * @method static SiiDtePayloadFactory factory(callable|array|int|null $count = null, callable|array $state = [])
 * @method Builder<static>|static newQuery()
 * @method static Builder<static>|static query()
 * ---
 * @property-read int $id
 * ---
 * @property DocumentPayload $data
 * @property string|null $xml
 * ---
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 * ---
 * @method Builder<static> whereHasRepairs()
 * @method Builder<static> whereDoesntHaveRepairs()
 */
#[UseFactory(SiiDtePayloadFactory::class)]
#[Fillable(
    'data',
    'xml',
    'sii_response',
)]
class SiiDtePayload extends Model
{
    /** @use HasFactory<SiiDtePayloadFactory> */
    use HasFactory;

    use HasXmlPayload;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string|class-string>
     */
    protected $casts = [
        'data' => AsDocumentPayload::class,
    ];

    /*
     |--------------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------------
     */

    public SiiDte $dte {
        get => $this->getRelationValue(__PROPERTY__);
    }

    /**
     * Return the document owning this payload.
     *
     * @return BelongsTo<SiiDte, static>
     */
    public function dte(): BelongsTo
    {
        return $this->belongsTo(SiiDte::class, 'sii_dte_id');
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
