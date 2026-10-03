<?php

namespace Laragear\Dte\Gateways;

use Laragear\Dte\Data\Token;
use Laragear\Dte\Proxies\SoapProxy;
use SoapClient;
use SoapHeader;

class SoapClientFactory
{
    /**
     * Create a new Soap Client Factory instance.
     */
    public function __construct(
        protected SoapProxy $soapProxy,
    ) {
        //
    }

    /**
     * Build a SOAP client for the given WSDL with the authentication token header set.
     */
    public function createAuthenticatedClient(string $wsdl, Token $token): SoapClient
    {
        $client = $this->soapProxy
            ->withWsdl($wsdl)
            ->build();

        $client->__setSoapHeaders(new SoapHeader(
            'http://www.sii.cl/ws/',
            'Token',
            $token->value,
        ));

        return $client;
    }
}
