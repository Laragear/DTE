<?php

namespace Tests\Unit\Gateways;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Laragear\Dte\Data\Token;
use Laragear\Dte\Enums\DteEnvironment as DteEnv;
use Laragear\Dte\Enums\InboundDteStatus;
use Laragear\Dte\Environment\EnvironmentResolver;
use Laragear\Dte\Gateways\Exceptions\TokenInvalidException;
use Laragear\Dte\Gateways\ReclamoWebserviceGateway;
use Laragear\Dte\Models\SiiInboundDocument;
use Laragear\Dte\Proxies\SoapProxy;
use Laragear\Dte\Support\TokenAuthenticator;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use SoapClient;
use Tests\DatabaseTestCase;

class ReclamoWebserviceGatewayTest extends DatabaseTestCase
{
    protected function makeGateway(DteEnv $environment = DteEnv::Production): ReclamoWebserviceGateway
    {
        $token = new Token('sii-reclamo-token', time() + 3600);
        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock) use ($token): void {
            $mock->expects('token')->zeroOrMoreTimes()->andReturn($token);
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(fn($request, $issuer) => $request());
        });

        $mockClient = Mockery::mock(SoapClient::class, static function (MockInterface $mock): void {
            $mock->expects('__setSoapHeaders');
            $mock
                ->expects('__soapCall')
                ->with('ReclamoDoc', Mockery::any())
                ->andReturn((object) [
                    'ReclamoDocResult' => (object) ['status' => '0'],
                ]);
        });

        $this->mock(SoapProxy::class, static function (MockInterface $mock) use ($mockClient): void {
            $mock->expects('withWsdl')->andReturnSelf();
            $mock->expects('build')->andReturn($mockClient);
        });

        $this->instance(EnvironmentResolver::class, $this->makeEnvironmentResolver($environment));

        return $this->app->make(ReclamoWebserviceGateway::class);
    }

    protected function makeEnvironmentResolver(DteEnv $environment): EnvironmentResolver
    {
        $app = Mockery::mock(Application::class, static function (MockInterface $mock) use ($environment): void {
            $mock->allows('environment')
                ->with(DteEnv::Production->value)
                ->andReturn($environment === DteEnv::Production);
            $mock->allows('environment')
                ->withNoArgs()
                ->andReturn($environment->value);
        });

        return new EnvironmentResolver(new Repository([
            'dte' => ['environment' => $environment->value],
        ]), $app);
    }

    protected function makeDocument(): SiiInboundDocument
    {
        return SiiInboundDocument::factory()->create([
            'status' => InboundDteStatus::TechnicalAccepted,
        ]);
    }

    public function test_accepts_a_document_commercially(): void
    {
        $document = $this->makeDocument();
        $gateway = $this->makeGateway();

        $gateway->accept($document);

        static::assertTrue(true);
    }

    public function test_does_nothing_in_local_environment(): void
    {
        $token = new Token('sii-token', time() + 3600);
        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock) use ($token): void {
            $mock->expects('token')->zeroOrMoreTimes()->andReturn($token);
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(fn($request, $issuer) => $request());
        });

        $this->mock(SoapProxy::class)->shouldNotReceive('build');

        $this->instance(EnvironmentResolver::class, $this->makeEnvironmentResolver(DteEnv::Local));

        $gateway = $this->app->make(ReclamoWebserviceGateway::class);

        $document = $this->makeDocument();

        $gateway->reject($document);

        static::assertTrue(true);
    }

    public function test_rejects_a_document(): void
    {
        $document = $this->makeDocument();
        $gateway = $this->makeGateway();

        // No exception means success
        $gateway->reject($document, 'Factura errónea');
    }

    public function test_rejects_for_missing_goods(): void
    {
        $document = $this->makeDocument();
        $gateway = $this->makeGateway();

        $gateway->rejectGoods($document, 'Mercadería no recibida');
    }

    public function test_throws_on_non_zero_reclamo_status(): void
    {
        $document = $this->makeDocument();
        $token = new Token('sii-token', time() + 3600);
        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock) use ($token): void {
            $mock->expects('token')->zeroOrMoreTimes()->andReturn($token);
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(fn($request, $issuer) => $request());
        });

        $mockClient = Mockery::mock(SoapClient::class, static function (MockInterface $mock): void {
            $mock->expects('__setSoapHeaders');
            $mock
                ->expects('__soapCall')
                ->andReturn((object) [
                    'ReclamoDocResult' => (object) ['status' => '3'],
                ]);
        });

        $this->mock(SoapProxy::class, static function (MockInterface $mock) use ($mockClient): void {
            $mock->expects('withWsdl')->andReturnSelf();
            $mock->expects('build')->andReturn($mockClient);
        });

        $this->instance(EnvironmentResolver::class, $this->makeEnvironmentResolver(DteEnv::Production));

        $gateway = $this->app->make(ReclamoWebserviceGateway::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs(sprintf(
            'SII Reclamo WS returned non-zero status 3 for document %s/%d.',
            $document->issuer_rut->formatBasic(),
            $document->folio,
        ));

        $gateway->reject($document);
    }

    public function test_rejects_for_partial_missing_goods(): void
    {
        $document = $this->makeDocument();
        $gateway = $this->makeGateway();

        $gateway->rejectPartial($document, 'Falta parcial de mercadería');
    }

    public function test_confirms_goods_receipt(): void
    {
        $document = $this->makeDocument();
        $gateway = $this->makeGateway();

        $gateway->confirmGoodsReceipt($document, 'Mercadería recibida');
    }

    public function test_throws_on_invalid_token_status(): void
    {
        $document = $this->makeDocument();
        $token = new Token('sii-token', time() + 3600);
        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock) use ($token): void {
            $mock->expects('token')->zeroOrMoreTimes()->andReturn($token);
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(fn($request, $issuer) => $request());
        });

        $mockClient = Mockery::mock(SoapClient::class, static function (MockInterface $mock): void {
            $mock->expects('__setSoapHeaders');
            $mock
                ->expects('__soapCall')
                ->andReturn((object) [
                    'ReclamoDocResult' => (object) ['status' => '001'],
                ]);
        });

        $this->mock(SoapProxy::class, static function (MockInterface $mock) use ($mockClient): void {
            $mock->expects('withWsdl')->andReturnSelf();
            $mock->expects('build')->andReturn($mockClient);
        });

        $this->instance(EnvironmentResolver::class, $this->makeEnvironmentResolver(DteEnv::Production));

        $gateway = $this->app->make(ReclamoWebserviceGateway::class);

        $this->expectException(TokenInvalidException::class);

        $gateway->reject($document);
    }
}
