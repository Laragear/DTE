<?php

namespace Laragear\Dte\Contracts;

use Laragear\Dte\Certificate\DigitalCertificate;

interface Certifiable
{
    /**
     * Get the digital certificate instance.
     */
    public function toDigitalCertificate(): DigitalCertificate;
}
