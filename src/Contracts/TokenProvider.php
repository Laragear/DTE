<?php

namespace Laragear\Dte\Contracts;

use Laragear\Dte\Data\Token;
use Laragear\Rut\Rut;

interface TokenProvider
{
    /**
     * Get a valid authentication token for the given taxpayer.
     */
    public function token(Rut $issuer, ?string $baseUrl = null): Token;

    /**
     * Execute a callback, retrying with a fresh token on rejection.
     *
     * @param  callable(): mixed  $request
     */
    public function retryWithFreshToken(callable $request, Rut $issuer): mixed;
}
