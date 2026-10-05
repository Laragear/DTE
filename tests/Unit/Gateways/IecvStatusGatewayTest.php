<?php

namespace Tests\Unit\Gateways;

use DOMDocument;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Laragear\Dte\Data\Token;
use Laragear\Dte\Enums\DteEnvironment;
use Laragear\Dte\Environment\EnvironmentResolver;
use Laragear\Dte\Gateways\Exceptions\TokenInvalidException;
use Laragear\Dte\Gateways\IecvStatusGateway;
use Laragear\Dte\Gateways\SoapGateway;
use Laragear\Dte\Models\SiiIecv;
use Laragear\Dte\Support\TokenAuthenticator;
use Mockery;
use Mockery\MockInterface;
use ReflectionMethod;
use RuntimeException;
use Tests\DatabaseTestCase;

class IecvStatusGatewayTest extends DatabaseTestCase
{
    protected function makeEnvironmentResolver(DteEnvironment $environment): EnvironmentResolver
    {
        $app = Mockery::mock(Application::class);
        $app->allows('environment')->with(DteEnvironment::Production->value)->andReturn($environment === DteEnvironment::Production);
        $app->allows('environment')->withNoArgs()->andReturn($environment->value);

        return new EnvironmentResolver(new Repository([
            'dte' => ['environment' => $environment->value],
        ]), $app);
    }

    /**
     * The gateway parses the SII response, which is covered end to end here
     * against the real XSD and a real SimpleXML parse.
     */
    protected function invokeParse(string $xml): mixed
    {
        $gateway = $this->app->make(IecvStatusGateway::class);

        $method = new ReflectionMethod($gateway, 'parse');

        return $method->invoke($gateway, $xml);
    }

    protected function acceptedResponse(): string
    {
        return '<?xml version="1.0" encoding="ISO-8859-1"?>'
            .'<ResultadoEnvioLibro><Identificacion>'
            .'<TrackId>123456</TrackId><RutEmisor>76692025-K</RutEmisor>'
            .'<RutEnvia>16678032-2</RutEnvia><TmstRecepcion>2026-10-04T18:59:35</TmstRecepcion>'
            .'<EstadoEnvio>EPR</EstadoEnvio><TipoSegmento>1</TipoSegmento><NroSegmento>1</NroSegmento>'
            .'<TipoLibro>MENSUAL</TipoLibro><TipoOperacion>VENTA</TipoOperacion>'
            .'<PeriodoTributario>2026-09</PeriodoTributario><EstadoLibro>CTR</EstadoLibro>'
            .'</Identificacion></ResultadoEnvioLibro>';
    }

    /*
    |--------------------------------------------------------------------------
    | Happy paths
    |--------------------------------------------------------------------------
    */

    public function test_parses_an_accepted_response(): void
    {
        $status = $this->invokeParse($this->acceptedResponse());

        static::assertSame('EPR', $status->sendState);
        static::assertSame('CTR', $status->bookState);
        static::assertTrue($status->isAccepted());
        static::assertFalse($status->hasBookErrors());
        static::assertSame(123456, $status->trackId);
    }

    public function test_parses_a_namespaced_response(): void
    {
        $xml = str_replace(
            '<ResultadoEnvioLibro>',
            '<ResultadoEnvioLibro xmlns="http://www.sii.cl/SiiDte">',
            $this->acceptedResponse()
        );

        static::assertTrue($this->invokeParse($xml)->isAccepted());
    }

    public function test_collects_the_book_errors(): void
    {
        $xml = str_replace(
            '</ResultadoEnvioLibro>',
            '<ErrorEnvioLibro><DetErrEnvio>Error 1</DetErrEnvio><DetErrEnvio>Error 2</DetErrEnvio></ErrorEnvioLibro></ResultadoEnvioLibro>',
            $this->acceptedResponse()
        );

        $status = $this->invokeParse($xml);

        static::assertSame(['Error 1', 'Error 2'], $status->errors);
        static::assertTrue($status->hasBookErrors());
    }

    public function test_skips_blank_book_errors(): void
    {
        $xml = str_replace(
            '</ResultadoEnvioLibro>',
            '<ErrorEnvioLibro><DetErrEnvio>   </DetErrEnvio><DetErrEnvio>Error 1</DetErrEnvio></ErrorEnvioLibro></ResultadoEnvioLibro>',
            $this->acceptedResponse()
        );

        $status = $this->invokeParse($xml);

        static::assertSame(['Error 1'], $status->errors);
    }

