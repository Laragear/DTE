<?php

namespace Laragear\Dte\Proxies;

use SoapClient;
use const WSDL_CACHE_DISK;

/** @internal */
class SoapProxy
{
    /**
     * Create a new SOAP Proxy instance.
     *
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        protected ?string $wsdl = null,
        protected array $options = [],
    ) {
        //
    }

    /**
     * Set the WSDL used to build the SOAP client.
     */
    public function withWsdl(?string $wsdl): static
    {
        $this->wsdl = $wsdl;

        return $this;
    }

    /**
     * Build the configured SOAP client.
     *
     * @see https://www.sii.cl/factura_electronica/factura_mercado/instructivo_emision.pdf
     */
    public function build(): SoapClient
    {
        $options = array_merge($this->options, [
            'soap_version' => SOAP_1_1,
            'trace' => 1,
            'exceptions' => true,
            // SII WSDL files can be very huge, so we will cache these on disk when available.
            'cache_wsdl' => WSDL_CACHE_DISK,
            // Enforce TLS 1.2+ as required by SII specification and all SOAP connections.
            'stream_context' => stream_context_create([
                'ssl' => [
                    'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]),
        ]);

        return new SoapClient($this->wsdl, $options);
    }
}
