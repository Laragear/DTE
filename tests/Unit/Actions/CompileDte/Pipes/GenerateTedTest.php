<?php

namespace Tests\Unit\Actions\CompileDte\Pipes;

use DOMDocument;
use ErrorException;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Laragear\Dte\Actions\CompileDte\Compilation;
use Laragear\Dte\Actions\CompileDte\Compile;
use Laragear\Dte\Actions\CompileDte\Pipes\GenerateTed;
use Laragear\Dte\Caf\CafParser;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\SiiCaf;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Support\XmlDomFactory;
use Laragear\Dte\Xml\TimbreSigner;
use Laragear\MetaTesting\Pipeline\InteractsWithPipelines;
use Laragear\Rut\Rut;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\DatabaseTestCase;

class GenerateTedTest extends DatabaseTestCase
{
    use InteractsWithPipelines;

    protected function makeCompilation(): Compilation
    {
        $cafXml = '<AUTORIZACION><CAF><DA><RE>11111111-1</RE></DA></CAF></AUTORIZACION>';

        $caf = SiiCaf::factory()->has(
            SiiDte::factory([
                'issuer_rut' => Rut::parse('11111111-1'),
                'receiver_rut' => Rut::parse('22222222-2'),
                'document_type' => DteType::Invoice,
                'folio' => 123,
                'amount_total' => 11900,
            ]),
            'dtes'
        )->create(['xml' => $cafXml]);

        $dte = $caf->dtes->first();

        $dte->payload()->create([
            'data' => [
                'issued_on' => '2024-01-15',
                'receiver' => [
                    'name' => 'Receiver Company Name',
                ],
                'items' => [
                    [
                        'name' => 'Test Item 1',
                    ],
                ],
            ],
        ]);

        $compilation = new Compilation($dte);
        $document = $this->app->make(XmlDomFactory::class)->document('1.0', 'ISO-8859-1');
        $document->appendChild($document->createElementNS(XmlDomFactory::XML_NAMESPACE, 'DTE'));
        $compilation->document = $document;

        return $compilation;
    }

    /*
    |--------------------------------------------------------------------------
    | Happy paths
    |--------------------------------------------------------------------------
    */

    public function test_generates_ted_and_appends_to_compilation(): void
    {
        $this->travelTo(Carbon::make('2024-02-01 12:00:00', 'America/Santiago'));

        $compilation = $this->makeCompilation();

        $this->mock(CafParser::class)->expects('parse')->andReturn([
            'private_key' => 'fake_private_key',
            'xml' => '<AUTORIZACION><CAF><DA><RE>11111111-1</RE></DA></CAF></AUTORIZACION>',
        ]);

        $this->mock(TimbreSigner::class)->expects('sign')->andReturn('fake_signature');

        $this->pipeline(Compile::class)
            ->isolatePipe(GenerateTed::class)
            ->send($compilation)
            ->assertPassable(function (Compilation $result) {
                static::assertNotNull($result->ted);
                static::assertEquals('TED', $result->ted->localName);
                static::assertEquals('1.0', $result->ted->getAttribute('version'));

                $xml = $result->document->saveXML($result->ted);

                // Assert DD fields
                static::assertStringContainsString('<RE>11111111-1</RE>', $xml);
                static::assertStringContainsString('<TD>33</TD>', $xml);
                static::assertStringContainsString('<F>123</F>', $xml);
                static::assertStringContainsString('<FE>2024-01-15</FE>', $xml);
                static::assertStringContainsString('<RR>22222222-2</RR>', $xml);
                static::assertStringContainsString('<RSR>Receiver Company Name</RSR>', $xml);
                static::assertStringContainsString('<MNT>11900</MNT>', $xml);
                static::assertStringContainsString('<IT1>Test Item 1</IT1>', $xml);
                static::assertStringContainsString('<CAF><DA><RE>11111111-1</RE></DA></CAF>', $xml);
                static::assertStringContainsString('<TSTED>2024-02-01T12:00:00</TSTED>', $xml);

                // Assert FRMT signature
                static::assertStringContainsString('<FRMT algoritmo="SHA1withRSA">fake_signature</FRMT>', $xml);

                return true;
            });
    }

    public function test_caf_data_nulled_after_ted_generation(): void
    {
        $compilation = $this->makeCompilation();

        $this->mock(CafParser::class)->expects('parse')->andReturn([
            'private_key' => 'cached_private_key',
            'xml' => '<AUTORIZACION><CAF><DA><RE>11111111-1</RE></DA></CAF></AUTORIZACION>',
        ]);

        $this->mock(TimbreSigner::class)->expects('sign')->andReturn('fake_signature');

        $this->pipeline(Compile::class)
            ->isolatePipe(GenerateTed::class)
            ->send($compilation)
            ->assertPassable(function (Compilation $result) {
                static::assertNull($result->cafData);
                static::assertNotNull($result->ted);

                return true;
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Angry paths
    |--------------------------------------------------------------------------
    */

    public function test_throws_exception_if_caf_is_missing(): void
    {
        $compilation = $this->makeCompilation();

        $compilation->dte->sii_caf_id = null;
        $compilation->dte->unsetRelation('caf');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('The DTE does not have an allocated CAF.');

        $this->pipeline(Compile::class)
            ->isolatePipe(GenerateTed::class)
            ->send($compilation);
    }

    public function test_throws_exception_if_caf_xml_lacks_caf_element(): void
    {
        $compilation = $this->makeCompilation();
        $compilation->dte->caf->xml = '<AUTORIZACION></AUTORIZACION>';

        $this->mock(CafParser::class)
            ->expects('parse')
            ->andThrow(new InvalidArgumentException('The XML document does not contain a CAF node.'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The XML document does not contain a CAF node.');

        $this->pipeline(Compile::class)
            ->isolatePipe(GenerateTed::class)
            ->send($compilation);
    }

    public function test_throws_on_malformed_caf_xml(): void
    {
        $compilation = $this->makeCompilation();
        $compilation->dte->caf->xml = 'not xml at all';

        $this->mock(CafParser::class)
            ->expects('parse')
            ->andThrow(new RuntimeException('Unable to parse the allocated CAF XML.'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Unable to parse the allocated CAF XML.');

        $this->pipeline(Compile::class)
            ->isolatePipe(GenerateTed::class)
            ->send($compilation);
    }

    public function test_throws_when_caf_xml_load_fails(): void
    {
        $compilation = $this->makeCompilation();

        // CafParser returns valid data but with invalid XML for DOMDocument
        $this->mock(CafParser::class)
            ->expects('parse')
            ->andReturn([
                'private_key' => 'fake_private_key',
                'xml' => '<AUTORIZACION><CAF><DA><RE>11111111-1</RE></DA></CAF></AUTORIZACION>',
            ]);

        $this->mock(TimbreSigner::class)->expects('sign')->zeroOrMoreTimes()->andReturn('fake_signature');

        // Mock XmlDomFactory to return a DOMDocument where loadXML throws
        $mockDocument = Mockery::mock(DOMDocument::class);
        $mockDocument->expects('loadXML')->andThrow(new ErrorException('DOMDocument::loadXML(): xmlParseChunk'));

        $this->mock(XmlDomFactory::class, static function (MockInterface $mock) use ($mockDocument) {
            $mock->expects('document')->with('1.0', 'ISO-8859-1')->andReturn($mockDocument);
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Unable to parse the allocated CAF XML.');

        $this->pipeline(Compile::class)
            ->isolatePipe(GenerateTed::class)
            ->send($compilation);
    }
}
