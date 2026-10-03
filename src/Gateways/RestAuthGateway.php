<?php

namespace Laragear\Dte\Gateways;

use Illuminate\Http\Client\Factory as Http;
use Laragear\Dte\Contracts\CertificateResolverInterface;
use Laragear\Dte\Environment\EnvironmentResolver;
use Laragear\Dte\Support\XmlDomFactory;
use Laragear\Rut\Rut;
use RuntimeException;

class RestAuthGateway
{
    use Concerns\SupportsTls;

    // The endpoint path for fetching a seed from SII.
    protected const string SEED_ENDPOINT = '/boleta.electronica.semilla';

    // The endpoint path for exchanging a signed seed for a token.
    protected const string TOKEN_ENDPOINT = '/boleta.electronica.token';

    // The body content type for the token exchange request.
    protected const string BODY_TYPE = 'application/xml';

    /**
     * Create a new REST Auth Gateway instance.
     */
    public function __construct(
        protected Http $http,
        protected EnvironmentResolver $environment,
        protected CertificateResolverInterface $certificates,
        protected SignedTokenRequestBuilder $signedTokenRequestBuilder,
        protected XmlDomFactory $xml,
    ) {
        //
    }

    /**
     * Perform the raw SII REST authentication flow and return the token string.
     */
    public function fetchToken(Rut $issuerRut, ?string $authUrl = null): string
    {
        $authUrl ??= $this->environment->resolve()->restBaseUrl();

        if ($authUrl === null) {
            return 'fake-token';
        }

        // Fetch and validate the seed from SII.
        $seed = $this->fetchSeed($authUrl);

        // Build the signed XML payload and resolve the certificate.
        $signedXml = $this->buildAndValidateSignature($issuerRut, $seed);

        // Exchange the signed seed for an authentication token.
        return $this->exchangeSeedForToken($authUrl, $signedXml);
    }

    /**
     * Fetch a seed from the SII REST API and validate it.
     */
    protected function fetchSeed(string $authUrl): string
    {
        $seedResponse = $this->http
            ->withOptions($this->tlsOptions())
            ->get($authUrl.static::SEED_ENDPOINT);

        if ($seedResponse->failed()) {
            throw new RuntimeException('Failed to get seed from SII.');
        }

        $seed = $this->parseSeedFromResponse($seedResponse->body());

        if (!$seed) {
            throw new RuntimeException('Invalid seed response from SII.');
        }

        return $seed;
    }

    /**
     * Extract the SEMILLA value from a seed response XML body.
     */
    protected function parseSeedFromResponse(string $xml): ?string
    {
        $seedDocument = $this->xml->document();
        $seedDocument->loadXML($xml);

        return $seedDocument->getElementsByTagName('SEMILLA')->item(0)?->nodeValue;
    }

    /**
     * Build the signed XML payload by resolve the certificate for signing.
     */
    protected function buildAndValidateSignature(Rut $issuerRut, string $seed): string
    {
        $certificate = $this->certificates->resolve($issuerRut);

        if (!$certificate) {
            throw new RuntimeException('No digital certificate resolved for issuer '.$issuerRut->formatBasic());
        }

        return $this->signedTokenRequestBuilder->build($seed, $certificate);
    }

    /**
     * Exchange a signed seed XML for an authentication token with SII.
     */
    protected function exchangeSeedForToken(string $authUrl, string $signedXml): string
    {
        $tokenResponse = $this->http
            ->withBody($signedXml, static::BODY_TYPE)
            ->withOptions($this->tlsOptions())
            ->post($authUrl.static::TOKEN_ENDPOINT);

        if ($tokenResponse->failed()) {
            throw new RuntimeException('Failed to get token from SII.');
        }

        return $this->parseTokenFromResponse($tokenResponse->body())
            ?: throw new RuntimeException('Invalid token response from SII.');
    }

    /**
     * Extract the TOKEN value from a token response XML body.
     */
    protected function parseTokenFromResponse(string $xml): ?string
    {
        $tokenDocument = $this->xml->document();
        $tokenDocument->loadXML($xml);

        return $tokenDocument->getElementsByTagName('TOKEN')->item(0)?->nodeValue;
    }
}
