<?php

namespace Laragear\Dte\Gateways;

use Illuminate\Support\Str;
use Laragear\Dte\Data\IecvTrackStatus;
use Laragear\Dte\Environment\EnvironmentResolver;
use Laragear\Dte\Gateways\Exceptions\TokenInvalidException;
use Laragear\Dte\Models\SiiIecv;
use Laragear\Dte\Support\TokenAuthenticator;
use Laragear\Dte\Support\XmlDomFactory;
use RuntimeException;
use SimpleXMLElement;

use function array_map;
use function is_numeric;
use function is_object;
use function trim;

class IecvStatusGateway
{
    /**
     * SII web service exposing the upload status query.
     */
    public const string QUERY_SERVICE = 'QueryEstUp';

    /**
     * Create a new IECV Status Gateway instance.
     */
    public function __construct(
        protected SoapGateway $soap,
        protected TokenAuthenticator $authenticator,
        protected EnvironmentResolver $environment,
        protected XmlDomFactory $xml,
    ) {
        //
    }

    /**
     * Query the SII for the current status of a book upload.
     */
    public function trackStatus(SiiIecv $book, ?string $baseUrl = null): IecvTrackStatus
    {
        $baseUrl ??= $this->environment->resolve()->soapBaseUrl();

        if ($baseUrl === null) {
            return $this->fakeTrackStatus();
        }

        // Uses the authenticator's retryWithFreshToken() loop: on an invalidated
        // token the authenticator refreshes it and retries.
        return $this->authenticator->retryWithFreshToken(function () use ($book, $baseUrl): IecvTrackStatus {
            $token = $this->authenticator->token($book->issuer_rut, $baseUrl);

            $response = $this->soap->query($token, static::QUERY_SERVICE, 'getEstUp', [
                'RutCompany' => $book->issuer_rut->num,
                'DvCompany' => $book->issuer_rut->vd,
                'TrackId' => $book->track_id,
                'Token' => $token->value,
            ], $baseUrl);

            $xml = is_object($response) ? ($response->getEstUpResult ?? '') : (string) $response;

            if ($this->isTokenInvalid($xml)) {
                throw new TokenInvalidException('SII SOAP token was invalidated (001/002/003).');
            }

            return $this->parse($xml);
        }, $book->issuer_rut);
    }

    /**
     * Parse the books upload response into a track status DTO.
     */
    protected function parse(string $xml): IecvTrackStatus
    {
        if (trim($xml) === '') {
            throw new RuntimeException('SII returned an empty response for the book status query.');
        }

        // The books response payload is namespace-safe: every lookup below goes
        // through local-name(), because an unqualified XPath matches only
        // elements in no namespace and would silently find nothing otherwise.
        $simple = $this->xml->simpleXml($xml);

        $sendState = $this->value($simple, 'EstadoEnvio');

        if ($sendState === null) {
            throw new RuntimeException(
                'The SII book status response did not contain an EstadoEnvio element.'
            );
        }

        return new IecvTrackStatus(
            sendState: $sendState,
            bookState: $this->value($simple, 'EstadoLibro'),
            errors: $this->errors($simple),
            trackId: $this->trackId($simple),
            raw: $xml,
        );
    }

    /**
     * Read a single element value using namespace-safe lookups.
     */
    protected function value(SimpleXMLElement $simple, string $name): ?string
    {
        $nodes = $simple->xpath("//*[local-name()='$name']");

        $value = $nodes !== false ? trim((string) ($nodes[0] ?? '')) : '';

        return $value === '' ? null : $value;
    }

    /**
     * Collect the individual book errors reported by the SII.
     *
     * @return array<int, string>
     */
    protected function errors(SimpleXMLElement $simple): array
    {
        $nodes = $simple->xpath("//*[local-name()='DetErrEnvio']");

        $errors = [];

        foreach ($nodes as $node) {
            $error = trim((string) $node);

            if ($error !== '') {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * Read the track ID echoed back by the SII.
     */
    protected function trackId(SimpleXMLElement $simple): ?int
    {
        $value = $this->value($simple, 'TrackId');

        return is_numeric($value)
            ? (int) $value
            : null;
    }

    /**
     * Checks if the response indicates an invalidated SOAP token.
     */
    protected function isTokenInvalid(string $xml): bool
    {
        return Str::contains($xml, array_map(
            static fn (string $code): string => '<ESTADO>'.$code.'</ESTADO>',
            TokenStatus::INVALID_CODES,
        ));
    }

    /**
     * Build a local-environment status without contacting the SII.
     */
    protected function fakeTrackStatus(): IecvTrackStatus
    {
        return new IecvTrackStatus(
            sendState: 'EPR',
            bookState: 'CTR',
            trackId: 1,
            raw: '<fake-iecv-status/>',
        );
    }
}
