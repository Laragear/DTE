<?php

namespace Laragear\Dte\Contracts;

use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Rut\Rut;

interface CertificateResolverInterface
{
    /**
     * Resolve the digital certificate available for the taxpayer.
     */
    public function resolve(Rut $rut): ?DigitalCertificate;
}
