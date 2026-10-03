<?php

namespace Laragear\Dte\Support;

use SoapFault;
use Throwable;
use function retry;

/** @internal */
class SoapRetry
{
    /**
     * Backoff delays in milliseconds (1 s, 2 s, 3 s, 4 s, 5 s).
     *
     * @var list<int>
     */
    public const array BACKOFF = [1000, 2000, 3000, 4000, 5000];

    /**
     * Retry a SOAP call up to 5 times with progressive backoff on transport errors.
     */
    public static function call(callable $callback): mixed
    {
        // Only retries when a {@see SoapFault} is thrown (network timeouts, DNS
        // failures, HTTP 500 from SII, etc.). All other exceptions propagate
        // immediately without retry.
        return retry(self::BACKOFF, $callback, when: static fn(Throwable $e): bool => $e instanceof SoapFault);
    }
}
