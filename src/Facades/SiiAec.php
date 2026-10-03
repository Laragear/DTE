<?php

namespace Laragear\Dte\Facades;

use Illuminate\Support\Facades\Facade;
use Laragear\Dte\Actions\Aec\CompileAec;

/**
 * @see CompileAec
 */
class SiiAec extends Facade
{
    /**
     * @inheritDoc
     */
    protected static function getFacadeAccessor(): string
    {
        return CompileAec::class;
    }
}
