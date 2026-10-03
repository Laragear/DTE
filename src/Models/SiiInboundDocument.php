<?php

namespace Laragear\Dte\Models;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Dte\Database\Factories\SiiInboundDocumentFactory;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\InboundDteStatus;
use Laragear\Dte\Models\Concerns\HasDocumentType;
use Laragear\Dte\Models\Concerns\HasSiiStatus;
use Laragear\Dte\Services\DteClaimService;
use Laragear\Rut\Eloquent\RutAttribute;
use Laragear\Rut\Rut;

/**
 * Stores the queryable header and validation lifecycle of an inbound DTE.
 * ---
 * @see  SiiInboundDocumentFactory
 * @link database/migrations/2026_01_01_000007_create_sii_inbound_documents_table.php
 * ---
 * @mixin Builder<static>
 * ---
 * @method static SiiInboundDocumentFactory factory(callable|array|int|null $count = null, callable|array $state = [])
 * @method Builder<static>|static newQuery()
 * @method static Builder<static>|static query()
 * ---
 * @property-read int $id
 * ---
 * @property Rut $issuer_rut
 * @property Rut $receiver_rut
 * @property DteType $document_type
 * @property int $folio
 * @property Carbon $issued_on
 * @property int $amount_total
 * @property InboundDteStatus $status
 * @property string|null $claim_status
 * @property Carbon|null $received_at
 * @property Carbon|null $validated_at
 * @property Carbon|null $claimed_at
 * ---
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
#[UseFactory(SiiInboundDocumentFactory::class)]
#[Fillable(
    'issuer_rut',
    'receiver_rut',
    'document_type',
    'folio',
    'issued_on',
    'amount_total',
    'status',
    'claim_status',
)]
class SiiInboundDocument extends Model
{
    /** @use HasFactory<SiiInboundDocumentFactory> */
    use HasDocumentType;

    use HasFactory;
    use HasSiiStatus;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string|class-string>
     */
    protected $casts = [
        'document_type' => DteType::class,
        'issued_on' => 'date',
        'status' => InboundDteStatus::class,
        'received_at' => 'datetime',
        'validated_at' => 'datetime',
        'claimed_at' => 'datetime',
    ];

    /*
     |--------------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------------
     */

    public ?SiiInterchangeLog $interchangeLog {
        get => $this->getRelationValue(__PROPERTY__);
    }

    /**
     * Return the interchange event that received this document.
     *
     * @return BelongsTo<SiiInterchangeLog, static>
     */
    public function interchangeLog(): BelongsTo
    {
        return $this->belongsTo(SiiInterchangeLog::class, 'sii_interchange_log_id');
    }

    public ?SiiInboundDocumentPayload $payload {
        get => $this->getRelationValue(__PROPERTY__);
    }

    /**
     * Return the raw XML payload for this inbound document.
     *
     * @return HasOne<SiiInboundDocumentPayload, static>
     */
    public function payload(): HasOne
    {
        return $this->hasOne(SiiInboundDocumentPayload::class);
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
     * Access/Mutate the "receiver_rut" attribute.
     */
    protected function receiverRut(): Attribute
    {
        return RutAttribute::for('receiver');
    }

    /*
     |--------------------------------------------------------------------------
     | Claims & Actions
     |--------------------------------------------------------------------------
     */

    /**
     * Commercially accept this vendor invoice (Ley 19.983).
     */
    public function accept(
        Rut $signer,
        string $location,
        DigitalCertificate $certificate,
        ?DateTimeImmutable $signedAt = null,
    ): string {
        return app(DteClaimService::class)->accept($this, $signer, $location, $certificate, $signedAt);
    }

    /**
     * Reject this vendor invoice commercially (Reclamo al Contenido).
     */
    public function reject(string $reason = ''): void
    {
        app(DteClaimService::class)->reject($this, $reason);
    }

    /**
     * Reject this vendor invoice for missing goods (Falta Total).
     */
    public function rejectGoods(string $reason = ''): void
    {
        app(DteClaimService::class)->rejectGoods($this, $reason);
    }

    /**
     * Reject this vendor invoice due to partially missing goods (Falta Parcial).
     */
    public function rejectPartial(string $reason = ''): void
    {
        app(DteClaimService::class)->rejectPartial($this, $reason);
    }

    /**
     * Confirm receipt of goods or services (Acuse de Recibo de Mercaderías).
     */
    public function confirmGoodsReceipt(string $reason = ''): void
    {
        app(DteClaimService::class)->confirmGoodsReceipt($this, $reason);
    }
}
