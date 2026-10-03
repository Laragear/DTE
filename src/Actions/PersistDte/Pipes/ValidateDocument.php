<?php

namespace Laragear\Dte\Actions\PersistDte\Pipes;

use Closure;
use Laragear\Dte\Actions\PersistDte\DteData;

class ValidateDocument
{
    /**
     * Handle the incoming DTE Data.
     *
     * @param  Closure(DteData):DteData  $next
     */
    public function handle(DteData $data, Closure $next): DteData
    {
        $data->builder->validate();

        return $next($data);
    }
}
