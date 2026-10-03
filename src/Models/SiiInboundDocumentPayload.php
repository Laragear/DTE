<?php

namespace Laragear\Dte\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Laragear\Dte\Database\Factories\SiiInboundDocumentPayloadFactory;
use Laragear\Dte\Models\Concerns\HasXmlPayload;

/**
 * Stores the raw XML received for an inbound DTE.
 * ---
 * @see  SiiInboundDocumentPayloadFactory
 * @link database/migrations/2026_01_01_000008_create_sii_inbound_document_payloads_table.php
 * ---
 * @mixin Builder<static>
 * ---
 * @method static SiiInboundDocumentPayloadFactory factory(callable|array|int|null $count = null, callable|array $state = [])
 * @method Builder<static>|static newQuery()
 * @method static Builder<static>|static query()
 * ---
 * @property-read int $id
 * ---
 * @property string|null $xml
 * ---
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
#[UseFactory(SiiInboundDocumentPayloadFactory::class)]
#[Fillable('xml')]
class SiiInboundDocumentPayload extends Model
{
    /** @use HasFactory<SiiInboundDocumentPayloadFactory> */
    use HasFactory;

    use HasXmlPayload;

    /*
     |--------------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------------
     */

    public SiiInboundDocument $inboundDocument {
        get => $this->getRelationValue(__PROPERTY__);
    }

    /**
     * Return the inbound document owning this payload.
     *
     * @return BelongsTo<SiiInboundDocument, static>
     */
    public function inboundDocument(): BelongsTo
    {
        return $this->belongsTo(SiiInboundDocument::class, 'sii_inbound_document_id');
    }
}
