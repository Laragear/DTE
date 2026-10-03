<?php

namespace Laragear\Dte\Actions\Aec;

use DateTimeImmutable;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Dte\Data\CessionData;
use Laragear\Dte\Models\SiiDte;
use Laragear\Rut\Rut;

class AecData
{
    /**
     * Create a new Aec Data instance.
     */
    public function __construct(
        public readonly SiiDte $dte,
        public readonly CessionData $cession,
        public readonly string $receiptXml,
        public readonly string $cedentEmail,
        public readonly string $authorizedName,
        public readonly DigitalCertificate $certificate,
        public Rut $authorizedSigner,
        public ?DateTimeImmutable $signedAt = null,
        public string $aecID = '',
        public string $dtecID = '',
        public string $cessionID = '',
        public string $xmlString = '',
        public string $signedXml = '',
    ) {
        //
    }
}
