<?php

namespace Laragear\Dte\Facades;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Services\PackDtesService;

/**
 * Manually pack compiled DTEs into envelopes, bypassing batch thresholds.
 *
 * @method static Collection<int, SiiDteEnvelope> packManual(array|Collection|EloquentBuilder $dtes, closure|mixed $sync = false, bool $retryFailed = false)
 * @method static Collection<int, SiiDteEnvelope> packManualSync(array|Collection|EloquentBuilder $dtes, bool $retryFailed = false)
 * @method static int pack()
 *
 * @see PackDtesService
 */
class SiiEnvelope extends Facade
{
    /**
     * @inheritDoc
     */
    protected static function getFacadeAccessor(): string
    {
        return PackDtesService::class;
    }
}
