<?php

namespace Laragear\Dte\Gateways;

use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Laragear\Dte\Environment\EnvironmentResolver;
use Laragear\Dte\Gateways\Exceptions\TokenInvalidException;
use Laragear\Dte\Support\SiiEndpoints;
use Laragear\Dte\Support\TokenAuthenticator;
use Laragear\Rut\Rut;
use RuntimeException;
use function sprintf;

class IecvUploadGateway
{
    use Concerns\SupportsTls;

    /**
     * Create a new IECV Upload Gateway instance.
     */
    public function __construct(
        protected Http $http,
        protected TokenAuthenticator $authenticator,
        protected EnvironmentResolver $environment,
        protected UploadResponseParser $responseParser,
    ) {
        //
    }

    /**
     * Upload an IECV XML to the SII and return the TrackID.
     */
    public function upload(Rut $issuer, Rut $sender, string $signedXml, ?string $baseUrl = null): string
    {
        $baseUrl ??= $this->environment->resolve()->soapBaseUrl();

        if ($baseUrl === null) {
            return 'fake-iecv-track-id-'.time();
        }

        return $this->authenticator->retryWithFreshToken(function () use (
            $issuer,
            $sender,
            $signedXml,
            $baseUrl
        ): string {
            $token = $this->authenticator->token($issuer, $baseUrl);

            $response = $this->client($token->value, $baseUrl)
                ->attach(
                    'archivo',
                    $signedXml,
                    'iecv.xml',
                )
                ->post(UploadGateway::UPLOAD_PATH, [
                    'rutSender' => $sender->num,
                    'dvSender' => $sender->vd,
                    'rutCompany' => $issuer->num,
                    'dvCompany' => $issuer->vd,
                ]);

            if ($response->unauthorized()) {
                throw new TokenInvalidException('SII Upload rejected the authentication token (401).');
            }

            if ($response->failed()) {
                throw new RuntimeException(sprintf(
                    'SII Upload request failed with status %d.',
                    $response->status(),
                ));
            }

            return $this->responseParser->parseTrackId($response->body());
        }, $issuer);
    }

    /**
     * Build an authenticated multipart HTTP client for the SII upload service.
     */
    protected function client(string $token, string $baseUrl): PendingRequest
    {
        return $this->http
            ->createPendingRequest()
            ->baseUrl($baseUrl)
            ->withCookies([SiiEndpoints::TOKEN_COOKIE => $token], parse_url($baseUrl, PHP_URL_HOST))
            ->withHeaders([SiiEndpoints::USER_AGENT_HEADER => SiiEndpoints::USER_AGENT])
            ->timeout(60)
            ->asMultipart()
            ->withOptions($this->tlsOptions());
    }
}
