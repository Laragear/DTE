<?php

namespace Laragear\Dte\Support;

use DateTimeInterface;

/** @internal */
class Timestamp
{
    /**
     * Format a date for SII XML elements (ISO 8601 without timezone).
     */
    public static function formatSii(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d\TH:i:s');
    }
}
