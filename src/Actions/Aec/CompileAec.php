<?php

namespace Laragear\Dte\Actions\Aec;

use DateTimeImmutable;
use Illuminate\Pipeline\Pipeline;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Dte\Data\CessionData;
use Laragear\Dte\Models\SiiDte;
use Laragear\Rut\Rut;

/**
 * @method AecData thenReturn()
 */
class CompileAec extends Pipeline
{
    /**
     * The array of class pipes.
     *
     * @var class-string[]
     */
    protected $pipes = [
        Pipes\ValidateDte::class,
        Pipes\PrepareAecContext::class,
        Pipes\BuildAecXml::class,
        Pipes\SignAec::class,
    ];

    /**
     * Build and sign an Archivo Electrónico de Cesión.
     */
    public function build(
        SiiDte $dte,
        CessionData $cession,
        string $receiptXml,
        Rut|string $authorizedSigner,
        string $authorizedName,
        string $cedentEmail,
        DigitalCertificate $certificate,
        ?DateTimeImmutable $signedAt = null,
    ): string {
        $data = new AecData(
            $dte,
            $cession,
            $receiptXml,
            $cedentEmail,
            $authorizedName,
            $certificate,
            Rut::parse($authorizedSigner),
            $signedAt,
        );

        return $this->send($data)->thenReturn()->signedXml;
    }
}
