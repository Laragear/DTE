<?php

namespace Laragear\Dte\Mailbox;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\DateFactory;
use Laragear\Dte\Contracts\TokenProvider;
use Laragear\Dte\Data\Token;
use Laragear\Dte\Environment\EnvironmentResolver;
use Laragear\Dte\Gateways\Exceptions\TokenInvalidException;
use Laragear\Dte\Gateways\SoapClientFactory;
use Laragear\Dte\Gateways\TokenStatus;
use Laragear\Dte\Support\SoapRetry;
use Laragear\Rut\Rut;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Resolves the official DTE interchange email address for a given RUT,
 * using the SII directory SOAP service with a configurable cache layer.
 *
 * Cache key format: dte|exchange_email|rut:{rut}
 */
class RutEmailResolver
{
    /**
     * Create a new Rut Email Resolver instance.
     */
    public function __construct(
        protected Cache $cache,
        protected ConfigRepository $config,
        protected DateFactory $date,
        protected TokenProvider $tokenProvider,
        protected SoapClientFactory $soapClientFactory,
        protected EnvironmentResolver $environment,
        protected LoggerInterface $logger,
    ) {
        //
    }

    /**
     * Resolve the DTE interchange email for the given RUT.
     */
    public function resolve(Rut $rut): ?string
    {
        // Will return a cached result if available and caching is enabled.
        $cacheEnabled = $this->config->get('dte.dim.addresses.cache', true);
        $cacheKey = $this->cacheKey($rut);

        if ($cacheEnabled) {
            $cached = $this->cache->get($cacheKey);

            if ($cached !== null) {
                return $cached;
            }
        }

        $email = $this->fetchFromSii($rut);

        if ($cacheEnabled && $email !== null) {
            $days = $this->config->get('dte.dim.addresses.days', 30);
            $this->cache->put($cacheKey, $email, $this->date->now()->addDays($days));
        }

        return $email;
    }

    /**
     * Fetch the exchange email from the SII SOAP directory service.
     */
    protected function fetchFromSii(Rut $rut): ?string
    {
        // Uses the authenticator's retryWithFreshToken() loop: on
        // TokenInvalidException (SII returned 001/002/003), the authenticator
        // refreshes the token and retries, up to 3 total attempts.
        $environment = $this->environment->resolve();

        if ($environment->isLocal() || $environment->isTesting()) {
            return null;
        }

        $baseUrl = $environment->isProduction()
            ? 'https://palena.sii.cl'
            : 'https://maullin.sii.cl';

        return $this->tokenProvider->retryWithFreshToken(function () use ($rut, $baseUrl): ?string {
            $token = $this->tokenProvider->token($rut);

            $wsdlUrl = $baseUrl.'/DTEWS/CrSeed.asmx?WSDL';

            try {
                $result = $this->callDirectoryService($wsdlUrl, $token, $rut);
            } catch (Throwable $e) {
                $this->logger->warning(
                    'SII directory service failed to resolve email for '.$rut->formatBasic().': '.$e->getMessage(),
                    ['rut' => $rut->formatBasic()],
                );

                return null;
            }

            // SII signals an inactive/invalid token with 001/002/003 in the response header: refresh and retry.
            $estado = $result->ESTADO
                ?? $result->getEmailByCodigoResult->ESTADO
                ?? null;

            if (TokenStatus::isNotValid($estado)) {
                throw new TokenInvalidException('SII directory service rejected the authentication token.');
            }

            $email = (string) ($result->getEmailByCodigoResult->email ?? '');

            return $email !== '' ? $email : null;
        }, $rut);
    }

    /**
     * Call the SII directory service for the given RUT.
     */
    protected function callDirectoryService(string $wsdlUrl, Token $token, Rut $rut): mixed
    {
        return SoapRetry::call(function () use ($wsdlUrl, $token, $rut) {
            $client = $this->soapClientFactory->createAuthenticatedClient($wsdlUrl, $token);

            return $client->__soapCall('getEmailByCodigo', [
                [
                    'RutEmpresa' => $rut->num,
                    'DvEmpresa' => $rut->vd,
                ],
            ]);
        });
    }

    /**
     * Build the globally prefixed cache key for this RUT's exchange email.
     */
    protected function cacheKey(Rut $rut): string
    {
        $prefix = $this->config->get('dte.cache.prefix', 'dte');

        return $prefix.'|exchange_email|rut:'.$rut->formatRaw();
    }
}
