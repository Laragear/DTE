<?php

namespace Tests\Unit\Services;

use Laragear\Dte\Data\Token;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Gateways\SoapGateway;
use Laragear\Dte\Services\DteAuthenticityVerifier;
use Laragear\Dte\Support\TokenAuthenticator;
use Laragear\Rut\Rut;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class DteAuthenticityVerifierTest extends TestCase
{
    protected function makeVerifier(): DteAuthenticityVerifier
    {
        return $this->app->make(DteAuthenticityVerifier::class);
    }

    protected function mockTokenAuth(): void
    {
        $token = new Token('fake-token', time() + 3600);

        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock) use ($token): void {
            $mock->expects('token')->zeroOrMoreTimes()->andReturn($token);
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(static fn(callable $request, Rut $issuer) => $request());
        });
    }

    protected function mockSoapQuery(string $response): void
    {
        $this->mock(SoapGateway::class, static function (MockInterface $mock) use ($response): void {
            $mock->expects('query')->zeroOrMoreTimes()->andReturn($response);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Happy paths
    |--------------------------------------------------------------------------
    */

    public function test_returns_true_when_accepted(): void
    {
        $this->mockTokenAuth();
        $this->mockSoapQuery(static::getStub('SiiAuthResponseOk.xml'));

        $verifier = $this->makeVerifier();

        static::assertTrue($verifier->verify(
            Rut::parse('60.803.000-K'),
            Rut::parse('76123456-0'),
            DteType::Invoice,
            1,
            now(),
            1000,
        ));
    }

    public function test_returns_true_when_accepted_with_repairs(): void
    {
        $this->mockTokenAuth();
        $this->mockSoapQuery(static::getStub('SiiAuthResponseWithRepairs.xml'));

        $verifier = $this->makeVerifier();

        static::assertTrue($verifier->verify(
            Rut::parse('60.803.000-K'),
            Rut::parse('76123456-0'),
            DteType::Invoice,
            1,
            now(),
            1000,
        ));
    }

    public function test_returns_false_when_other_status(): void
    {
        $this->mockTokenAuth();
        $this->mockSoapQuery(static::getStub('SiiAuthResponseRejected.xml'));

        $verifier = $this->makeVerifier();

        static::assertFalse($verifier->verify(
            Rut::parse('60.803.000-K'),
            Rut::parse('76123456-0'),
            DteType::Invoice,
            1,
            now(),
            1000,
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | Sad paths
    |--------------------------------------------------------------------------
    */

    public function test_returns_false_on_soap_exception(): void
    {
        $this->mockTokenAuth();

        $this->mock(SoapGateway::class, static function (MockInterface $mock): void {
            $mock->expects('query')->zeroOrMoreTimes()
                ->andThrow(new RuntimeException('SII timeout'));
        });

        $verifier = $this->makeVerifier();

        static::assertFalse($verifier->verify(
            Rut::parse('60.803.000-K'),
            Rut::parse('76123456-0'),
            DteType::Invoice,
            1,
            now(),
            1000,
        ));
    }

    public function test_returns_false_on_token_invalid(): void
    {
        $this->mockTokenAuth();
        $this->mockSoapQuery(static::getStub('SiiAuthResponseTokenInvalid.xml'));

        $verifier = $this->makeVerifier();

        static::assertFalse($verifier->verify(
            Rut::parse('60.803.000-K'),
            Rut::parse('76123456-0'),
            DteType::Invoice,
            1,
            now(),
            1000,
        ));
    }
}
