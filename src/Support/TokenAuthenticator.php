<?php

namespace Laragear\Dte\Support;

use Illuminate\Support\DateFactory;
use Laragear\Dte\Contracts\TokenProviderInterface;
use Laragear\Dte\Data\Token;
use Laragear\Dte\Enums\TokenType;
use Laragear\Dte\Gateways\Exceptions\TokenInvalidException;
use Laragear\Dte\Gateways\RestAuthGateway;
use Laragear\Dte\Gateways\SoapGateway;
use Laragear\Rut\Rut;
use Throwable;

/** @internal */
class TokenAuthenticator implements TokenProviderInterface
{
    /**
     * Create a new Token Authenticator instance.
     */
    public function __construct(
        protected DateFactory $date,
        protected TokenRepository $repository,
        protected SoapGateway $soap,
        protected RestAuthGateway $rest,
    ) {
        //
    }

    /**
     * Get a valid SOAP authentication token for the given taxpayer.
     */
    public function token(Rut $issuer, ?string $baseUrl = null): Token
    {
        // Returns the cached token if still valid, otherwise authenticates with SII and caches the new token.
        return $this->repository->get(TokenType::Soap, $issuer)
            ?: $this->authenticateSoap($issuer, $baseUrl);
    }

    /**
     * Get a valid REST authentication token value for the given taxpayer.
     */
    public function restToken(Rut $issuer, ?string $authUrl = null): string
    {
        // Returns the cached token if still valid, otherwise authenticates with SII and caches the new token.
        return $this->repository->get(TokenType::Rest, $issuer)?->value
            ?: $this->authenticateRest($issuer, $authUrl);
    }

    /**
     * Force re-authentication for the SOAP endpoint.
     */
    public function refresh(Rut $issuer, ?string $baseUrl = null): Token
    {
        // Invalidates the cached SOAP token and retrieves + caches a fresh one.
        // Called by consumers when SII signals this current token is no longer valid.
        $this->repository->forget(TokenType::Soap, $issuer);

        return $this->authenticateSoap($issuer, $baseUrl);
    }

    /**
     * Force re-authentication for the REST endpoint.
     */
    public function refreshRest(Rut $issuer, ?string $authUrl = null): string
    {
        // Invalidates the cached REST token and retrieves + caches a fresh one.
        $this->repository->forget(TokenType::Rest, $issuer);

        return $this->authenticateRest($issuer, $authUrl);
    }

    /**
     * Perform the SOAP authentication flow and cache the resulting token.
     */
    protected function authenticateSoap(Rut $issuer, ?string $baseUrl): Token
    {
        return $this->authenticate(
            TokenType::Soap,
            $issuer,
            fn() => $this->soap->authenticate($issuer, null, $baseUrl),
        );
    }

    /**
     * Perform the REST authentication flow and cache the resulting token.
     */
    protected function authenticateRest(Rut $issuer, ?string $authUrl): string
    {
        return $this->authenticate(
            TokenType::Rest,
            $issuer,
            fn() => $this->rest->fetchToken($issuer, $authUrl),
        )->value;
    }

    /**
     * Shared authentication flow.
     *
     * @template TReturn
     *
     * @param  callable():TReturn  $authenticate
     */
    protected function authenticate(TokenType $type, Rut $issuer, callable $authenticate): Token
    {
        // Validates the TTL before any SII round-trip so operator misconfiguration fails fast.
        $ttl = $this->repository->ttl($type);

        // Call the gateway.
        $raw = $authenticate();

        // Wrap the token
        $token = Token::make($raw, $this->date->now()->addSeconds($ttl)->getTimestamp());

        // Store the token
        $this->repository->put($type, $issuer, $token);

        return $token;
    }

    /**
     * Execute a callback, retrying with a fresh SOAP token on rejection.
     *
     * @param  callable(): mixed  $request  The SII request to execute.
     */
    public function retryWithFreshToken(callable $request, Rut $issuer): mixed
    {
        // Retries only on TokenInvalidException (refreshing the SOAP token before
        // each subsequent attempt); any other exception is rethrown immediately.
        // After 3 total attempts, the last TokenInvalidException is rethrown.
        return $this->retryLoop($request, $issuer, function (Rut $rut): Token {
            return $this->refresh($rut);
        });
    }

    /**
     * Execute a callback, retrying with a fresh REST token on rejection.
     *
     * @param  callable(): mixed  $request  The SII request to execute.
     */
    public function retryRestWithFreshToken(callable $request, Rut $issuer): mixed
    {
        // Retries only on TokenInvalidException (refreshing the REST token before
        // each subsequent attempt); any other exception is rethrown immediately.
        // After 3 total attempts, the last TokenInvalidException is rethrown.
        return $this->retryLoop($request, $issuer, function (Rut $rut): string {
            return $this->refreshRest($rut);
        });
    }

    /**
     * Shared refresh-and-retry loop for the retry*WithFreshToken methods.
     *
     * @param  callable(): mixed  $request  The SII request to execute.
     * @param  callable(Rut): void  $refresh  Refreshes the token to retry with.
     */
    protected function retryLoop(callable $request, Rut $issuer, callable $refresh): mixed
    {
        return retry(3, $request, when: static function (Throwable $e) use ($issuer, $refresh): bool {
            if ($e instanceof TokenInvalidException) {
                $refresh($issuer);

                return true;
            }

            return false;
        });
    }
}
