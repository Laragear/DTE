<?php

namespace Tests\Unit\Certification;

use Laragear\Dte\Builders\Iecv\IecvBuilder;
use Laragear\Dte\Builders\Iecv\IecvPurchaseData;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\IecvProperty;
use Laragear\Dte\Enums\IecvType;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Support\XmlDomFactory;
use Laragear\Rut\Rut;
use Tests\DatabaseTestCase;

class IecvBuilderTest extends DatabaseTestCase
{
    /**
     * Whether this libxml build can compile the SII schema's decimal facets.
     *
     * The official SII schemas cap `MontoType` at 30 integer digits
     * (`999999999999999999999999999999.9999`). XSD 1.0 leaves `xs:decimal`
     * unbounded, so older libxml accepts it, while newer builds enforce a
     * fixed precision and reject the facet as an invalid literal -- failing
     * schema compilation before the generated document is ever examined.
     * That is a property of the validator, not of the XML under test.
     */
    protected function libxmlRejectsSiiDecimalFacet(): bool
    {
        $schema = <<<'XSD'
            <?xml version="1.0"?>
            <xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
              <xs:element name="MontoType">
                <xs:simpleType>
                  <xs:restriction base="xs:decimal">
                    <xs:maxInclusive value="999999999999999999999999999999.9999"/>
                  </xs:restriction>
                </xs:simpleType>
              </xs:element>
            </xs:schema>
            XSD;

        $path = tempnam(sys_get_temp_dir(), 'sii-decimal-').'.xsd';

        file_put_contents($path, $schema);

        $probe = new \DOMDocument;
        $probe->loadXML('<?xml version="1.0"?><MontoType>1000</MontoType>');

        $previous = libxml_use_internal_errors(true);

        try {
            $compiles = $probe->schemaValidate($path);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            unlink($path);
        }

        return $compiles === false;
    }

    /*
    |--------------------------------------------------------------------------
    | Happy paths
    |--------------------------------------------------------------------------
    */

    public function test_builds_valid_iecv_sales_xml_against_schema(): void
    {
        if ($this->libxmlRejectsSiiDecimalFacet()) {
            $this->markTestSkipped(
                'This libxml build rejects the 30-digit SII MontoType facet, so the official schema cannot compile here.'
            );
        }

        $dtes = SiiDte::factory()
            ->count(2)
            ->sequence(
                [
                    'document_type' => DteType::Invoice,
                    'folio' => 1,
                    'issuer_rut' => '76123456-0',
                    'receiver_rut' => '55666777-8',
                    'issued_on' => '2023-10-05',
                    'amount_net' => 1000,
                    'amount_exempt' => 0,
                    'amount_taxes' => 190,
                    'amount_total' => 1190,
                ],
                [
                    'document_type' => DteType::Invoice,
                    'folio' => 2,
                    'issuer_rut' => '76123456-0',
                    'receiver_rut' => '55666777-8',
                    'issued_on' => '2023-10-06',
                    'amount_net' => 2000,
                    'amount_exempt' => 0,
                    'amount_taxes' => 380,
                    'amount_total' => 2380,
                ]
            )
            ->createMany();

        $xml = $this->app->make(IecvBuilder::class)->build(
            dtes: $dtes,
            type: IecvType::Sales,
            period: '2023-10',
            resolutionDate: '2020-01-01',
            resolutionNumber: 1234,
            senderRut: Rut::parse('55666777-8')
        );

        $dom = $this->app->make(XmlDomFactory::class)->document();
        $dom->loadXML($xml);

        // Append dummy signature to pass schema validation
        $signatureXml = static::getStub('FakeIecvXmlSignature.xml');

        $sigDom = $this->app->make(XmlDomFactory::class)->document();
        $sigDom->loadXML($signatureXml);

        $dom->documentElement->appendChild($dom->importNode($sigDom->documentElement, true));

        $schemaPath = static::STUBS.'/LibroCV_v10.xsd';

        static::assertTrue(
            $dom->schemaValidate($schemaPath),
            'The generated IECV XML does not match the EnvioLibro_v10.xsd schema.'
        );

        // Sales book must have FolioNotificacion = 1
        static::assertStringContainsString('<FolioNotificacion>1</FolioNotificacion>', $xml);
        // Tax rate should be derived from config (default 19.00)
        static::assertStringContainsString('<TasaImp>19.00</TasaImp>', $xml);
        // TotMntIVA must always be present (not conditionally omitted)
        static::assertStringContainsString('<TotMntIVA>570</TotMntIVA>', $xml);
    }

