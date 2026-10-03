<?php

namespace Laragear\Dte\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Laragear\Dte\Database\Factories\SiiInterchangeLogFactory;

/**
 * Audits inbound and outbound DTE interchange events.
 * ---
 * @see  SiiInterchangeLogFactory
 * @link database/migrations/2026_01_01_000006_create_sii_interchange_logs_table.php
 * ---
 * @mixin Builder<static>
 * ---
 * @method static SiiInterchangeLogFactory factory(callable|array|int|null $count = null, callable|array $state = [])
 * @method Builder<static>|static newQuery()
 * @method static Builder<static>|static query()
 * ---
 * @property-read int $id
 * ---
 * @property string|null $message_id
 * @property string $direction
 * @property string $type
 * @property string $sender
 * @property string $recipient
 * @property string|null $subject
 * @property string|null $raw_email
 * @property string|null $response_xml
 * @property array<string, mixed>|null $data
 * @property Carbon|null $processed_at
 * ---
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
#[UseFactory(SiiInterchangeLogFactory::class)]
#[Fillable(
    'message_id',
    'direction',
    'type',
    'sender',
    'recipient',
    'subject',
    'raw_email',
    'response_xml',
    'data',
    'processed_at',
)]
class SiiInterchangeLog extends Model
{
    /** @use HasFactory<SiiInterchangeLogFactory> */
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string|class-string>
     */
    protected $casts = [
        'data' => 'array',
        'processed_at' => 'datetime',
    ];

    /*
     |--------------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------------
     */

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

    /** @var EloquentCollection<int, SiiInboundDocument> */
    public EloquentCollection $inboundDocuments {
        get => $this->getRelationValue(__PROPERTY__);
    }

    /**
     * @return HasMany<SiiInboundDocument, static>
     */
    public function inboundDocuments(): HasMany
    {
        return $this->hasMany(SiiInboundDocument::class);
    }
}
