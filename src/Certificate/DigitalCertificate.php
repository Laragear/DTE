<?php

namespace Laragear\Dte\Certificate;

use SensitiveParameter;

readonly class DigitalCertificate
{
    /**
     * Create a Digital Certificate instance.
     */
    public function __construct(
        #[SensitiveParameter] public string $pkcs12,
        #[SensitiveParameter] public string $password,
    ) {
        //
    }
}
