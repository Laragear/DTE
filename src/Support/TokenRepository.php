<?php

namespace Laragear\Dte\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\DateFactory;
use InvalidArgumentException;
use Laragear\Dte\Data\Token;
use Laragear\Dte\Enums\TokenType;
use Laragear\Rut\Rut;
use function is_numeric;

/**
 * Pure cache mechanics for SII authentication tokens.
 */

/** @internal */
class TokenRepository
{
    /**
     * Seconds before expiry to proactively refresh REST tokens.
     */
    protected const int REST_REFRESH_THRESHOLD = 300;

    /**
     * Maximum token lifetime allowed by SII (1 hour).
     */
    protected const int SII_MAX_TTL = 3600;

    /**
     * Create a new Token Repository instance.
     */
    public function __construct(
        protected DateFactory $date,
        protected Repository $cache,
        protected ConfigRepository $config,
    ) {
        //
    }

    /**
     * Resolve the TTL in seconds for the given service, capped at 3600.
     */
    public function ttl(TokenType $service): int
    {
        // Both SOAP and REST tokens share the same SII 1-hour lifetime, so both
        // services read the same `dte.soap.token_ttl` configuration.
        $configured = $this->config->get('dte.soap.token_ttl');

        // REST tokens are refreshed within 5 minutes of expiry to prevent authentication
        // failures during batch operations.
        $ttl = is_numeric($configured) ? (int) $configured : self::SII_MAX_TTL;

        if ($ttl > self::SII_MAX_TTL) {
            throw new InvalidArgumentException(
                strtoupper($service->name).' token TTL must not exceed '.self::SII_MAX_TTL." seconds (SII token lifetime). Got: {$ttl}"
            );
        }

        return $ttl;
    }

    /**
     * Build the cache key for the given service and issuer.
     */
    public function key(TokenType $service, Rut $rut): string
    {
        $prefix = $this->config->get('dte.cache.prefix', 'dte');

        return $prefix.'|'.$service->value.'_token|business:'.$rut->formatRaw();
    }

    /**
     * Retrieve a non-expired token from the cache, or null if absent/expired.
     */
    public function get(TokenType $service, Rut $rut): ?Token
    {
        $key = $this->key($service, $rut);

        $token = Token::fromJson($this->cache->get($key, '{"value":"","expires_at":0}'));

        if ($token->isNotExpired()) {
            // For REST tokens, proactively refresh if close to expiry. Returns null if
            // the token is close to expiry (within REST_REFRESH_THRESHOLD seconds) to
            // trigger proactive refresh (by returning null).
            if ($service === TokenType::Rest) {
                $expiresInSeconds = $token->expiresAt - $this->date->now()->getTimestamp();

                if ($expiresInSeconds <= self::REST_REFRESH_THRESHOLD) {
                    return null;
                }
            }

            // Also touches the cache entry to extend the TTL, since SII renews the
            // token lifetime on each use.
            $this->cache->touch($key, $this->ttl($service));

            return $token;
        }

        return null;
    }

    /**
     * Store a token in the cache for the given service and issuer.
     */
    public function put(TokenType $service, Rut $rut, Token $token): void
    {
        $this->cache->put(
            $this->key($service, $rut), $token->toJson(), $token->expiresAt - $this->date->now()->getTimestamp()
        );
    }

    /**
     * Forget the cached token for the given service and issuer.
     */
    public function forget(TokenType $service, Rut $rut): void
    {
        $this->cache->forget($this->key($service, $rut));
    }
}
