<?php

namespace Tests\Unit\Models\Concerns;

use DOMDocument;
use InvalidArgumentException;
use Laragear\Dte\Support\XmlDomFactory;
use LogicException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Unit\Models\Concerns\Fixtures\DummyXmlPayloadModel;
use Throwable;

class HasXmlPayloadTest extends TestCase
{
    /*
     |--------------------------------------------------------------------------
     | Happy paths
     |--------------------------------------------------------------------------
     */

    public static function providesMalformedXmlMethods(): iterable
    {
        return [
            'DOM Document' => ['toDomDocument'],
            'Simple XML' => ['toSimpleXml'],
        ];
    }

    /**
     * Create a dummy model with an XML payload.
     */
    protected function model(string $xml): DummyXmlPayloadModel
    {
        return new DummyXmlPayloadModel(['xml' => $xml]);
    }

    public function test_parses_xml_into_a_dom_document(): void
    {
        $document = $this->model('<DTE><Folio>123</Folio></DTE>')->toDomDocument();

        static::assertSame('DTE', $document->documentElement?->tagName);
        static::assertSame('123', $document->getElementsByTagName('Folio')->item(0)?->textContent);
    }

    public function test_parses_xml_into_a_simple_xml_element(): void
    {
        $xml = $this->model('<DTE><Folio>123</Folio></DTE>')->toSimpleXml();

        static::assertSame('123', (string) $xml->Folio);
    }

    #[DataProvider('providesMalformedXmlMethods')]
    public function test_throws_when_xml_is_malformed(string $method): void
    {
        $model = $this->model('<DTE>');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The model XML payload is malformed.');

        $model->{$method}();
    }

    public function test_throws_when_xml_is_absent(): void
    {
        $model = new DummyXmlPayloadModel;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The model XML payload is malformed.');

        try {
            $model->toDomDocument();
        } catch (Throwable $e) {
            static::assertSame('The model does not contain an XML payload.', $e->getPrevious()->getMessage());

            throw $e;
        }
    }

    public function test_throws_when_load_xml_returns_false_without_throwing(): void
    {
        $document = Mockery::mock(DOMDocument::class);
        $document->expects('loadXml')->andReturnFalse();

        $this->mock(XmlDomFactory::class)->expects('document')->andReturn($document);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The model XML payload is malformed.');

        $this->model('<test>')->toDomDocument();
    }
}
