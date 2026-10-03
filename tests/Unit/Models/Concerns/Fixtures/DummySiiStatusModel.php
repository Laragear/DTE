<?php

namespace Tests\Unit\Models\Concerns\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Models\Concerns\HasSiiStatus;

/**
 * @property DteStatus $status
 *
 * @method static Builder<static>|static accepted()
 * @method static Builder<static>|static pending()
 * @method static Builder<static>|static whereStatus(DteStatus|string $status)
 */
class DummySiiStatusModel extends Model
{
    use HasSiiStatus;

    public bool $saved = false;

    protected $attributes = [
        'status' => 'pending',
    ];

    protected $casts = [
        'status' => DteStatus::class,
    ];

    /**
     * Record that the dummy model was saved.
     *
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        return $this->saved = true;
    }
}
