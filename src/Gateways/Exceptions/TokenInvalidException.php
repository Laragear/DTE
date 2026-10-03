<?php

namespace Laragear\Dte\Gateways\Exceptions;

use Laragear\Dte\Contracts\Exceptions\SiiException;
use RuntimeException;

/**
 * Thrown when SII signals the current token is no longer valid
 * (SOAP states 001/002/003, HTTP 401 for REST uploads).
 *
 * Consumers throw this at their request boundary so the TokenAuthenticator's
 * retryWithFreshToken() / retryRestWithFreshToken() loops can refresh the
 * token and retry.
 */
class TokenInvalidException extends RuntimeException implements SiiException
{
    //
}
