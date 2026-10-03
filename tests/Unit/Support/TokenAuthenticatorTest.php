<?php

namespace Tests\Unit\Support;

use Illuminate\Contracts\Cache\Factory;
use Illuminate\Support\DateFactory;
use InvalidArgumentException;
use Laragear\Dte\Data\Token;
use Laragear\Dte\Enums\TokenType;
use Laragear\Dte\Gateways\Exceptions\TokenInvalidException;
use Laragear\Dte\Gateways\RestAuthGateway;
use Laragear\Dte\Gateways\SoapGateway;
use Laragear\Dte\Support\TokenAuthenticator;
use Laragear\Dte\Support\TokenRepository;
use Laragear\Rut\Rut;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class TokenAuthenticatorTest extends TestCase
{
    protected SoapGateway|MockInterface $soap;

    protected RestAuthGateway|MockInterface $rest;

    protected TokenAuthenticator $authenticator;

    protected Rut $issuer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make('cache')->flush();
        $this->config('dte.soap.token_ttl', 3600);

        $this->issuer = Rut::parse('76.123.456-7');

        $this->soap = Mockery::mock(SoapGateway::class);
        $this->rest = Mockery::mock(RestAuthGateway::class);

        // The real TokenRepository: the authenticator must drive the actual cache.
        $this->authenticator = new TokenAuthenticator(
            $this->app->make(DateFactory::class),
            $this->app->make(TokenRepository::class),
            $this->soap,
            $this->rest,
        );
    }

    protected function tearDown(): void
    {
        $this->app->make('cache')->flush();

        parent::tearDown();
    }

    protected function seedCache(TokenType $service, Token $token): void
    {
        $this->app->make(TokenRepository::class)->put($service, $this->issuer, $token);
    }

    public function test_token_returns_cached_token_without_authenticating(): void
    {
        $this->seedCache(TokenType::Soap, new Token('cached-token', time() + 3600));

        $this->soap->shouldNotReceive('authenticate');

        $token = $this->authenticator->token($this->issuer);

        static::assertSame('cached-token', $token->value);
    }

    public function test_token_authenticates_and_caches_when_nothing_is_cached(): void
    {
        $this->soap->expects('authenticate')->with($this->issuer, null, null)->andReturn('fresh-token');

        $token = $this->authenticator->token($this->issuer);

        static::assertSame('fresh-token', $token->value);

        $diff = $token->expiresAt - time();

        // The authenticator owns the TTL: expires in ~3600s.
        static::assertGreaterThanOrEqual(3599, $diff);
        static::assertLessThanOrEqual(3600, $diff);

        // The token was cached: the next call does not re-authenticate.
        $this->soap->shouldNotReceive('authenticate');

        static::assertSame('fresh-token', $this->authenticator->token($this->issuer)->value);
    }

    public function test_token_fails_fast_on_ttl_misconfiguration(): void
    {
        $this->config('dte.soap.token_ttl', 7200);

        $this->soap->shouldNotReceive('authenticate');

        try {
            $this->authenticator->token($this->issuer);
            static::fail('Expected the TTL misconfiguration to throw.');
        } catch (Throwable $e) {
            static::assertInstanceOf(InvalidArgumentException::class, $e);
        }
    }

    public function test_rest_token_returns_cached_value_without_authenticating(): void
    {
        $this->seedCache(TokenType::Rest, new Token('cached-rest', time() + 3600));

        $this->rest->shouldNotReceive('fetchToken');

        static::assertSame('cached-rest', $this->authenticator->restToken($this->issuer));
    }

    public function test_rest_token_authenticates_and_caches_when_nothing_is_cached(): void
    {
        $this->rest->expects('fetchToken')->with($this->issuer, null)->andReturn('fresh-rest');

        static::assertSame('fresh-rest', $this->authenticator->restToken($this->issuer));

        // The token was cached: the next call does not re-authenticate.
        $this->rest->shouldNotReceive('fetchToken');

        static::assertSame('fresh-rest', $this->authenticator->restToken($this->issuer));
    }

    public function test_refresh_forgets_the_cached_token_and_re_authenticates(): void
    {
        $this->seedCache(TokenType::Soap, new Token('stale-token', time() + 3600));

        $this->soap->expects('authenticate')->with($this->issuer, null, null)->andReturn('refreshed-token');

        $token = $this->authenticator->refresh($this->issuer);

        static::assertSame('refreshed-token', $token->value);

        // The stale token was replaced in the cache.
        $cached = Token::fromJson($this->app->make(Factory::class)->get('dte|soap_token|business:761234567'));

        static::assertSame('refreshed-token', $cached->value);
    }

    public function test_refresh_rest_forgets_the_cached_token_and_re_authenticates(): void
    {
        $this->seedCache(TokenType::Rest, new Token('stale-rest', time() + 3600));

        $this->rest->expects('fetchToken')->with($this->issuer, null)->andReturn('refreshed-rest');

        static::assertSame('refreshed-rest', $this->authenticator->refreshRest($this->issuer));

        $cached = Token::fromJson($this->app->make(Factory::class)->get('dte|rest_token|business:761234567'));

        static::assertSame('refreshed-rest', $cached->value);
    }

    public function test_refresh_propagates_auth_failures(): void
    {
        $this->soap->expects('authenticate')->andThrow(new RuntimeException('SII unavailable'));

        $this->expectException(RuntimeException::class);

        $this->authenticator->refresh($this->issuer);
    }

    public function test_retry_with_fresh_token_returns_result_without_refreshing(): void
    {
        $this->soap->shouldNotReceive('authenticate');

        static::assertSame(
            'sii-response',
            $this->authenticator->retryWithFreshToken(fn(): string => 'sii-response', $this->issuer)
        );
    }

    public function test_retry_with_fresh_token_refreshes_once_and_succeeds(): void
    {
        $attempts = 0;

        // One refresh before the successful second attempt.
        $this->soap->expects('authenticate')->andReturn('refreshed-token');

        $result = $this->authenticator->retryWithFreshToken(function () use (&$attempts): string {
            if (++$attempts === 1) {
                throw new TokenInvalidException('Token was rejected.');
            }

            return 'sii-response';
        }, $this->issuer);

        static::assertSame('sii-response', $result);
        static::assertSame(2, $attempts);
    }

    public function test_retry_with_fresh_token_gives_up_after_three_attempts(): void
    {
        $attempts = 0;

        // Two refreshes (one before each retry), then the last exception propagates.
        $this->soap->expects('authenticate')->twice()->andReturn('refreshed-token');

        try {
            $this->authenticator->retryWithFreshToken(function () use (&$attempts): never {
                $attempts++;

                throw new TokenInvalidException('Token keeps being rejected.');
            }, $this->issuer);

            static::fail('Expected TokenInvalidException after 3 attempts.');
        } catch (TokenInvalidException $e) {
            static::assertSame('Token keeps being rejected.', $e->getMessage());
        }

        static::assertSame(3, $attempts);
    }

    public function test_retry_does_not_retry_other_exceptions(): void
    {
        $attempts = 0;

        $this->soap->shouldNotReceive('authenticate');

        try {
            $this->authenticator->retryWithFreshToken(function () use (&$attempts): never {
                $attempts++;

                throw new RuntimeException('Transport failed.');
            }, $this->issuer);

            static::fail('Expected the original exception to be rethrown.');
        } catch (RuntimeException $e) {
            static::assertSame('Transport failed.', $e->getMessage());
        }

        static::assertSame(1, $attempts);
    }

    public function test_retry_rest_with_fresh_token_refreshes_and_succeeds(): void
    {
        $attempts = 0;

        $this->rest->expects('fetchToken')->andReturn('refreshed-rest');

        $result = $this->authenticator->retryRestWithFreshToken(function () use (&$attempts): string {
            if (++$attempts === 1) {
                throw new TokenInvalidException('SII REST rejected the token (401).');
            }

            return 'rest-response';
        }, $this->issuer);

        static::assertSame('rest-response', $result);
        static::assertSame(2, $attempts);
    }

    public function test_retry_rest_with_fresh_token_gives_up_after_three_attempts(): void
    {
        $attempts = 0;

        $this->rest->expects('fetchToken')->twice()->andReturn('refreshed-rest');

        try {
            $this->authenticator->retryRestWithFreshToken(function () use (&$attempts): never {
                $attempts++;

                throw new TokenInvalidException('SII REST rejected the token (401).');
            }, $this->issuer);

            static::fail('Expected TokenInvalidException after 3 attempts.');
        } catch (TokenInvalidException) {
            // Expected.
        }

        static::assertSame(3, $attempts);
    }
}
