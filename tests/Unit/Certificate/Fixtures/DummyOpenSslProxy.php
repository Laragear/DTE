<?php

namespace Tests\Unit\Certificate\Fixtures;

use Laragear\Dte\Proxies\OpenSslProxy;

class DummyOpenSslProxy extends OpenSslProxy
{
    /**
     * Create a Dummy Open Ssl Proxy instance.
     *
     * @param  list<string>  $extraCertificates
     */
    public function __construct(
        protected int $validFrom,
        protected int $validUntil,
        protected array $extraCertificates = [],
    ) {
        //
    }

    /**
     * Return dummy PKCS#12 certificate contents.
     *
     * @return array{cert: string, pkey: string, extracerts: list<string>}
     */
    public function readPkcs12(string $path, string $password): array
    {
        return [
            'cert' => 'certificate',
            'pkey' => 'private-key',
            'extracerts' => $this->extraCertificates,
        ];
    }

    /**
     * Return dummy X.509 validity metadata.
     *
     * @return array{valid_from: int, valid_to: int}
     */
    public function parseX509(string $certificate): array
    {
        return [
            'valid_from' => $this->validFrom,
            'valid_to' => $this->validUntil,
        ];
    }
}
