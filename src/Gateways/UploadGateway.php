<?php

namespace Laragear\Dte\Gateways;

use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Laragear\Dte\Environment\EnvironmentResolver;
use Laragear\Dte\Gateways\Exceptions\TokenInvalidException;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Support\SiiEndpoints;
use Laragear\Dte\Support\TokenAuthenticator;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use function sprintf;

class UploadGateway
{
    use Concerns\SupportsTls;

    /**
     * SII upload endpoint path.
     */
    public const string UPLOAD_PATH = '/cgi_dte/UPL/DTEUpload';

    /**
     * Create a new Upload Gateway instance.
     */
    public function __construct(
        protected LoggerInterface $logger,
        protected Http $http,
        protected TokenAuthenticator $authenticator,
        protected EnvironmentResolver $environment,
        protected UploadResponseParser $responseParser,
    ) {
        //
    }

    /**
     * Upload a signed envelope XML to the SII and return the TrackID.
     */
    public function upload(SiiDteEnvelope $envelope, string $signedXml, ?string $baseUrl = null): string
    {
        $baseUrl ??= $this->environment->resolve()->soapBaseUrl();

        if ($baseUrl === null) {
            return 'fake-track-id-'.$envelope->getKey();
        }

        return $this->authenticator->retryWithFreshToken(function () use ($envelope, $signedXml, $baseUrl): string {
            $token = $this->authenticator->token($envelope->issuer_rut, $baseUrl);

            $this->logger->debug('Sending XML as attachment', [
                'base_url' => $baseUrl,
                'path' => self::UPLOAD_PATH,
                'token' => $token->value,
                'envio.xml' => $signedXml,
                'sender' => $envelope->sender_rut,
                'issuer' => $envelope->issuer_rut,
            ]);

            // This try-catch block will check any cURL error that the HTTP library can't catch.
            try {
                $response = $this->client($token->value, $baseUrl)
                    ->attach('archivo', $signedXml, 'envio.xml')
                    // Only server errors are worth another attempt: a rejected token will
                    // never be accepted on retry. `throw: false` hands back the final
                    // Response so the status checks below stay the ones deciding the error.
                    // Anything that is not an HTTP response (a cURL failure) still retries.
                    ->retry(
                        [1000, 2000, 3000, 3000, 3000],
                        when: static fn (Throwable $e): bool => ! $e instanceof RequestException
                            || $e->response->serverError(),
                        throw: false,
                    )
                    ->post(self::UPLOAD_PATH, [
                        'rutSender' => $envelope->sender_rut->num,
                        'dvSender' => $envelope->sender_rut->vd,
                        'rutCompany' => $envelope->issuer_rut->num,
                        'dvCompany' => $envelope->issuer_rut->vd,
                    ]);
            } catch (Throwable $e) {
                throw new RuntimeException('SII connection failed', previous: $e);
            }

            if ($response->unauthorized()) {
                throw new TokenInvalidException('SII Upload rejected the authentication token (401).');
            }

            if ($response->failed()) {
                $this->logger->debug('Received SII Response', [
                    'base_url' => $baseUrl,
                    'path' => self::UPLOAD_PATH,
                    'token' => $token->value,
                    'xml' => $response->body(),
                    'sender' => $envelope->sender_rut,
                    'issuer' => $envelope->issuer_rut,
                ]);

                throw new RuntimeException(sprintf(
                    'SII Upload request failed with status %d.',
                    $response->status(),
                ));
            }

            try {
                return $this->responseParser->parseTrackId($response->body());
            } catch (Throwable $e) {
                $this->logger->debug('Received SII Response', [
                    'base_url' => $baseUrl,
                    'path' => self::UPLOAD_PATH,
                    'token' => $token->value,
                    'xml' => $response->body(),
                    'sender' => $envelope->sender_rut,
                    'issuer' => $envelope->issuer_rut,
                ]);

                throw $e;
            }

        }, $envelope->issuer_rut);
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
