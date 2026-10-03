<?php

namespace Laragear\Dte\Gateways;

use function in_array;

class TokenStatus
{
    /**
     * SII status codes that indicate an inactive, invalid or expired token.
     *
     * The authenticator should refresh the token and retry when it encounters these.
     */
    public const array INVALID_CODES = ['001', '002', '003'];

    /**
     * Determine whether the given SII status code signals a valid token.
     */
    public static function isValid(int|string|null $status): bool
    {
        return $status === null || !in_array((string) $status, self::INVALID_CODES, true);
    }

    /**
     * Determine whether the given SII status code signals an invalid token.
     */
    public static function isNotValid(int|string|null $status): bool
    {
        return !static::isValid($status);
    }
}
