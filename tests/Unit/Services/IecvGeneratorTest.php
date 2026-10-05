<?php

namespace Tests\Unit\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use Laragear\Dte\Builders\Iecv\IecvBuilder;
use Laragear\Dte\Builders\Iecv\IecvPurchaseData;
use Laragear\Dte\Certificate\CertificateResolver;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\IecvType;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Proxies\LibxmlProxy;
use Laragear\Dte\Services\IecvGenerator;
use Laragear\Dte\Support\XmlDomFactory;
use Laragear\Dte\Xml\XmlSigner;
use Laragear\Dte\Xml\XsdValidator;
use Laragear\Rut\Rut;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class IecvGeneratorTest extends TestCase
{
    protected function makeGenerator(
        ?IecvBuilder $builder = null,
        ?CertificateResolver $certificate = null,
        ?XmlDomFactory $xml = null,
        ?XsdValidator $xsd = null,
        ?XmlSigner $signer = null,
    ): IecvGenerator {
        return new IecvGenerator(
            $certificate ?? $this->mock(CertificateResolver::class),
            $builder ?? $this->mock(IecvBuilder::class),
            $xml ?? $this->mock(XmlDomFactory::class),
            $xsd ?? $this->mock(XsdValidator::class),
            $signer ?? $this->mock(XmlSigner::class),
        );
    }

    protected function validEnvioLibroXml(): string
    {
        return '<?xml version="1.0" encoding="ISO-8859-1"?><EnvioLibro ID="test"></EnvioLibro>';
    }

    /**
     * A namespaced IECV document, as produced by IecvBuilder.
     */
    protected function namespacedEnvioLibroXml(): string
    {
        return '<?xml version="1.0" encoding="ISO-8859-1"?>'
            .'<LibroCompraVenta xmlns="'.XmlDomFactory::XML_NAMESPACE.'" version="1.0">'
            .'<EnvioLibro ID="test"></EnvioLibro>'
            .'</LibroCompraVenta>';
    }

    /*
    |--------------------------------------------------------------------------
    | Happy paths
    |--------------------------------------------------------------------------
    */

    public function test_generate_sales_builds_and_signs_xml(): void
    {
        $xmlString = $this->validEnvioLibroXml();
        $issuer = Rut::parse('76123456-0');
        $senderRut = Rut::parse('76123456-0');
        $dtes = collect([$this->mock(SiiDte::class)]);

        $builder = $this->mock(IecvBuilder::class);
        $builder->expects('build')
            ->with($dtes, IecvType::Sales, '2024-01', '2024-01-01', 1, $senderRut, [])

            ->andReturn($xmlString);

        $certificate = $this->mock(CertificateResolver::class);
        $cert = new DigitalCertificate('fake', 'fake');
        $certificate->expects('resolve')->with($issuer)->andReturn($cert);

        $xsd = $this->mock(XsdValidator::class);
        $xsd->expects('validate');

        $signer = $this->mock(XmlSigner::class);
        $signer->expects('sign');

        $dom = new DOMDocument('1.0', 'ISO-8859-1');
        $dom->loadXML($xmlString);

        $xpath = $this->mock(DOMXPath::class);
        $xpath->expects('query')
            ->with("//*[local-name()='EnvioLibro']")
            ->andReturnUsing(fn() => $dom->getElementsByTagName('EnvioLibro'));

        $xml = $this->mock(XmlDomFactory::class, static function (MockInterface $mock) use ($dom, $xpath): void {
            $mock->expects('document')->andReturn($dom);
            $mock->expects('xpath')->with($dom)->andReturn($xpath);
        });

        $generator = $this->makeGenerator($builder, $certificate, $xml, $xsd, $signer);

        $result = $generator->generateSales(
            $issuer, $dtes, '2024-01', '2024-01-01', 1, $senderRut,
        );

        static::assertIsString($result);
        static::assertStringContainsString('EnvioLibro', $result);
    }

    public function test_generate_purchases_builds_and_signs_xml(): void
    {
        $xmlString = $this->validEnvioLibroXml();
        $issuer = Rut::parse('76123456-0');
        $senderRut = Rut::parse('76123456-0');
        $entries = [new IecvPurchaseData(DteType::PurchaseInvoice, 1, '2024-01-01', $issuer)];

        $builder = $this->mock(IecvBuilder::class);
        $builder->expects('buildPurchases')
            ->with($entries, '2024-01', '2024-01-01', 1, $issuer, $senderRut, [])

            ->andReturn($xmlString);

        $certificate = $this->mock(CertificateResolver::class);
        $cert = new DigitalCertificate('fake', 'fake');
        $certificate->expects('resolve')->with($issuer)->andReturn($cert);

        $xsd = $this->mock(XsdValidator::class);
        $xsd->expects('validate');

        $signer = $this->mock(XmlSigner::class);
        $signer->expects('sign');

        $dom = new DOMDocument('1.0', 'ISO-8859-1');
        $dom->loadXML($xmlString);

        $xpath = $this->mock(DOMXPath::class);
        $xpath->expects('query')
            ->with("//*[local-name()='EnvioLibro']")
            ->andReturnUsing(fn() => $dom->getElementsByTagName('EnvioLibro'));

        $xml = $this->mock(XmlDomFactory::class, static function (MockInterface $mock) use ($dom, $xpath): void {
            $mock->expects('document')->andReturn($dom);
            $mock->expects('xpath')->with($dom)->andReturn($xpath);
        });

        $generator = $this->makeGenerator($builder, $certificate, $xml, $xsd, $signer);

        $result = $generator->generatePurchases(
            $issuer, $entries, '2024-01', '2024-01-01', 1, $senderRut,
        );

        static::assertIsString($result);
        static::assertStringContainsString('EnvioLibro', $result);
    }

    /*
    |--------------------------------------------------------------------------
    | Sad paths
    |--------------------------------------------------------------------------
    */

    public function test_sign_xml_throws_when_no_certificate(): void
    {
        $issuer = Rut::parse('76123456-0');
        $dtes = collect([$this->mock(SiiDte::class)]);

        $builder = $this->mock(IecvBuilder::class);
        $builder->expects('build')->andReturn($this->validEnvioLibroXml());

        $certificate = $this->mock(CertificateResolver::class);
        $certificate->expects('resolve')->andReturn(null);

        $generator = $this->makeGenerator($builder, $certificate);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs("No certificate was found for [$issuer].");

        $generator->generateSales(
            $issuer, $dtes, '2024-01', '2024-01-01', 1, $issuer,
        );
    }

    public function test_parse_document_throws_on_invalid_xml(): void
    {
        $issuer = Rut::parse('76123456-0');
        $dtes = collect([$this->mock(SiiDte::class)]);

        $builder = $this->mock(IecvBuilder::class);
        $builder->expects('build')->andReturn('not valid xml at all');

        $certificate = $this->mock(CertificateResolver::class);
        $cert = new DigitalCertificate('fake', 'fake');
        $certificate->expects('resolve')->andReturn($cert);

        $xsd = $this->mock(XsdValidator::class);
        $xsd->expects('validate');

        $domMock = $this->mock(DOMDocument::class);
        $domMock->expects('loadXML')->andReturn(false);

        $xml = $this->mock(XmlDomFactory::class);
        $xml->expects('document')->andReturn($domMock);

        $generator = $this->makeGenerator($builder, $certificate, $xml, $xsd);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Unable to parse the IECV XML.');

        $generator->generateSales(
            $issuer, $dtes, '2024-01', '2024-01-01', 1, $issuer,
        );
    }

    public function test_find_envio_libro_throws_when_missing(): void
    {
        $issuer = Rut::parse('76123456-0');
        $dtes = collect([$this->mock(SiiDte::class)]);

        $builder = $this->mock(IecvBuilder::class);
        $builder->expects('build')->andReturn('<?xml version="1.0"?><Root></Root>');

        $certificate = $this->mock(CertificateResolver::class);
        $cert = new DigitalCertificate('fake', 'fake');
        $certificate->expects('resolve')->andReturn($cert);

        $xsd = $this->mock(XsdValidator::class);
        $xsd->expects('validate');

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadXML('<?xml version="1.0"?><Root></Root>');

        $xpathMock = $this->mock(DOMXPath::class);
        $xpathMock->expects('query')->with("//*[local-name()='EnvioLibro']")->andReturn(new DOMNodeList);

        $xml = $this->mock(XmlDomFactory::class, static function (MockInterface $mock) use ($dom, $xpathMock): void {
            $mock->expects('document')->andReturn($dom);
            $mock->expects('xpath')->with($dom)->andReturn($xpathMock);
        });

        $generator = $this->makeGenerator($builder, $certificate, $xml, $xsd);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Unable to find EnvioLibro in IECV XML.');

        $generator->generateSales(
            $issuer, $dtes, '2024-01', '2024-01-01', 1, $issuer,
        );
    }

    public function test_sign_xml_finds_envio_libro_within_default_namespace(): void
    {
        $xmlString = $this->namespacedEnvioLibroXml();
        $issuer = Rut::parse('76123456-0');
        $senderRut = Rut::parse('76123456-0');
        $dtes = collect([$this->mock(SiiDte::class)]);

        $builder = $this->mock(IecvBuilder::class);
        $builder->expects('build')->andReturn($xmlString);

        $certificate = $this->mock(CertificateResolver::class);
        $certificate->expects('resolve')->andReturn(new DigitalCertificate('fake', 'fake'));

        $xsd = $this->mock(XsdValidator::class);
        $xsd->expects('validate');

        $signed = null;
        $signer = $this->mock(XmlSigner::class);
        $signer->expects('sign')->andReturnUsing(
            static function (DOMElement $element) use (&$signed): DOMNode {
                return $signed = $element;
            }
        );

        $xml = new XmlDomFactory(new LibxmlProxy);

        $generator = $this->makeGenerator($builder, $certificate, $xml, $xsd, $signer);

        $generator->generateSales(
            $issuer, $dtes, '2024-01', '2024-01-01', 1, $senderRut,
        );

        static::assertInstanceOf(DOMElement::class, $signed);
        static::assertSame('EnvioLibro', $signed->localName);
    }
}
