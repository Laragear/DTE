<?php

namespace Laragear\Dte\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\ReferenceType;

/**
 * Stores a reference from one DTE to another (or to an external document).
 * ---
 * @link database/migrations/2026_01_01_000010_create_sii_dte_references_table.php
 * ---
 * @mixin Builder<static>
 * ---
 * @method Builder<static>|static newQuery()
 * @method static Builder<static>|static query()
 * ---
 * @property-read int $id
 * ---
 * @property int $sii_dte_id
 * @property int|null $target_dte_id
 * @property DteType|ReferenceType|null $document_type
 * @property string|null $folio
 * @property Carbon|null $date
 * @property string|null $reason
 * @property int|null $reference_code
 * ---
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
#[Fillable(
    'sii_dte_id',
    'target_dte_id',
    'document_type',
    'folio',
    'date',
    'reason',
    'reference_code',
)]
class SiiDteReference extends Model
{
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string|class-string>
     */
    protected $casts = [
        'date' => 'date',
        'reference_code' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Attributes
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve the document type to the correct enum.
     */
    protected function documentType(): Attribute
    {
        // Numeric values resolve to DteType first (they're DTE document types),
        // falling back to ReferenceType for 801-823, 48, 888, etc.
        // Alphabetic values always resolve to ReferenceType.
        return Attribute::get(function (?string $value): DteType|ReferenceType|null {
            if ($value === null) {
                return null;
            }

            if (is_numeric($value)) {
                return DteType::tryFrom((int) $value) ?? ReferenceType::from($value);
            }

            return ReferenceType::from($value);
        });
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
     * Return the DTE that owns this reference.
     *
     * @return BelongsTo<SiiDte, static>
     */
    public function dte(): BelongsTo
    {
        return $this->belongsTo(SiiDte::class, 'sii_dte_id');
    }

    public SiiDte $targetDte {
        get => $this->getRelationValue(__PROPERTY__);
    }

    /**
     * Return the DTE that this reference points to.
     *
     * @return BelongsTo<SiiDte, static>
     */
    public function targetDte(): BelongsTo
    {
        return $this->belongsTo(SiiDte::class, 'target_dte_id');
    }
}
