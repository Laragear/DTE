<?php

namespace Tests\Unit\Actions\Aec;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use Laragear\Dte\Actions\Aec\CompileAec;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Dte\Data\CessionData;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDtePayload;
use Laragear\Dte\Support\XmlDomFactory;
use Laragear\Rut\Facades\Generator;
use Tests\TestCase;
use Tests\Unit\Certificate\Fixtures\CertificateFixture;

class CompileAecTest extends TestCase
{
    protected DigitalCertificate $certificate;

    protected CertificateFixture $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = CertificateFixture::create();

        $this->certificate = new DigitalCertificate(file_get_contents($this->fixture->path), $this->fixture->password);
    }

    protected function tearDown(): void
    {
        $this->fixture->delete();

        parent::tearDown();
    }

    public function test_builds_the_signed_aec_structure(): void
    {
        $dte = $this->dte();
        $cession = $this->cession();
        $xml = $this->app->make(CompileAec::class)->build(
            $dte,
            $cession,
            $this->receiptXml(),
            Generator::asPeople()->makeOne(),
            'Authorized Signer',
            'issuer@example.com',
            $this->certificate,
            new DateTimeImmutable('2026-08-13 12:00:00'),
        );
        $document = $this->xml($xml);
        $xpath = $this->xpath($document);

        static::assertSame('33', $xpath->evaluate('string(//sii:DocumentoCesion/sii:IdDTE/sii:TipoDTE)'));
        static::assertSame($cession->assigneeRut->formatBasic(), $xpath->evaluate('string(//sii:Cesionario/sii:RUT)'));
        static::assertSame('Invoice Company LLC', $xpath->evaluate('string(//sii:Cedente/sii:RazonSocial)'));
        static::assertSame('DTE', $xpath->evaluate('local-name(//sii:DocumentoDTECedido/*[1])'));
        static::assertSame('Recibo', $xpath->evaluate('local-name(//sii:DocumentoDTECedido/*[2])'));
        static::assertSame('Cesiones', $xpath->evaluate('local-name(//sii:DocumentoAEC/sii:Cesiones)'));
        static::assertSame('DTECedido', $xpath->evaluate('local-name(//sii:DocumentoAEC/sii:Cesiones/*[1])'));
        static::assertSame('Cesion', $xpath->evaluate('local-name(//sii:DocumentoAEC/sii:Cesiones/*[2])'));
        static::assertSame(3, $xpath->query('//ds:Signature')?->length);
    }

    public function test_rejects_document_types_that_cannot_be_transferred(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The DTE type cannot be transferred through an AEC.');

        $this->app->make(CompileAec::class)->build(
            $this->dte(DteType::CreditNote),
            $this->cession(),
            $this->receiptXml(),
            Generator::asPeople()->makeOne(),
            'Authorized Signer',
            'issuer@example.com',
            $this->certificate,
        );
    }

    public function test_requires_a_compiled_signed_dte(): void
    {
        $dte = $this->dte();
        $dte->payload->xml = null;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The AEC requires a compiled DTE with a folio and signed XML payload.');

        $this->app->make(CompileAec::class)->build(
            $dte,
            $this->cession(),
            $this->receiptXml(),
            Generator::asPeople()->makeOne(),
            'Authorized Signer',
            'issuer@example.com',
            $this->certificate,
        );
    }

    public function test_rejects_invalid_receipt_xml(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The [Recibo] XML payload is invalid.');

        $this->app->make(CompileAec::class)->build(
            $this->dte(),
            $this->cession(),
            'invalid xml',
            Generator::asPeople()->makeOne(),
            'Authorized Signer',
            'issuer@example.com',
            $this->certificate,
        );
    }

    public function test_throws_if_xml_payload_missing_element(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The XML payload does not contain a [Recibo] element.');

        $this->app->make(CompileAec::class)->build(
            $this->dte(),
            $this->cession(),
            '<?xml version="1.0"?><WrongElement></WrongElement>',
            Generator::asPeople()->makeOne(),
            'Authorized Signer',
            'issuer@example.com',
            $this->certificate,
        );
    }

    protected function dte(DteType $type = DteType::Invoice): SiiDte
    {
        $dte = new SiiDte([
            'issuer_rut' => Generator::asCompanies()->makeOne()->formatRaw(),
            'receiver_rut' => Generator::asCompanies()->makeOne()->formatRaw(),
            'document_type' => $type,
            'folio' => 123,
            'issued_on' => '2026-08-01',
            'amount_total' => 1190,
        ]);
        $dte->setRelation('payload', new SiiDtePayload([
            'data' => [
                'issuer' => ['name' => 'Invoice Company LLC', 'address' => 'Main Street 123'],
                'receiver' => ['email' => 'debtor@example.com'],
            ],
            'xml' => '<DTE xmlns="http://www.sii.cl/SiiDte" version="1.0"><Documento ID="F123"/></DTE>',
        ]));

        return $dte;
    }

    protected function cession(): CessionData
    {
        return CessionData::make(
            Generator::asCompanies()->makeOne(),
            'Factoring Company LLC',
            'Factor Street 456',
            'factor@example.com',
            1190,
            new DateTimeImmutable('2026-09-01'),
            'Without recourse',
        );
    }

    protected function receiptXml(): string
    {
        return '<Recibo xmlns="http://www.sii.cl/SiiDte" version="1.0"><DocumentoRecibo ID="R123"/></Recibo>';
    }

    protected function xml(string $xml): DOMDocument
    {
        $document = $this->app->make(XmlDomFactory::class)->document();
        static::assertTrue($document->loadXML($xml, LIBXML_NONET));

        return $document;
    }

    protected function xpath(DOMDocument $document): DOMXPath
    {
        $xpath = $this->app->make(XmlDomFactory::class)->xpath($document);
        $xpath->registerNamespace('sii', XmlDomFactory::XML_NAMESPACE);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        return $xpath;
    }
}
