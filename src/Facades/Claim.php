<?php

namespace Laragear\Dte\Facades;

use DateTimeImmutable;
use Illuminate\Support\Facades\Facade;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Dte\Models\SiiInboundDocument;
use Laragear\Dte\Services\DteClaimService;
use Laragear\Rut\Rut;

/**
 * @method static string accept(SiiInboundDocument $document, Rut $signer, string $location, DigitalCertificate $certificate, ?DateTimeImmutable $signedAt = null)
 * @method static void reject(SiiInboundDocument $document, string $reason = '')
 * @method static void rejectGoods(SiiInboundDocument $document, string $reason = '')
 * @method static void rejectPartial(SiiInboundDocument $document, string $reason = '')
 * @method static void confirmGoodsReceipt(SiiInboundDocument $document, string $reason = '')
 *
 * @see DteClaimService
 */
class Claim extends Facade
{
    /**
     * @inheritDoc
     */
    protected static function getFacadeAccessor(): string
    {
        return DteClaimService::class;
    }
}
