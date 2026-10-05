<?php

namespace Laragear\Dte\Facades;

use Illuminate\Support\Facades\Facade;
use Laragear\Dte\Models\SiiIecv as SiiIecvModel;
use Laragear\Dte\Services\IecvService;

/**
 * Build, sign and send electronic books (IECV) to the SII.
 *
 * The fiscal resolution is read from the issuer registration when omitted.
 *
 * @method static SiiIecvModel sendSales(\Laragear\Rut\Rut $issuer, \Illuminate\Support\Collection $dtes, string $period, ?string $resolutionDate = null, ?int $resolutionNumber = null, ?\Laragear\Rut\Rut $senderRut = null, array $properties = [])
 * @method static SiiIecvModel sendPurchases(\Laragear\Rut\Rut $issuer, array $entries, string $period, ?string $resolutionDate = null, ?int $resolutionNumber = null, ?\Laragear\Rut\Rut $senderRut = null, array $properties = [])
 *
 * @see IecvService
 */
class SiiIecv extends Facade
{
    /**
     * {@inheritDoc}
     */
    protected static function getFacadeAccessor(): string
    {
        return IecvService::class;
    }
}