    public function test_builds_iecv_purchases_and_amount_exempt(): void
    {
        $dtes = SiiDte::factory()
            ->count(1)
            ->sequence(
                [
                    'document_type' => DteType::Invoice,
                    'folio' => 1,
                    'issuer_rut' => '76123456-0',
                    'receiver_rut' => '55666777-8',
                    'issued_on' => '2023-10-05',
                    'amount_net' => 1000,
                    'amount_exempt' => 500,
                    'amount_taxes' => 190,
                    'amount_total' => 1690,
                ]
            )
            ->createMany();

        $xml = $this->app->make(IecvBuilder::class)->build(
            dtes: $dtes,
            type: IecvType::Purchases,
            period: '2023-10',
            resolutionDate: '2020-01-01',
            resolutionNumber: 1234,
            senderRut: Rut::parse('55666777-8')
        );

        $xmlString = $xml;

        // Should include MntExe
        static::assertStringContainsString('<MntExe>500</MntExe>', $xmlString);
        // For Purchases, RUTDoc should be the issuer
        static::assertStringContainsString('<RUTDoc>76123456-0</RUTDoc>', $xmlString);
        // Purchases book must have FolioNotificacion = 2
        static::assertStringContainsString('<FolioNotificacion>2</FolioNotificacion>', $xmlString);
        // TotMntIVA must always be present (570 here, even though purchases total differs)
        static::assertStringContainsString('<TotMntIVA>190</TotMntIVA>', $xmlString);
        // MntIVA must always be present in Detalle for non-common-use DTEs
        static::assertStringContainsString('<MntIVA>190</MntIVA>', $xmlString);
    }

    public function test_builds_iecv_with_exempt_dte(): void
    {
        $dtes = SiiDte::factory()
            ->count(1)
            ->sequence(
                [
                    'document_type' => DteType::Invoice,
                    'folio' => 1,
                    'issuer_rut' => '76123456-0',
                    'receiver_rut' => '55666777-8',
                    'issued_on' => '2023-10-05',
                    'amount_net' => 0,
                    'amount_exempt' => 1000,
                    'amount_taxes' => 0,
                    'amount_total' => 1000,
                ]
            )
            ->createMany();

        $xml = $this->app->make(IecvBuilder::class)->build(
            dtes: $dtes,
            type: IecvType::Sales,
            period: '2023-10',
            resolutionDate: '2020-01-01',
            resolutionNumber: 1234,
            senderRut: Rut::parse('55666777-8')
        );

        // Exempt DTEs must have TasaImp = 0.00
        static::assertStringContainsString('<TasaImp>0.00</TasaImp>', $xml);
        // MntIVA must always be present, even when 0
        static::assertStringContainsString('<MntIVA>0</MntIVA>', $xml);
        // TotMntIVA must be present in ResumenPeriodo, even when 0
        static::assertStringContainsString('<TotMntIVA>0</TotMntIVA>', $xml);
    }

    public function test_otros_imp_appears_before_mnt_total(): void
    {
        $dte = SiiDte::factory()->create([
            'document_type' => DteType::Invoice,
            'folio' => 42,
            'issuer_rut' => '76123456-0',
            'receiver_rut' => '55666777-8',
            'issued_on' => '2023-10-05',
            'amount_net' => 1000,
            'amount_exempt' => 0,
            'amount_taxes' => 190,
            'amount_total' => 1190,
            'taxes' => [15 => 500],
        ]);

        $xml = $this->app->make(IecvBuilder::class)->build(
            dtes: collect([$dte]),
            type: IecvType::Sales,
            period: '2023-10',
            resolutionDate: '2020-01-01',
            resolutionNumber: 1234,
            senderRut: Rut::parse('55666777-8'),
        );

        $otrosImpPos = strpos($xml, '<OtrosImp>');
        $mntTotalPos = strpos($xml, '<MntTotal>');

        static::assertNotFalse($otrosImpPos, 'OtrosImp should be present');
        static::assertNotFalse($mntTotalPos, 'MntTotal should be present');
        static::assertLessThan($mntTotalPos, $otrosImpPos, 'OtrosImp must appear before MntTotal in XSD order');
    }

