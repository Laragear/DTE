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
use Laragear\Dte\Caf\Exceptions\FolioAlreadyAllocatedException;
use Laragear\Dte\Caf\Exceptions\FolioAlreadyAnnuledException;
use Laragear\Dte\Caf\Exceptions\FolioOutOfRangeException;
use Laragear\Dte\Caf\Folio;
use Laragear\Dte\Casts\AsFolio;
use Laragear\Dte\Database\Factories\SiiCafFactory;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Events\CafDepleted;
use Laragear\Dte\Events\CafFoliosAnnuled;
use Laragear\Dte\Events\CafFoliosRestored;
use Laragear\Dte\Models\Concerns\HasDocumentType;
use Laragear\Rut\HasRut;
use Laragear\Rut\Rut;
use Psr\Log\LoggerInterface;
use Throwable;
use function app;
use function value;

/**
 * Stores an authorized SII folio range and its CAF XML.
 * ---
 * @see  SiiCafFactory
 * @link database/migrations/2026_01_01_000001_create_sii_cafs_table.php
 * ---
 * @mixin Builder<static>
 * ---
 * @method static SiiCafFactory factory(callable|array|int|null $count = null, callable|array $state = [])
 * @method Builder<static>|static newQuery()
 * @method Builder<static>|static query()
 * @method static Builder<static>|static collidesWith(Rut|string $rut, DteType|int $documentType, int $folioFrom, int $folioTo)
 * @method static static annulFolios(array<int|array{int, int}> $folios, string $reason = '', bool $validateAllocated = true)
 * @method static static restoreFolios(array<int|array{int, int}> $folios)
 * ---
 * @property-read int $id
 * ---
 * @property-read Rut $rut
 * ---
 * @property DteType $document_type
 * @property int $folio_from
 * @property int $folio_to
 * @property int $folio_current
 * @property array|null $folio_annuled
 * @property Carbon $authorized_on
 * @property Carbon|null $expires_on
 * @property string $xml
 * @property Folio $folios
 * @property Carbon|null $depleted_at
 * ---
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 * ---
 * @method Builder<static>|static collidesWith(Rut|string $rut, DteType|int $documentType, int $folioFrom, int $folioTo)
 * @method Builder<static>|static whereDepleted()
 * @method Builder<static>|static whereNotDepleted()
 */
#[UseFactory(SiiCafFactory::class)]
#[Fillable(
    'rut',
    'document_type',
    'folio_from',
    'folio_to',
    'folio_current',
    'folio_annuled',
    'authorized_on',
    'expires_on',
    'xml',
)]
class SiiCaf extends Model
{
    /** @use HasFactory<SiiCafFactory> */
    use HasDocumentType;

    use HasFactory;
    use HasRut;

    public const string RUT_NUM = 'issuer_num';

    public const string RUT_VD = 'issuer_vd';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string|class-string>
     */
    protected $casts = [
        'document_type' => DteType::class,
        'authorized_on' => 'date',
        'expires_on' => 'date',
        'folios' => AsFolio::class,
        'folio_annuled' => 'array',
        'depleted_at' => 'datetime',
    ];

    /*
     |--------------------------------------------------------------------------
     | Scopes
     |--------------------------------------------------------------------------
     */

    /**
     * Scope the query to find CAFs that collide with the given properties.
     */
    public function scopeCollidesWith(
        Builder $query,
        Rut|string $rut,
        DteType|int $documentType,
        int $folioFrom,
        int $folioTo,
    ): Builder {
        return $query
            ->whereRut($rut)
            ->where('document_type', $documentType)
            ->where('folio_from', '<=', $folioTo)
            ->where('folio_to', '>=', $folioFrom);
    }

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
     * Return the documents allocated from this CAF.
     *
     * @return HasMany<SiiDte, static>
     */
    public function dtes(): HasMany
    {
        return $this->hasMany(SiiDte::class);
    }

    /*
     |--------------------------------------------------------------------------
     | Local Scopes
     |--------------------------------------------------------------------------
     */

    /**
     * Filter the CAF by those completely depleted as "whereDepleted".
     */
    protected function scopeWhereDepleted(Builder $builder): Builder
    {
        return $builder->whereNotNull('depleted_at');
    }

