<?php

namespace Laragear\Dte\Contracts;

use Laragear\Rut\Rut;

interface TenantResolverInterface
{
    /**
     * Resolve the application tenant that owns the taxpayer RUT.
     */
    public function resolve(Rut $rut): ?object;
}
