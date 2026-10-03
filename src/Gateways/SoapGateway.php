<?php

namespace Laragear\Dte\Gateways;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Sleep;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Dte\Contracts\CertificateResolverInterface;
use Laragear\Dte\Data\Token;
use Laragear\Dte\Enums\SiiAuthState;
use Laragear\Dte\Environment\EnvironmentResolver;
use Laragear\Dte\Gateways\Exceptions\SiiAuthenticationException;
use Laragear\Dte\Gateways\Exceptions\SiiSeedUnavailableException;
use Laragear\Dte\Proxies\OpenSslProxy;
use Laragear\Dte\Proxies\SoapProxy;
use Laragear\Dte\Support\SoapRetry;
use Laragear\Dte\Support\XmlDomFactory;
use Laragear\Dte\Xml\XmlSigner;
use Laragear\Rut\Rut;
use RuntimeException;
use SoapClient;
use Throwable;
use function is_object;
use function sprintf;
use function trim;

class SoapGateway
{
    /**
     * The XML element carrying the response state in SII auth responses.
     */
    protected const string ESTADO_ELEMENT = 'ESTADO';

    /**
     * The key of the private key inside the PKCS#12 read result.
     */
    protected const string PEM_PRIVATE_KEY = 'pkey';

    /**
     * Progressive backoff (in seconds) used when SII asks to retry later.
     *
     * @var list<int>
     */
    protected const array RETRY_DELAYS = [0, 5, 10, 15];

    /**
     * Create a new SOAP Gateway instance.
     */
    public function __construct(
        protected Filesystem $file,
        protected CertificateResolverInterface $certificates,
        protected EnvironmentResolver $environment,
        protected SoapProxy $soapProxy,
        protected OpenSslProxy $openSsl,
        protected XmlDomFactory $xml,
        protected XmlSigner $xmlSigner,
        protected SoapClientFactory $soapClientFactory,
    ) {
        //
    }

    /**
     * Authenticate with SII to get a new token (CrSeed → GetTokenFromSeed).
     */
    public function authenticate(
        Rut $issuer,
        ?DigitalCertificate $certificate = null,
        ?string $baseUrl = null
    ): string {
        // This is the raw SII auth flow without any caching. The
        // TokenAuthenticator calls this method and is responsible for caching
        // the result.
        $baseUrl ??= $this->environment->resolve()->soapBaseUrl();

        if ($baseUrl === null) {
            throw new RuntimeException('Cannot authenticate because no SOAP Base URL is available in this environment.');
        }

        $certificate ??= $this->certificates->resolve($issuer);

        if ($certificate === null) {
            throw new RuntimeException('No digital certificate resolved for issuer '.$issuer->formatBasic());
        }

        $seed = SoapRetry::call(fn() => $this->fetchSeed($baseUrl));

        $signedXml = $this->buildSignedSeedXml($seed, $certificate);

        return SoapRetry::call(fn() => $this->exchangeSeedForToken($baseUrl, $signedXml));
    }

    /**
     * Execute a SOAP query against the service with the given token.
     */
    public function query(
        Token $token,
        string $service,
        string $action,
        array $arguments = [],
        ?string $baseUrl = null
    ): mixed {
        // Token fetching and caching is the responsibility of the caller
        // (TokenAuthenticator).
        return SoapRetry::call(function () use ($token, $service, $action, $arguments, $baseUrl): mixed {
            $baseUrl ??= $this->environment->resolve()->soapBaseUrl();
            $wsdlUrl = $this->resolveWsdlUrl($baseUrl, $service);

            $client = $this->soapClientFactory->createAuthenticatedClient($wsdlUrl, $token);

            return $client->__soapCall($action, $arguments);
        });
    }

    /**
     * Fetch a semilla (seed) from the SII CrSeed service.
     */
    protected function fetchSeed(string $baseUrl): string
    {
        $client = $this->newSoapClient($this->resolveWsdlUrl($baseUrl, 'CrSeed'));

        [$estado, $seed] = $this->callSeed($client);

        if ($this->state($estado) === SiiAuthState::Ok) {
            return $seed;
        }

        // SeedGenerationError: "Error no genera Semilla" (retry the SOAP call once, then give up).
        if ($this->state($estado) === SiiAuthState::SeedGenerationError) {
            [$estado, $seed] = $this->callSeed($client);

            if ($this->state($estado) === SiiAuthState::Ok) {
                return $seed;
            }

            throw new RuntimeException("SII seed is unavailable after retry (state '$estado').");
        }

        // SeedDatabaseError: "Error en Base de Datos SII" (retry later with progressive backoff).
        if ($this->state($estado) === SiiAuthState::SeedDatabaseError) {
            return $this->seedBackoff($client);
        }

        throw new RuntimeException("SII returned an unexpected seed state '$estado'.");
    }

    /**
     * Retry fetching the seed with progressive backoff on SeedDatabaseError.
     */
    protected function seedBackoff(SoapClient $client): string
    {
        foreach (self::RETRY_DELAYS as $delay) {
            $this->sleepFor($delay);

            [$estado, $seed] = $this->callSeed($client);

            if ($this->state($estado) === SiiAuthState::Ok) {
                return $seed;
            }

            if ($this->state($estado) !== SiiAuthState::SeedDatabaseError) {
                break;
            }
        }

        throw new SiiSeedUnavailableException('SII seed is unavailable after multiple retries.');
    }

    /**
     * Exchange a signed seed XML for an authentication token.
     */
    protected function exchangeSeedForToken(string $baseUrl, string $signedXml): string
    {
        $client = $this->newSoapClient($this->resolveWsdlUrl($baseUrl, 'GetTokenFromSeed'));

        [$estado, $token] = $this->callToken($client, $signedXml);

        if ($this->state($estado) === SiiAuthState::TokenPending) {
            return $this->tokenBackoff($client, $signedXml);
        }

        return $this->evaluateTokenResult($estado, $token);
    }

