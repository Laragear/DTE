<?php

namespace Tests\Unit\Gateways;

use DOMDocument;
use Laragear\Dte\Gateways\IecvStatusGateway;
use ReflectionMethod;
use RuntimeException;
use Tests\DatabaseTestCase;

class IecvStatusGatewayTest extends DatabaseTestCase
{
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
}
