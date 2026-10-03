<?php

namespace Laragear\Dte\Models\Concerns;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Laragear\Dte\Enums\AecStatus;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\EnvelopeStatus;
use Laragear\Dte\Enums\InboundDteStatus;
use LogicException;
use function value;

/**
 * @method Builder<static>|static whereStatus(BackedEnum|string $status)
 * @method Builder<static>|static pending()
 * @method Builder<static>|static accepted()
 */
trait HasSiiStatus
{
    /*
     |--------------------------------------------------------------------------
     | Local Scopes
     |--------------------------------------------------------------------------
     */

    /**
     * Local scope to filter records by a SII status.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function scopeWhereStatus(Builder $query, BackedEnum|string $status): Builder
    {
        return $query->where('status', $status instanceof BackedEnum ? $status->value : $status);
    }

    /**
     * Local scope to filter records in the pending state.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function scopePending(Builder $query): Builder
    {
        return $this->scopeWhereStatus($query, 'pending');
    }

    /**
     * Local scope to filter records in the accepted state.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function scopeAccepted(Builder $query): Builder
    {
        return $this->scopeWhereStatus($query, 'accepted');
    }

    /*
     |--------------------------------------------------------------------------
     | SII status
     |--------------------------------------------------------------------------
     */

    /**
     * Transition the model into another state from the same status family.
     */
    public function transitionTo(
        DteStatus|EnvelopeStatus|InboundDteStatus|AecStatus $status,
        mixed $save = true,
    ): static {
        $current = $this->currentSiiStatus();

        $this->guardStatusTransition($current, $status);
        $this->forceFill(['status' => $status]);

        if (value($save, $this)) {
            $this->save();
        }

        return $this;
    }

    /**
     * Guard a transition from an incompatible or terminal status.
     */
    protected function guardStatusTransition(
        DteStatus|EnvelopeStatus|InboundDteStatus|AecStatus $current,
        DteStatus|EnvelopeStatus|InboundDteStatus|AecStatus $next,
    ): void {
        if ($current::class !== $next::class) {
            throw new LogicException('The next status must belong to the same SII status enum.');
        }

        if ($current !== $next && $current->isTerminalState()) {
            throw new LogicException('A terminal SII status cannot transition to another state.');
        }
    }

    /**
     * Return the model SII status.
     */
    protected function currentSiiStatus(): DteStatus|EnvelopeStatus|InboundDteStatus|AecStatus
    {
        $status = $this->getAttribute('status');

        if (
            !$status instanceof DteStatus
            && !$status instanceof EnvelopeStatus
            && !$status instanceof InboundDteStatus
            && !$status instanceof AecStatus
        ) {
            throw new LogicException('The model status must be cast to a supported SII status enum.');
        }

        return $status;
    }
}