    /**
     * Retry exchanging the token with progressive backoff on TokenPending.
     */
    protected function tokenBackoff(SoapClient $client, string $signedXml): string
    {
        foreach (self::RETRY_DELAYS as $delay) {
            $this->sleepFor($delay);

            [$estado, $token] = $this->callToken($client, $signedXml);

            if ($this->state($estado) === SiiAuthState::TokenPending) {
                continue;
            }

            return $this->evaluateTokenResult($estado, $token);
        }

        throw new RuntimeException('SII could not exchange the seed for a token after multiple retries.');
    }

    /**
     * Evaluate a terminal GetTokenFromSeed response state.
     */
    protected function evaluateTokenResult(string $estado, string $token): string
    {
        $state = $this->state($estado);

        if ($state === SiiAuthState::Ok) {
            return $token;
        }

        if ($state !== null && in_array($state, SiiAuthState::clientErrors(), true)) {
            throw new RuntimeException(
                sprintf('SII rejected the token request (code %s): %s', $estado, $state->gloss())
            );
        }

        if ($state === SiiAuthState::CertificateRejected) {
            throw new SiiAuthenticationException(
                'SII rejected the certificate: it is invalid, expired or the RUT is not enrolled.'
            );
        }

        throw new RuntimeException("SII returned an unexpected token state '$estado'.");
    }

    /**
     * Map a raw SII response state to its enum, or null when unknown.
     */
    protected function state(string $estado): ?SiiAuthState
    {
        return SiiAuthState::tryFrom($estado);
    }

    /**
     * Invoke the CrSeed `getSeed` SOAP call and parse its response.
     *
     * @return array{0: string, 1: string}
     */
    protected function callSeed(SoapClient $client): array
    {
        $xml = $this->normalizeSoapReturn($client->getSeed());

        $estado = $this->statusElement($xml, static::ESTADO_ELEMENT);

        if ($estado === null) {
            throw new RuntimeException('Unable to parse the SII seed response.');
        }

        $semilla = $this->state($estado) === SiiAuthState::Ok
            ? ($this->statusElement($xml, 'SEMILLA') ?? '')
            : '';

        return [$estado, $semilla];
    }

    /**
     * Invoke the GetTokenFromSeed `getToken` SOAP call and parse its response.
     *
     * @return array{0: string, 1: string}
     */
    protected function callToken(SoapClient $client, string $signedXml): array
    {
        $xml = $this->normalizeSoapReturn($client->getToken($signedXml));

        $estado = $this->statusElement($xml, static::ESTADO_ELEMENT);

        if ($estado === null) {
            throw new RuntimeException('Unable to parse the SII token response.');
        }

        $token = $this->state($estado) === SiiAuthState::Ok
            ? ($this->statusElement($xml, 'TOKEN') ?? '')
            : '';

        return [$estado, $token];
    }

    /**
     * Build the XMLDSig enveloped-signed `<getToken>` document for a seed.
     *
     * @see https://www4c.sii.cl/bolcoreinternetui/api/openapi.yaml (getToken step)
     */
    protected function buildSignedSeedXml(string $seed, DigitalCertificate $certificate): string
    {
        // Uses XmlSigner::signRoot() which applies an enveloped signature
        // (URI="") as required by the SII auth flow. This differs from DTE
        // document signing which uses URI="#{id}" to reference a specific
        // element.
        $doc = $this->xml->document();

        $root = $doc->createElement('getToken');
        $doc->appendChild($root);

        $item = $doc->createElement('item');
        $root->appendChild($item);

        $item->appendChild($doc->createElement('Semilla', $seed));

        // Use XmlSigner for the enveloped signature (URI="").
        // This is a SII requirement for the seed token auth flow.
        $this->xmlSigner->signRoot($root, $certificate);

        return $doc->saveXML();
    }

    /**
     * Normalize the SOAP return value into a usable XML string.
     */
    protected function normalizeSoapReturn(mixed $response): string
    {
        if (is_object($response)) {
            foreach (['getSeedReturn', 'getTokenReturn', 'getSeedResult', 'getTokenResult', 'return'] as $property) {
                if (isset($response->{$property})) {
                    return (string) $response->{$property};
                }
            }

            $response = (string) $response;
        }

        return (string) $response;
    }

    /**
     * Extract the value of an element by local name from an XML string.
     */
    protected function statusElement(string $xml, string $localName): ?string
    {
        try {
            $simple = $this->xml->simpleXml($xml);
        } catch (Throwable) {
            return null;
        }

        $nodes = $simple->xpath("//*[local-name() = '$localName']");

        return isset($nodes[0]) ? trim((string) $nodes[0]) : null;
    }

    /**
     * Build a configured SOAP client for the given WSDL URL.
     */
    protected function newSoapClient(string $wsdlUrl): SoapClient
    {
        return $this->soapProxy
            ->withWsdl($wsdlUrl)
            ->build();
    }

    /**
     * Resolve the WSDL URL for the given environment and service.
     */
    protected function resolveWsdlUrl(?string $baseUrl, string $service): string
    {
        if ($baseUrl === null) {
            throw new RuntimeException('Cannot resolve the WSDL URL without a SOAP base URL.');
        }

        return $baseUrl.'/DTEWS/'.$service.'.jws?WSDL';
    }

    /**
     * Sleep for the given amount of seconds. Extracted to allow test overrides.
     */
    protected function sleepFor(int $seconds): void
    {
        Sleep::sleep($seconds);
    }
}
