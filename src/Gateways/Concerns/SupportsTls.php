<?php

namespace Laragear\Dte\Gateways\Concerns;

trait SupportsTls
{
    /**
     * Return HTTP client options that enforce TLS 1.2+ connections.
     */
    protected function tlsOptions(): array
    {
        return [
            'verify' => true,
            'curl' => [
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            ],
        ];
    }
}
