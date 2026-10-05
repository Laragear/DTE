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
use Illuminate\Support\Carbon;
use Laragear\Dte\Database\Factories\SiiIecvFactory;
use Laragear\Dte\Enums\IecvStatus;
use Laragear\Dte\Enums\IecvType;
use Laragear\Dte\Models\Concerns\HasSiiStatus;
use Laragear\Rut\Eloquent\RutAttribute;
use Laragear\Rut\HasRut;
use Laragear\Rut\Rut;

/**
 * Tracks an electronic sales or purchases book (IECV) and its SII submission lifecycle.
 * ---
 * @see  SiiIecvFactory
 * @link database/migrations/2026_01_01_000011_create_sii_iecvs_table.php
 * ---
 * @mixin Builder<static>
 * ---
 * @method static SiiIecvFactory factory(callable|array|int|null $count = null, callable|array $state = [])
 * @method Builder<static>|static newQuery()
 * @method static Builder<static>|static query()
 * ---
 * @property-read int $id
 * ---
 * @property Rut $issuer_rut
 * @property Rut $sender_rut
 * @property IecvType $type
 * @property string $period
 * @property Carbon $resolution_date
 * @property int $resolution_number
 * @property string|null $xml
 * @property string|null $track_id
 * @property IecvStatus $status
 * @property array|null $errors
 * @property Carbon|null $uploaded_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $poll_at
 * ---
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
#[UseFactory(SiiIecvFactory::class)]
#[Fillable(
    'issuer_rut',
    'sender_rut',
    'type',
    'period',
    'resolution_date',
    'resolution_number',
    'xml',
    'track_id',
    'status',
    'errors',
    'poll_at',
    'uploaded_at',
    'accepted_at',
    'rejected_at',
)]
class SiiIecv extends Model
{
    /** @use HasFactory<SiiIecvFactory> */
    use HasFactory;

    use HasRut;
    use HasSiiStatus;

    /**
     * The attributes that should be cast.
     *
     * @var array<array-key, mixed>
     */
    protected $casts = [
        'type' => IecvType::class,
        'resolution_date' => 'date',
        'resolution_number' => 'integer',
        'status' => IecvStatus::class,
        'errors' => 'array',
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

    /** @var EloquentCollection<int, SiiDte> */
    public EloquentCollection $dtes {
        get => $this->getRelationValue(__PROPERTY__);
    }

    /**
     * Return the documents contained by this book.
     *
     * @return HasMany<SiiDte, static>
     */
    public function dtes(): HasMany
    {
        return $this->hasMany(SiiDte::class);
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
}