    /**
     * Filter the CAF by those not depleted as "whereNotDepleted".
     */
    protected function scopeWhereNotDepleted(Builder $builder): Builder
    {
        return $builder->whereNull('depleted_at');
    }

    /*
     |--------------------------------------------------------------------------
     | Attributes
     |--------------------------------------------------------------------------
     */

    /**
     * Access the `expired_at` attribute.
     */
    protected function expiredAt(): Attribute
    {
        return Attribute::get(static function (null $value, array $attributes): Carbon {
            return Carbon::parse($attributes['authorized_on'], 'America/Santiago')->addMonthsNoOverflow(6);
        });
    }

    /*
     |--------------------------------------------------------------------------
     | Helpers
     |--------------------------------------------------------------------------
     */

    /**
     * Marks the CAF as depleted.
     */
    public function markAsDepleted(mixed $save = true): void
    {
        $this->depleted_at = $this->freshTimestamp();

        value($save, $this) && $this->save();
    }

    /**
     * Marks the CAF as not depleted.
     */
    public function markAsNotDepleted(mixed $save = true): void
    {
        $this->depleted_at = null;

        value($save, $this) && $this->save();
    }

    /*
     |--------------------------------------------------------------------------
     | Folio annulment
     |--------------------------------------------------------------------------
     */

    /**
     * Annul one or more folios, or ranges of folios, inside a locked transaction.
     *
     * @param  array<int|array{int, int}>  $folios
     * @return $this
     */
    public function annulFolios(array $folios, string $reason = '', bool $validateAllocated = true): static
    {
        // Single transaction of the annul flow. Callers never wrap this method.
        $folios = Folio::normalize($folios);

        try {
            return $this->getConnection()->transaction(function () use ($folios, $validateAllocated): static {
                $this->refreshForUpdate();

                $this->validateFoliosForAnnulment($folios, $validateAllocated);

                $this->folios->annul(...$folios);

                if ($this->folios->isNotExhausted()) {
                    $this->markAsNotDepleted();
                } else {
                    $this->markAsDepleted();
                }

                $this->getConnection()->afterCommit(function () use ($folios): void {
                    CafFoliosAnnuled::dispatch($this, $folios);

                    CafDepleted::dispatchIf($this->folios->isExhausted(), $this->rut, $this->document_type);
                });

                return $this->syncFolios($this);
            });
        } catch (Throwable $e) {
            app(LoggerInterface::class)->error('CAF folio annulment failed, rolling back to the pre-annul state.', [
                'flow' => 'folio-annulment',
                'caf_id' => $this->getKey(),
                'folios' => $folios,
                'reason' => $reason,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function validateFoliosForAnnulment(array $folios, bool $validateAllocated): void
    {
        foreach ($folios as $folio) {
            if ($this->folios->isNotInRange($folio)) {
                throw new FolioOutOfRangeException("The folio [$folio] is out of the CAF range.");
            }

            if ($validateAllocated && $this->folios->isNotAllocatable($folio)) {
                throw new FolioAlreadyAllocatedException("The folio [$folio] was already allocated.");
            }

            if ($this->folios->isAnnuled($folio)) {
                throw new FolioAlreadyAnnuledException("The folio [$folio] was already annulled.");
            }
        }
    }

    /**
     * Restore one or more annulled folios locally, no SII report.
     *
     * @param  array<int|array{int, int}>  $folios
     * @return $this
     */
    public function restoreFolios(array $folios): static
    {
        // Single transaction of the restore flow. Callers never wrap this
        // method.
        try {
            return $this->getConnection()->transaction(function () use ($folios): static {
                $this->refreshForUpdate();

                $this->folios->restore(...$folios);

                $this->save();

                $this->getConnection()->afterCommit(function () use ($folios): void {
                    CafFoliosRestored::dispatch($this, $folios);
                });

                return $this->syncFolios($this);
            });
        } catch (Throwable $e) {
            app(LoggerInterface::class)->error('CAF folio restore failed, rolling back to the pre-restore state.', [
                'flow' => 'folio-restore',
                'caf_id' => $this->getKey(),
                'folios' => $folios,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Sync the in-memory folio state with the freshly locked and saved instance.
     */
    protected function syncFolios(SiiCaf $caf): static
    {
        $this->folios = $caf->folios;

        return $this;
    }
}
