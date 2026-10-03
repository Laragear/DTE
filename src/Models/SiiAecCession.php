<?php

namespace Laragear\Dte\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Laragear\Dte\Database\Factories\SiiAecCessionFactory;
use Laragear\Dte\Enums\AecStatus;
use Laragear\Dte\Models\Concerns\HasSiiStatus;
use Laragear\Rut\HasRut;

/**
 * Tracks an electronic invoice-credit cession registered with the SII.
 * ---
 * @see  SiiAecCessionFactory
 * @link database/migrations/2026_01_01_000009_create_sii_aec_cessions_table.php
 * ---
 * @mixin Builder<static>
 * ---
 * @method static SiiAecCessionFactory factory(callable|array|int|null $count = null, callable|array $state = [])
 * @method Builder<static>|static newQuery()
 * @method static Builder<static>|static query()
 * ---
 * @property-read int $id
 * ---
 * @property int $cession_number
 * @property int $amount_total
 * @property Carbon $last_due_on
 * @property string|null $terms
 * @property array $data
 * @property string|null $xml
 * @property string|null $track_id
 * @property AecStatus $status
 * @property Carbon|null $submitted_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $rejected_at
 * ---
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
#[UseFactory(SiiAecCessionFactory::class)]
#[Fillable('cession_number', 'rut', 'amount_total', 'last_due_on', 'terms', 'data', 'xml', 'track_id', 'status')]
class SiiAecCession extends Model
{
    public const string RUT_NUM = 'assignee_num';
    public const string RUT_VD = 'assignee_vd';

    /** @use HasFactory<SiiAecCessionFactory> */
    use HasFactory;

    use HasRut;
    use HasSiiStatus;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string|class-string>
     */
    protected $casts = [
        'last_due_on' => 'date',
        'data' => 'array',
        'status' => AecStatus::class,
        'submitted_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
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
     * Return the document whose credit was transferred.
     *
     * @return BelongsTo<SiiDte, static>
     */
    public function dte(): BelongsTo
    {
        return $this->belongsTo(SiiDte::class, 'sii_dte_id');
    }
}
