<?php

namespace Tests\Unit\Mailbox;

use Illuminate\Contracts\Cache\Factory;
use Illuminate\Support\Sleep;
use Laragear\Dte\Contracts\TokenProvider;
use Laragear\Dte\Data\Token;
use Laragear\Dte\Environment\EnvironmentResolver;
use Laragear\Dte\Gateways\Exceptions\TokenInvalidException;
use Laragear\Dte\Mailbox\RutEmailResolver;
use Laragear\Dte\Proxies\SoapProxy;
use Laragear\Rut\Rut;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use SoapClient;
use SoapFault;
use Tests\TestCase;

class RutEmailResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();

        $this->app->make('cache')->flush();
    }

    protected function tearDown(): void
    {
        $this->app->make('cache')->flush();

        Sleep::fake(false);

        parent::tearDown();
    }

    protected function makeResolver(
        string $environment = 'certification',
        bool $cacheEnabled = true,
    ): RutEmailResolver {
        $token = new Token('sii-dir-token', time() + 3600);
        $this->mock(TokenProvider::class, static function (MockInterface $mock) use ($token): void {
            $mock->expects('token')
                ->zeroOrMoreTimes()
                ->andReturn($token);
            $mock->expects('retryWithFreshToken')
                ->zeroOrMoreTimes()
                ->andReturnUsing(static fn ($request, $issuer) => $request());
        });

        $this->config([
            'dte.environment' => $environment,
            'dte.cache.prefix' => 'dte',
            'dte.dim.addresses.cache' => $cacheEnabled,
            'dte.dim.addresses.days' => 30,
        ]);
        $this->app->make(EnvironmentResolver::class)->flush();

        $mockClient = Mockery::mock(SoapClient::class);
        $mockClient->expects('__setSoapHeaders')->zeroOrMoreTimes();
        $mockClient
            ->expects('__soapCall')
            ->zeroOrMoreTimes()
            ->with('getEmailByCodigo', Mockery::any())
            ->andReturn((object) [
                'getEmailByCodigoResult' => (object) ['email' => 'dte@empresa.cl'],
            ]);

        $this->mock(SoapProxy::class, static function (MockInterface $mock) use ($mockClient): void {
            $mock->expects('withWsdl')->zeroOrMoreTimes()->andReturnSelf();
            $mock->expects('withOptions')->zeroOrMoreTimes()->andReturnSelf();
            $mock->expects('build')->zeroOrMoreTimes()->andReturn($mockClient);
        });

        return $this->app->make(RutEmailResolver::class);
    }

    public function test_returns_null_in_local_environment(): void
    {
        $resolver = $this->makeResolver('local');

        $result = $resolver->resolve(Rut::parse('76.123.456-7'));

        static::assertNull($result);
    }

    public function test_fetches_email_from_sii_in_certification_environment(): void
    {
        $resolver = $this->makeResolver('certification');

        $result = $resolver->resolve(Rut::parse('76.123.456-7'));

        static::assertSame('dte@empresa.cl', $result);
    }

    public function test_fetches_email_from_sii_in_production_environment(): void
    {
        $resolver = $this->makeResolver('production');

        $result = $resolver->resolve(Rut::parse('76.123.456-7'));

        static::assertSame('dte@empresa.cl', $result);
    }

    public function test_caches_the_email_result(): void
    {
        $resolver = $this->makeResolver('certification', true);
        $rut = Rut::parse('76.123.456-7');

        $resolver->resolve($rut);

        $cached = $this->app->make(Factory::class)->get('dte|exchange_email|rut:761234567');

        static::assertSame('dte@empresa.cl', $cached);
    }

    public function test_returns_cached_email_without_calling_sii(): void
    {
        $rut = Rut::parse('76.123.456-7');
        $this->app->make(Factory::class)->put('dte|exchange_email|rut:761234567',
            'cached@empresa.cl');

        $this->mock(SoapProxy::class)->shouldNotReceive('build');
        $this->mock(TokenProvider::class)->shouldNotReceive('token');

        $this->config([
            'dte.environment' => 'certification',
            'dte.cache.prefix' => 'dte',
            'dte.dim.addresses.cache' => true,
            'dte.dim.addresses.days' => 30,
        ]);
        $this->app->make(EnvironmentResolver::class)->flush();

        $resolver = $this->app->make(RutEmailResolver::class);

        $result = $resolver->resolve($rut);

        static::assertSame('cached@empresa.cl', $result);
    }

    public function test_uses_configured_cache_prefix(): void
    {
        $rut = Rut::parse('76.123.456-7');

        $token = new Token('tok', time() + 3600);
        $this->mock(TokenProvider::class, static function (MockInterface $mock) use ($token): void {
            $mock->expects('token')->zeroOrMoreTimes()->andReturn($token);
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(fn ($request, $issuer) => $request());
        });

        $this->config([
            'dte.environment' => 'certification',
            'dte.cache.prefix' => 'myapp',
            'dte.dim.addresses.cache' => true,
            'dte.dim.addresses.days' => 30,
        ]);
        $this->app->make(EnvironmentResolver::class)->flush();

        $mockClient = Mockery::mock(SoapClient::class);
        $mockClient->expects('__setSoapHeaders');
        $mockClient
            ->expects('__soapCall')
            ->andReturn((object) [
                'getEmailByCodigoResult' => (object) ['email' => 'x@x.cl'],
            ]);

        $this->mock(SoapProxy::class, static function (MockInterface $mock) use ($mockClient): void {
            $mock->expects('withWsdl')->andReturnSelf();
            $mock->expects('build')->andReturn($mockClient);
        });

        $resolver = $this->app->make(RutEmailResolver::class);
        $resolver->resolve($rut);

        static::assertSame('x@x.cl',
            $this->app->make(Factory::class)->get('myapp|exchange_email|rut:761234567'));
    }

    public function test_does_not_cache_when_caching_is_disabled(): void
    {
        $resolver = $this->makeResolver('certification', false);
        $rut = Rut::parse('76.123.456-7');

        $resolver->resolve($rut);

        $cached = $this->app->make(Factory::class)->get('dte|exchange_email|rut:761234567');

        static::assertNull($cached);
    }

    public function test_returns_null_and_logs_warning_on_soap_fault(): void
    {
        $token = new Token('tok', time() + 3600);
        $this->mock(TokenProvider::class, static function (MockInterface $mock) use ($token): void {
            $mock->expects('token')->zeroOrMoreTimes()->andReturn($token);
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(fn ($request, $issuer) => $request());
        });

        $this->config([
            'dte.environment' => 'certification',
            'dte.cache.prefix' => 'dte',
            'dte.dim.addresses.cache' => true,
            'dte.dim.addresses.days' => 30,
        ]);
        $this->app->make(EnvironmentResolver::class)->flush();

        $mockClient = Mockery::mock(SoapClient::class);
        $mockClient->expects('__setSoapHeaders')->zeroOrMoreTimes();
        $mockClient->expects('__soapCall')->zeroOrMoreTimes()->andThrow(new SoapFault('Server', 'Fault'));

        $this->mock(SoapProxy::class, static function (MockInterface $mock) use ($mockClient): void {
            $mock->expects('withWsdl')->zeroOrMoreTimes()->andReturnSelf();
            $mock->expects('build')->zeroOrMoreTimes()->andReturn($mockClient);
        });

        $this->mock(LoggerInterface::class)
            ->expects('warning')
            ->once()
            ->with(
                Mockery::on(static fn (string $message): bool => str_contains($message,
                    'SII directory service failed to resolve email for 76123456-7')),
                Mockery::on(static fn (array $context): bool => $context['rut'] === '76123456-7'),
            );

        $resolver = $this->app->make(RutEmailResolver::class);

        static::assertNull($resolver->resolve(Rut::parse('76.123.456-7')));
    }

    public function test_throws_token_invalid_exception_when_sii_returns_invalid_token_status(): void
    {
        $token = new Token('tok', time() + 3600);
        $this->mock(TokenProvider::class, static function (MockInterface $mock) use ($token): void {
            $mock->expects('token')->zeroOrMoreTimes()->andReturn($token);
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(fn ($request, $issuer) => $request());
        });

        $this->config([
            'dte.environment' => 'certification',
            'dte.cache.prefix' => 'dte',
            'dte.dim.addresses.cache' => false,
            'dte.dim.addresses.days' => 30,
        ]);
        $this->app->make(EnvironmentResolver::class)->flush();

        $mockClient = Mockery::mock(SoapClient::class);
        $mockClient->expects('__setSoapHeaders');
        $mockClient
            ->expects('__soapCall')
            ->andReturn((object) [
                'ESTADO' => '001',
            ]);

        $this->mock(SoapProxy::class, static function (MockInterface $mock) use ($mockClient): void {
            $mock->expects('withWsdl')->andReturnSelf();
            $mock->expects('build')->andReturn($mockClient);
        });

        $resolver = $this->app->make(RutEmailResolver::class);

        $this->expectException(TokenInvalidException::class);
        $this->expectExceptionMessageIs('SII directory service rejected the authentication token.');

        $resolver->resolve(Rut::parse('76.123.456-7'));
    }
}
