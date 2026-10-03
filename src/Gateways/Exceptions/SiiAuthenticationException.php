<?php

namespace Laragear\Dte\Gateways\Exceptions;

use Laragear\Dte\Contracts\Exceptions\SiiException;
use RuntimeException;

/**
 * Thrown when SII rejects the digital certificate or the RUT is not enrolled
 * to exchange SOAP tokens (GetTokenFromSeed response code -3).
 *
 * This requires operator intervention: the certificate must be renewed and/or
 * the RUT must be enrolled in the SII electronic invoicing system.
 */
class SiiAuthenticationException extends RuntimeException implements SiiException
{
    //
}
