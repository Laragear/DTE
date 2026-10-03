<?php

namespace Laragear\Dte\Gateways;

use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Laragear\Dte\Data\TrackStatus;
use Laragear\Dte\Environment\EnvironmentResolver;
use Laragear\Dte\Gateways\Exceptions\TokenInvalidException;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Support\SiiEndpoints;
use Laragear\Dte\Support\TokenAuthenticator;
use Laragear\Rut\Rut;
use RuntimeException;

class BoletaRestGateway
{
    use Concerns\SupportsTls;

    /**
     * Response returned when there is no REST base URL (non-production environments).
     *
     * @example The SII "received" status without a real envelope
     */
    protected const array FAKE_TRACK_STATUS = ['estado' => 'REC', 'glosa' => 'Faked status'];

    /**
     * Create a new Boleta Rest Gateway instance.
     */
    public function __construct(
        protected Http $http,
        protected EnvironmentResolver $environment,
        protected TokenAuthenticator $authenticator,
    ) {
        //
    }

    /**
     * Get an authentication token string for the given issuer RUT.
     */
    public function getToken(Rut $issuerRut, ?string $authUrl = null): string
    {
        $authUrl ??= $this->environment->resolve()->restBaseUrl();

        if ($authUrl === null) {
            return 'fake-token';
        }

        // Delegates to the TokenAuthenticator which handles cache read + SII auth.
        // Token fetching and caching are owned by the TokenAuthenticator; on a 401
        // the refresh-and-retry loop asks it to refresh the REST token and retries.
        return $this->authenticator->restToken($issuerRut, $authUrl);
    }

    /**
     * Upload a signed envelope XML to the SII REST API and return the TrackID.
     */
    public function upload(SiiDteEnvelope $envelope, string $signedXml, ?string $authUrl = null): string
    {
        $authUrl ??= $this->environment->resolve()->restBaseUrl();

        if ($authUrl === null) {
            return 'fake-track-id-'.$envelope->getKey();
        }

        return $this->authenticator->retryRestWithFreshToken(function () use ($envelope, $signedXml, $authUrl): string {
            $restToken = $this->authenticator->restToken($envelope->issuer_rut, $authUrl);
            $baseUrl = $this->environment->resolve()->restUploadBaseUrl();

            /**
             * We do a POST to /boleta.electronica.envio (dedicated upload server).
             * Uses multipart/form-data with file attachment as required by SII.
             *
             * @see https://www4c.sii.cl/bolcoreinternetui/api/openapi.yaml (EnvioPost schema)
             */
            $uploadResponse = $this
                ->makeClient($restToken, $baseUrl, 60)
                ->attach('archivo', $signedXml, 'envio.xml', ['Content-Type' => 'application/xml'])
                ->post('/boleta.electronica.envio', [
                    'rutSender' => $envelope->sender_rut->num,
                    'dvSender' => $envelope->sender_rut->vd,
                    'rutCompany' => $envelope->issuer_rut->num,
                    'dvCompany' => $envelope->issuer_rut->vd,
                ]);

            if ($uploadResponse->unauthorized()) {
                throw new TokenInvalidException('SII Upload rejected the authentication token (401).');
            }

            if ($uploadResponse->failed()) {
                throw new RuntimeException('SII Upload request failed with status '.$uploadResponse->status().'.');
            }

            $trackId = $uploadResponse->json('trackid');

            if (!$trackId) {
                throw new RuntimeException('SII Upload response did not contain a valid TrackID.');
            }

            return (string) $trackId;
        }, $envelope->issuer_rut);
    }

    /**
     * Queries the SII REST API for the status of an envelope track ID.
     *
     * @see https://www4c.sii.cl/bolcoreinternetui/api/openapi.yaml (X-Retry-After header)
     */
    public function trackStatus(SiiDteEnvelope $envelope, ?string $authUrl = null): TrackStatus
    {
        $authUrl ??= $this->environment->resolve()->restBaseUrl();

        // Returns a TrackStatus DTO that includes the X-Retry-After header value for respecting SII rate limits.
        if ($authUrl === null) {
            return new TrackStatus(status: 'REC', raw: static::FAKE_TRACK_STATUS);
        }

        return $this->authenticator->retryRestWithFreshToken(function () use ($envelope, $authUrl): TrackStatus {
            $token = $this->authenticator->restToken($envelope->issuer_rut, $authUrl);

            $response = $this->makeClient($token, $authUrl)
                ->get(sprintf(
                    '/boleta.electronica.envio/%s-%s-%s',
                    $envelope->issuer_rut->num,
                    $envelope->issuer_rut->vd,
                    $envelope->track_id
                ));

            if ($response->unauthorized()) {
                throw new TokenInvalidException('SII Status Query rejected the authentication token (401).');
            }

            if ($response->failed()) {
                throw new RuntimeException('SII Status Query failed with status '.$response->status().'.');
            }

            // X-Retry-After header specifies seconds to wait before next poll.
            $retryAfter = (int) ($response->header('X-Retry-After') ?? 10);

            return TrackStatus::fromResponse($response->json() ?? [], $retryAfter);
        }, $envelope->issuer_rut);
    }

    /**
     * Queries the SII REST API for the status of an individual boleta DTE.
     */
    public function documentStatus(SiiDte $dte, ?string $authUrl = null): array
    {
        $authUrl ??= $this->environment->resolve()->restBaseUrl();

        if ($authUrl === null) {
            return static::FAKE_TRACK_STATUS;
        }

        return $this->authenticator->retryRestWithFreshToken(function () use ($dte, $authUrl): array {
            $token = $this->authenticator->restToken($dte->issuer_rut, $authUrl);

            $receiverNum = '0';
            $receiverVd = '0';

            if ($dte->receiver_rut && $dte->receiver_rut->formatRaw() !== '0') {
                $receiverNum = $dte->receiver_rut->num;
                $receiverVd = $dte->receiver_rut->vd;
            }

            $monto = $dte->amount_total;
            $fecha = $dte->issued_on->format('d-m-Y');

            // OpenAPI path: /boleta.electronica/{rut}-{dv}-{tipo}-{folio}/estado
            $response = $this->makeClient($token, $authUrl)
                ->get(sprintf(
                    '/boleta.electronica/%s-%s-%s-%s/estado',
                    $dte->issuer_rut->num,
                    $dte->issuer_rut->vd,
                    $dte->document_type->value,
                    $dte->folio
                ), [
                    'rut_receptor' => $receiverNum.'-'.$receiverVd,
                    'monto' => $monto,
                    'fechaEmision' => $fecha,
                ]);

            if ($response->unauthorized()) {
                throw new TokenInvalidException('SII Document Status Query rejected the authentication token (401).');
            }

            if ($response->failed()) {
                throw new RuntimeException('SII Document Status Query failed with status '.$response->status().'.');
            }

            return $response->json() ?? [];
        }, $dte->issuer_rut);
    }

    /**
     * Build an authenticated HTTP client for the SII REST API.
     */
    protected function makeClient(string $token, string $baseUrl, int $timeout = 30): PendingRequest
    {
        return $this->http
            ->createPendingRequest()
            ->baseUrl($baseUrl)
            ->withCookies([SiiEndpoints::TOKEN_COOKIE => $token], parse_url($baseUrl, PHP_URL_HOST))
            ->withHeaders([SiiEndpoints::USER_AGENT_HEADER => SiiEndpoints::USER_AGENT])
            ->timeout($timeout)
            ->withOptions($this->tlsOptions());
    }
}
