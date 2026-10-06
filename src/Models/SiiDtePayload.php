<?php

namespace Laragear\Dte\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Laragear\Dte\Casts\DteBlock;
use Laragear\Dte\Casts\DteCommissions;
use Laragear\Dte\Casts\DteDetailItems;
use Laragear\Dte\Casts\DteEmissionGeoref;
use Laragear\Dte\Casts\DteGlobalModifiers;
use Laragear\Dte\Casts\DteHeaderIdDoc;
use Laragear\Dte\Casts\DteHeaderIssuer;
use Laragear\Dte\Casts\DteHeaderOtherCurrency;
use Laragear\Dte\Casts\DteHeaderReceiver;
use Laragear\Dte\Casts\DteHeaderTotals;
use Laragear\Dte\Casts\DteHeaderTransport;
use Laragear\Dte\Casts\DteReferences;
use Laragear\Dte\Casts\DteSubtotals;
use Laragear\Dte\Casts\DteTimberHandling;
use Laragear\Dte\Database\Factories\SiiDtePayloadFactory;
use Laragear\Dte\Models\Concerns\HasXmlPayload;

/**
 * Stores builder input and signed XML outside the DTE ledger.
 *
 * Each column maps to one mother/child block of the SII Documento, hydrated
 * into its DteBlock castable: SiiDtePayload::make(['header_issuer' => [...]])
 * returns the model with a DteHeaderIssuer block for easy access.
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
 * @property DteHeaderIdDoc $header_id_doc
 * @property DteHeaderIssuer $header_issuer
 * @property DteHeaderReceiver $header_receiver
 * @property DteHeaderTransport $header_transport
 * @property DteHeaderTotals $header_totals
 * @property DteHeaderOtherCurrency $header_other_currency
 * @property DteDetailItems $detail_items
 * @property DteSubtotals $subtotals
 * @property DteGlobalModifiers $global_modifiers
 * @property DteReferences $references
 * @property DteCommissions $commissions
 * @property DteEmissionGeoref $emission_georef
 * @property DteTimberHandling $timber_handling
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
    'header_id_doc',
    'header_issuer',
    'header_receiver',
    'header_transport',
    'header_totals',
    'header_other_currency',
    'detail_items',
    'subtotals',
    'global_modifiers',
    'references',
    'commissions',
    'emission_georef',
    'timber_handling',
    'xml',
    'sii_response',
)]
class SiiDtePayload extends Model
{
    /** @use HasFactory<SiiDtePayloadFactory> */
    use HasFactory;

    use HasXmlPayload;

    /**
     * The payload block columns and their castable block classes.
     *
     * @var array<string, class-string<DteBlock>>
     */
    public const array BLOCKS = [
        'header_id_doc' => DteHeaderIdDoc::class,
        'header_issuer' => DteHeaderIssuer::class,
        'header_receiver' => DteHeaderReceiver::class,
        'header_transport' => DteHeaderTransport::class,
        'header_totals' => DteHeaderTotals::class,
        'header_other_currency' => DteHeaderOtherCurrency::class,
        'detail_items' => DteDetailItems::class,
        'subtotals' => DteSubtotals::class,
        'global_modifiers' => DteGlobalModifiers::class,
        'references' => DteReferences::class,
        'commissions' => DteCommissions::class,
        'emission_georef' => DteEmissionGeoref::class,
        'timber_handling' => DteTimberHandling::class,
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string|class-string>
     */
    protected $casts = self::BLOCKS;

    /*
     |--------------------------------------------------------------------------
     | Blocks
     |--------------------------------------------------------------------------
     */

    /**
     * Return every block as a column-keyed attribute array.
     *
     * @return array<string, array<string, mixed>>
     */
    public function blocksToArray(): array
    {
        $blocks = [];

        foreach (array_keys(self::BLOCKS) as $column) {
            $blocks[$column] = $this->{$column}->toArray();
        }

        return $blocks;
    }

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
