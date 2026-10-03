<?php

namespace Laragear\Dte\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use Laragear\Dte\Certificate\CertificateResolver;

/**
 * @method static CertificateResolver resolveUsing(Closure $callback)
 *
 * @see CertificateResolver
 */
class Certificate extends Facade
{
    /**
     * @inheritDoc
     */
    protected static function getFacadeAccessor(): string
    {
        return CertificateResolver::class;
    }
}