    public function test_build_purchases_from_entries(): void
    {
        $companyRut = Rut::parse('76.123.456-0');
        $senderRut = Rut::parse('76.123.456-0');
        $vendor1 = Rut::parse('77.777.777-7');
        $vendor2 = Rut::parse('88.888.888-8');

        $entries = [
            IecvPurchaseData::make(
                documentType: DteType::InvoicePhysical,
                folio: 234,
                issuedOn: '2023-10-01',
                issuerRut: $vendor1,
                amountNet: 45899,
            ),
            IecvPurchaseData::make(
                documentType: DteType::InvoicePhysical,
                folio: 781,
                issuedOn: '2023-10-01',
                issuerRut: $vendor1,
                amountNet: 30082,
                ivaCommonUse: true,
            ),
            IecvPurchaseData::make(
                documentType: DteType::Invoice,
                folio: 32,
                issuedOn: '2023-10-01',
                issuerRut: $vendor1,
                amountNet: 10335,
                amountExempt: 10221,
            ),
            IecvPurchaseData::make(
                documentType: DteType::Invoice,
                folio: 67,
                issuedOn: '2023-10-01',
                issuerRut: $vendor2,
                amountNet: 11650,
                noCost: true,
            ),
            IecvPurchaseData::make(
                documentType: DteType::PurchaseInvoice,
                folio: 9,
                issuedOn: '2023-10-01',
                issuerRut: $vendor1,
                amountNet: 10388,
                ivaRetainedTotal: true,
            ),
            IecvPurchaseData::make(
                documentType: DteType::CreditNote,
                folio: 451,
                issuedOn: '2023-10-01',
                issuerRut: $vendor1,
                amountNet: 2880,
                referenceType: DteType::InvoicePhysical,
                referenceFolio: 234,
            ),
        ];

        $properties = [IecvProperty::CommonIvaFactor->of(0.60)];

        $xml = $this->app->make(IecvBuilder::class)->buildPurchases(
            entries: $entries,
            period: '2023-10',
            resolutionDate: '2020-01-01',
            resolutionNumber: 1234,
            companyRut: $companyRut,
            senderRut: $senderRut,
            properties: $properties,
        );

        // Carátula
        static::assertStringContainsString('<TipoOperacion>COMPRA</TipoOperacion>', $xml);
        static::assertStringContainsString('<TipoLibro>ESPECIAL</TipoLibro>', $xml);
        static::assertStringContainsString('<TipoEnvio>TOTAL</TipoEnvio>', $xml);
        static::assertStringContainsString('<FolioNotificacion>2</FolioNotificacion>', $xml);
        static::assertStringContainsString('<RutEmisorLibro>76123456-0</RutEmisorLibro>', $xml);

        // Vendor RUTDoc
        static::assertStringContainsString('<RUTDoc>77777777-7</RUTDoc>', $xml);
        static::assertStringContainsString('<RUTDoc>88888888-8</RUTDoc>', $xml);

        // Computed amounts for Factura 234: net=45899, taxes=round(45899*0.19)=8721, total=54620
        static::assertStringContainsString('<MntNeto>45899</MntNeto>', $xml);
        static::assertStringContainsString('<MntIVA>8721</MntIVA>', $xml);
        static::assertStringContainsString('<MntTotal>54620</MntTotal>', $xml);

        // Retained IVA for Factura de Compra 9: taxes=round(10388*0.19)=1974, total=10388
        static::assertStringContainsString('<IVARetTotal>1974</IVARetTotal>', $xml);

        // Reference fields
        static::assertStringContainsString('<TpoDocRef>30</TpoDocRef>', $xml);
        static::assertStringContainsString('<FolioRef>234</FolioRef>', $xml);

        // IndSinCosto for entrega gratuita
        static::assertStringContainsString('<IndSinCosto>1</IndSinCosto>', $xml);

        // FctProp in resumen
        static::assertStringContainsString('<FctProp>0.6</FctProp>', $xml);
    }

    public function test_build_purchases_accepts_raw_int_document_type(): void
    {
        $entries = [
            IecvPurchaseData::make(
                documentType: 30,
                folio: 1,
                issuedOn: '2023-10-01',
                issuerRut: '77777777-7',
                amountNet: 1000,
                referenceType: 33,
                referenceFolio: 10,
            ),
        ];

        $xml = $this->app->make(IecvBuilder::class)->buildPurchases(
            entries: $entries,
            period: '2023-10',
            resolutionDate: '2020-01-01',
            resolutionNumber: 1234,
            companyRut: Rut::parse('76.123.456-0'),
            senderRut: Rut::parse('76.123.456-0'),
        );

        static::assertStringContainsString('<TpoDoc>30</TpoDoc>', $xml);
        static::assertStringContainsString('<TpoDocRef>33</TpoDocRef>', $xml);
        static::assertStringContainsString('<FolioRef>10</FolioRef>', $xml);
    }
}