    public function test_validates_the_response_against_the_shipped_xsd(): void
    {
        $document = new DOMDocument;
        $document->loadXML($this->acceptedResponse());

        static::assertTrue(
            $document->schemaValidate(__DIR__.'/../../../resources/xsd/RespSIILibros_v10.xsd'),
            'The SII books response must validate against RespSIILibros_v10.xsd.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Sad paths
    |--------------------------------------------------------------------------
    */

    public function test_throws_on_an_empty_response(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('SII returned an empty response for the book status query.');

        $this->invokeParse('   ');
    }

    public function test_throws_when_estado_envio_is_missing(): void
    {
        $xml = '<ResultadoEnvioLibro><Identificacion><TrackId>1</TrackId></Identificacion></ResultadoEnvioLibro>';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('The SII book status response did not contain an EstadoEnvio element.');

        $this->invokeParse($xml);
    }

    /*
    |--------------------------------------------------------------------------
    | Status query
    |--------------------------------------------------------------------------
    */

    public function test_returns_a_local_status_without_contacting_the_sii(): void
    {
        $book = SiiIecv::factory()->uploaded()->create();

        $this->instance(EnvironmentResolver::class, $this->makeEnvironmentResolver(DteEnvironment::Local));

        // The SOAP client must never be reached in a local environment.
        $soap = $this->mock(SoapGateway::class);
        $soap->expects('query')->zeroOrMoreTimes();

        $status = $this->app->make(IecvStatusGateway::class)->trackStatus($book);

        static::assertSame('EPR', $status->sendState);
        static::assertSame('CTR', $status->bookState);
        static::assertSame(1, $status->trackId);
        static::assertSame('<fake-iecv-status/>', $status->raw);
    }

    public function test_queries_the_sii_and_parses_the_response(): void
    {
        $book = SiiIecv::factory()->uploaded()->create();

        $this->instance(EnvironmentResolver::class, $this->makeEnvironmentResolver(DteEnvironment::Production));

        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock): void {
            $mock->expects('token')->zeroOrMoreTimes()
                ->andReturn(new Token('sii-token', time() + 3600));
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(static fn ($request, $issuer) => $request());
        });

        $soap = $this->mock(SoapGateway::class);
        $soap->expects('query')
            ->once()
            ->withArgs(function ($token, $service, $operation, $body, $baseUrl) use ($book) {
                static::assertSame(IecvStatusGateway::QUERY_SERVICE, $service);
                static::assertSame('getEstUp', $operation);
                static::assertSame($book->track_id, $body['TrackId']);

                return true;
            })
            ->andReturn($this->acceptedResponse());

        $status = $this->app->make(IecvStatusGateway::class)->trackStatus($book);

        static::assertSame('EPR', $status->sendState);
        static::assertSame('CTR', $status->bookState);
        static::assertSame(123456, $status->trackId);
    }

    public function test_reads_the_result_element_from_a_soap_object_response(): void
    {
        $book = SiiIecv::factory()->uploaded()->create();

        $this->instance(EnvironmentResolver::class, $this->makeEnvironmentResolver(DteEnvironment::Production));

        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock): void {
            $mock->expects('token')->zeroOrMoreTimes()
                ->andReturn(new Token('sii-token', time() + 3600));
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(static fn ($request, $issuer) => $request());
        });

        // Some SII operations answer with a result object holding the payload.
        $result = new class($this->acceptedResponse())
        {
            public function __construct(public string $getEstUpResult)
            {
                //
            }
        };

        $soap = $this->mock(SoapGateway::class);
        $soap->expects('query')->zeroOrMoreTimes()->andReturn($result);

        $status = $this->app->make(IecvStatusGateway::class)->trackStatus($book);

        static::assertSame('EPR', $status->sendState);
    }

    public function test_throws_when_the_sii_invalidates_the_token(): void
    {
        $book = SiiIecv::factory()->uploaded()->create();

        $this->instance(EnvironmentResolver::class, $this->makeEnvironmentResolver(DteEnvironment::Production));

        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock): void {
            $mock->expects('token')->zeroOrMoreTimes()
                ->andReturn(new Token('sii-token', time() + 3600));
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(static fn ($request, $issuer) => $request());
        });

        $soap = $this->mock(SoapGateway::class);
        $soap->expects('query')->zeroOrMoreTimes()->andReturn(
            '<Respuesta><ESTADO>001</ESTADO></Respuesta>'
        );

        $this->expectException(TokenInvalidException::class);
        $this->expectExceptionMessageIs('SII SOAP token was invalidated (001/002/003).');

        $this->app->make(IecvStatusGateway::class)->trackStatus($book);
    }
}
