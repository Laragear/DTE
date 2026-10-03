<?php

namespace Tests\Unit\Actions\CompileDte\Pipes;

use DOMDocument;
use Laragear\Dte\Actions\CompileDte\Compilation;
use Laragear\Dte\Actions\CompileDte\Compile;
use Laragear\Dte\Actions\CompileDte\Pipes\BuildXml;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDtePayload;
use Laragear\MetaTesting\Pipeline\InteractsWithPipelines;
use Laragear\Rut\Rut;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\DatabaseTestCase;

class BuildXmlTest extends DatabaseTestCase
{
    use InteractsWithPipelines;

    protected function makeCompilation(array $data = []): Compilation
    {
        $dte = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'folio' => 123,
            'document_type' => DteType::Invoice,
        ]);

        $payload = new SiiDtePayload([
            'sii_dte_id' => $dte->id,
            'data' => array_merge([
                'document_type' => 33,
                'issued_on' => '2024-01-15',
                'issuer' => [
                    'rut' => '11111111-1',
                    'name' => 'Test Company',
                    'activity' => 'Test Activity',
                    'activity_code' => '620100',
                ],
                'receiver' => [
                    'rut' => '22222222-2',
                    'name' => 'Client Company',
                ],
                'totals' => [
                    'net' => 10000,
                    'exempt' => 0,
                    'tax' => 1900,
                    'total' => 11900,
                ],
                'items' => [
                    [
                        'name' => 'Item 1',
                        'description' => null,
                        'quantity' => 1,
                        'unit' => null,
                        'unit_price' => 10000,
                        'discount_percentage' => 0,
                        'exempt' => false,
                        'code' => null,
                        'code_type' => null,
                    ],
                ],
                'references' => [],
            ], $data),
        ]);

        $dte->setRelation('payload', $payload);

        return new Compilation($dte);
    }

    /*
    |--------------------------------------------------------------------------
    | Happy paths
    |--------------------------------------------------------------------------
    */

    public function test_build_xml_dom_creates_valid_document(): void
    {
        $compilation = $this->makeCompilation();

        $this->pipeline(Compile::class)
            ->isolatePipe(BuildXml::class)
            ->send($compilation)
            ->assertPassable(function (Compilation $result) {
                static::assertInstanceOf(DOMDocument::class, $result->document);
                static::assertEquals('ISO-8859-1', $result->document->encoding);
                static::assertStringContainsString('<DTE', $result->document->saveXML());
                static::assertStringContainsString('<Documento', $result->document->saveXML());

                return true;
            });
    }

    public function test_tasa_iva_element_reads_from_config(): void
    {
        $this->config('dte.taxes.iva_rate', 21);

        $compilation = $this->makeCompilation();

        $this->pipeline(Compile::class)
            ->isolatePipe(BuildXml::class)
            ->send($compilation)
            ->assertPassable(function (Compilation $result) {
                static::assertStringContainsString('<TasaIVA>21</TasaIVA>', $result->document->saveXML());

                return true;
            });
    }

    public function test_append_receiver_returns_early_when_receiver_is_null(): void
    {
        // Line 119: return when receiver is null
        $compilation = $this->makeCompilation(['receiver' => null]);

        $this->pipeline(Compile::class)
            ->isolatePipe(BuildXml::class)
            ->send($compilation)
            ->assertPassable(function (Compilation $result) {
                static::assertInstanceOf(DOMDocument::class, $result->document);
                $xml = $result->document->saveXML();
                static::assertStringNotContainsString('<Receptor', $xml);

                return true;
            });
    }

    public function test_append_item_code_creates_code_elements_when_code_present(): void
    {
        // Lines 191-193: creates CdgItem, TpoCodigo, VlrCodigo when item has code
        $compilation = $this->makeCompilation([
            'items' => [
                [
                    'name' => 'Item with Code',
                    'description' => null,
                    'quantity' => 1,
                    'unit' => null,
                    'unit_price' => 10000,
                    'discount_percentage' => 0,
                    'exempt' => false,
                    'code' => 'SKU-001',
                    'code_type' => 'INT1',
                ],
            ],
        ]);

        $this->pipeline(Compile::class)
            ->isolatePipe(BuildXml::class)
            ->send($compilation)
            ->assertPassable(function (Compilation $result) {
                $xml = $result->document->saveXML();
                static::assertStringContainsString('<CdgItem', $xml);
                static::assertStringContainsString('<TpoCodigo>INT1</TpoCodigo>', $xml);
                static::assertStringContainsString('<VlrCodigo>SKU-001</VlrCodigo>', $xml);

                return true;
            });
    }

    public function test_append_references_creates_reference_elements(): void
    {
        // Lines 231-237: creates Referencia elements with all sub-elements
        $compilation = $this->makeCompilation([
            'references' => [
                [
                    'document_type' => 33,
                    'folio' => 456,
                    'date' => '2024-01-10',
                    'reference_code' => 1,
                    'reason' => 'Replaces invoice 456',
                ],
            ],
        ]);

        $this->pipeline(Compile::class)
            ->isolatePipe(BuildXml::class)
            ->send($compilation)
            ->assertPassable(function (Compilation $result) {
                $xml = $result->document->saveXML();
                static::assertStringContainsString('<Referencia', $xml);
                static::assertStringContainsString('<NroLinRef>1</NroLinRef>', $xml);
                static::assertStringContainsString('<TpoDocRef>33</TpoDocRef>', $xml);
                static::assertStringContainsString('<FolioRef>456</FolioRef>', $xml);
                static::assertStringContainsString('<FchRef>2024-01-10</FchRef>', $xml);
                static::assertStringContainsString('<CodRef>1</CodRef>', $xml);
                static::assertStringContainsString('<RazonRef>Replaces invoice 456</RazonRef>', $xml);

                return true;
            });
    }

    public function test_append_references_with_optional_fields_only(): void
    {
        $compilation = $this->makeCompilation([
            'references' => [
                [
                    'document_type' => 33,
                    'folio' => 456,
                    'date' => '2024-01-10',
                    'reference_code' => null,
                    'reason' => null,
                ],
            ],
        ]);

        $this->pipeline(Compile::class)
            ->isolatePipe(BuildXml::class)
            ->send($compilation)
            ->assertPassable(function (Compilation $result) {
                $xml = $result->document->saveXML();
                static::assertStringContainsString('<Referencia', $xml);
                static::assertStringNotContainsString('<CodRef>', $xml);
                static::assertStringNotContainsString('<RazonRef>', $xml);

                return true;
            });
    }

    public function test_builds_xml_with_transport_and_payment_terms(): void
    {
        $compilation = $this->makeCompilation([
            'payment' => [
                'condition' => 2,
                'expiration_date' => '2023-12-31',
            ],
            'transport' => [
                'vehicle_plate' => 'AA1122',
                'trailer_plate' => 'BB3344',
                'carrier_rut' => '22222222-2',
                'driver_rut' => '33333333-3',
                'driver_name' => 'John Doe',
                'destination_address' => '123 Fake St',
                'destination_commune' => 'Santiago',
                'destination_city' => 'Santiago',
            ],
        ]);

        $this->pipeline(Compile::class)
            ->isolatePipe(BuildXml::class)
            ->send($compilation)
            ->assertPassable(function (Compilation $data) {
                $xml = $data->document->saveXML();

                // Assert Payment Terms
                static::assertStringContainsString('<FmaPago>2</FmaPago>', $xml);
                static::assertStringContainsString('<FchVenc>2023-12-31</FchVenc>', $xml);

                // Assert Transport
                static::assertStringContainsString('<Transporte>', $xml);
                static::assertStringContainsString('<Patente>AA1122</Patente>', $xml);
                static::assertStringContainsString('<PatenteVehiculo>BB3344</PatenteVehiculo>', $xml);
                static::assertStringContainsString('<RUTTrans>22222222-2</RUTTrans>', $xml);
                static::assertStringContainsString('<Chofer>', $xml);
                static::assertStringContainsString('<RUT>33333333-3</RUT>', $xml);
                static::assertStringContainsString('<Nombre>John Doe</Nombre>', $xml);
                static::assertStringContainsString('</Chofer>', $xml);
                static::assertStringContainsString('<DirDest>123 Fake St</DirDest>', $xml);
                static::assertStringContainsString('<CmnaDest>Santiago</CmnaDest>', $xml);
                static::assertStringContainsString('<CiudadDest>Santiago</CiudadDest>', $xml);
                static::assertStringContainsString('</Transporte>', $xml);

                return true;
            });
    }

    public function test_percentage_discount_emits_both_pct_and_monto(): void
    {
        $compilation = $this->makeCompilation([
            'totals' => [
                'net' => 1051002,
                'exempt' => 0,
                'tax' => 199690,
                'total' => 1250692,
            ],
            'items' => [
                [
                    'name' => 'Pañuelo AFECTO',
                    'description' => null,
                    'quantity' => 373,
                    'unit' => null,
                    'unit_price' => 2966,
                    'discount_percentage' => 5,
                    'exempt' => false,
                    'code' => null,
                    'code_type' => null,
                ],
            ],
        ]);

        $this->pipeline(Compile::class)
            ->isolatePipe(BuildXml::class)
            ->send($compilation)
            ->assertPassable(function (Compilation $result) {
                $xml = $result->document->saveXML();

                static::assertStringContainsString('<DescuentoPct>5</DescuentoPct>', $xml);
                static::assertStringContainsString('<DescuentoMonto>55316</DescuentoMonto>', $xml);
                static::assertStringContainsString('<MontoItem>1051002</MontoItem>', $xml);

                return true;
            });
    }

    public function test_fixed_discount_without_percentage(): void
    {
        $compilation = $this->makeCompilation([
            'totals' => [
                'net' => 9000,
                'exempt' => 0,
                'tax' => 1710,
                'total' => 10710,
            ],
            'items' => [
                [
                    'name' => 'Fixed Discount Item',
                    'description' => null,
                    'quantity' => 10,
                    'unit' => null,
                    'unit_price' => 1000,
                    'discount_percentage' => 0,
                    'discount_amount' => 1000,
                    'exempt' => false,
                    'code' => null,
                    'code_type' => null,
                ],
            ],
        ]);

        $this->pipeline(Compile::class)
            ->isolatePipe(BuildXml::class)
            ->send($compilation)
            ->assertPassable(function (Compilation $result) {
                $xml = $result->document->saveXML();

                static::assertStringNotContainsString('<DescuentoPct>', $xml);
                static::assertStringContainsString('<DescuentoMonto>1000</DescuentoMonto>', $xml);
                static::assertStringContainsString('<MontoItem>9000</MontoItem>', $xml);

                return true;
            });
    }

    public function test_percentage_takes_precedence_over_fixed_discount(): void
    {
        $compilation = $this->makeCompilation([
            'totals' => [
                'net' => 90000,
                'exempt' => 0,
                'tax' => 17100,
                'total' => 107100,
            ],
            'items' => [
                [
                    'name' => 'Conflict Item',
                    'description' => null,
                    'quantity' => 10,
                    'unit' => null,
                    'unit_price' => 10000,
                    'discount_percentage' => 10,
                    'discount_amount' => 999,
                    'exempt' => false,
                    'code' => null,
                    'code_type' => null,
                ],
            ],
        ]);

        $this->pipeline(Compile::class)
            ->isolatePipe(BuildXml::class)
            ->send($compilation)
            ->assertPassable(function (Compilation $result) {
                $xml = $result->document->saveXML();

                static::assertStringContainsString('<DescuentoPct>10</DescuentoPct>', $xml);
                static::assertStringContainsString('<DescuentoMonto>10000</DescuentoMonto>', $xml);
                static::assertStringContainsString('<MontoItem>90000</MontoItem>', $xml);

                return true;
            });
    }

    public function test_no_discount_omits_both_fields(): void
    {
        $compilation = $this->makeCompilation([
            'totals' => [
                'net' => 10000,
                'exempt' => 0,
                'tax' => 1900,
                'total' => 11900,
            ],
            'items' => [
                [
                    'name' => 'No Discount Item',
                    'description' => null,
                    'quantity' => 1,
                    'unit' => null,
                    'unit_price' => 10000,
                    'discount_percentage' => 0,
                    'exempt' => false,
                    'code' => null,
                    'code_type' => null,
                ],
            ],
        ]);

        $this->pipeline(Compile::class)
            ->isolatePipe(BuildXml::class)
            ->send($compilation)
            ->assertPassable(function (Compilation $result) {
                $xml = $result->document->saveXML();

                static::assertStringNotContainsString('<DescuentoPct>', $xml);
                static::assertStringNotContainsString('<DescuentoMonto>', $xml);
                static::assertStringContainsString('<MontoItem>10000</MontoItem>', $xml);

                return true;
            });
    }

    public function test_zero_quantity_is_emitted_as_one(): void
    {
        $compilation = $this->makeCompilation([
            'items' => [
                [
                    'name' => 'Tax Only Line',
                    'description' => null,
                    'quantity' => 0,
                    'unit' => null,
                    'unit_price' => 0,
                    'discount_percentage' => 0,
                    'exempt' => false,
                    'code' => null,
                    'code_type' => null,
                ],
            ],
        ]);

        $this->pipeline(Compile::class)
            ->isolatePipe(BuildXml::class)
            ->send($compilation)
            ->assertPassable(function (Compilation $result) {
                $xml = $result->document->saveXML();

                // The SII rejects a literal zero quantity because
                // Dec12_6Type declares minInclusive 0.000001.
                static::assertStringContainsString('<QtyItem>1</QtyItem>', $xml);

                return true;
            });
    }

    public function test_zero_quantity_keeps_a_zero_line_amount_without_unit_price(): void
    {
        $compilation = $this->makeCompilation([
            'items' => [
                [
                    'name' => 'Tax Only Line',
                    'description' => null,
                    'quantity' => 0,
                    'unit' => null,
                    'unit_price' => 0,
                    'discount_percentage' => 0,
                    'exempt' => false,
                    'code' => null,
                    'code_type' => null,
                ],
            ],
        ]);

        $this->pipeline(Compile::class)
            ->isolatePipe(BuildXml::class)
            ->send($compilation)
            ->assertPassable(function (Compilation $result) {
                $xml = $result->document->saveXML();

                static::assertStringContainsString('<QtyItem>1</QtyItem>', $xml);
                static::assertStringContainsString('<MontoItem>0</MontoItem>', $xml);

                // A zero unit price is also facet-invalid, so it stays omitted.
                static::assertStringNotContainsString('<PrcItem>', $xml);

                return true;
            });
    }

    public function test_zero_quantity_omits_unit_price_even_when_the_price_is_given(): void
    {
        $compilation = $this->makeCompilation([
            'items' => [
                [
                    'name' => 'Tax Only Line',
                    'description' => null,
                    'quantity' => 0,
                    'unit' => null,
                    'unit_price' => 5000,
                    'discount_percentage' => 0,
                    'exempt' => false,
                    'code' => null,
                    'code_type' => null,
                ],
            ],
        ]);

        $this->pipeline(Compile::class)
            ->isolatePipe(BuildXml::class)
            ->send($compilation)
            ->assertPassable(function (Compilation $result) {
                $xml = $result->document->saveXML();

                static::assertStringContainsString('<QtyItem>1</QtyItem>', $xml);

                // A zero quantity means no price is billed, so PrcItem is left out.
                static::assertStringNotContainsString('<PrcItem>', $xml);
                static::assertStringContainsString('<MontoItem>0</MontoItem>', $xml);

                return true;
            });
    }

    public function test_positive_quantity_is_unchanged(): void
    {
        $compilation = $this->makeCompilation([
            'items' => [
                [
                    'name' => 'Regular Line',
                    'description' => null,
                    'quantity' => 20,
                    'unit' => null,
                    'unit_price' => 1000,
                    'discount_percentage' => 0,
                    'exempt' => false,
                    'code' => null,
                    'code_type' => null,
                ],
            ],
        ]);

        $this->pipeline(Compile::class)
            ->isolatePipe(BuildXml::class)
            ->send($compilation)
            ->assertPassable(function (Compilation $result) {
                $xml = $result->document->saveXML();

                static::assertStringContainsString('<QtyItem>20</QtyItem>', $xml);
                static::assertStringContainsString('<PrcItem>1000</PrcItem>', $xml);

                return true;
            });
    }

    #[DataProvider('providesFractionalQuantities')]
    public function test_fractional_quantities_below_one_are_preserved(float $quantity, string $expected): void
    {
        $compilation = $this->makeCompilation([
            'items' => [
                [
                    'name' => 'Weighed Line',
                    'description' => null,
                    'quantity' => $quantity,
                    'unit' => 'KG',
                    'unit_price' => 1000,
                    'discount_percentage' => 0,
                    'exempt' => false,
                    'code' => null,
                    'code_type' => null,
                ],
            ],
        ]);

        $this->pipeline(Compile::class)
            ->isolatePipe(BuildXml::class)
            ->send($compilation)
            ->assertPassable(function (Compilation $result) use ($expected) {
                $xml = $result->document->saveXML();

                // Only a literal zero is rewritten to 1; every other quantity,
                // including ones below 1, keeps its exact value.
                static::assertStringContainsString('<QtyItem>'.$expected.'</QtyItem>', $xml);
                static::assertStringContainsString('<PrcItem>1000</PrcItem>', $xml);

                return true;
            });
    }

    public static function providesFractionalQuantities(): array
    {
        return [
            'two point two' => [2.2, '2.2'],
            'a tenth' => [0.1, '0.1'],
            'a thousandth' => [0.001, '0.001'],
            'just below one' => [0.999999, '0.999999'],
            'above one' => [99999.0, '99999'],
        ];
    }

    public function test_additional_taxes_output_impto_reten_xml(): void
    {
        $compilation = $this->makeCompilation([
            'items' => [
                [
                    'name' => 'Item 1',
                    'description' => null,
                    'quantity' => 1,
                    'unit' => null,
                    'unit_price' => 10000,
                    'discount_percentage' => 0,
                    'exempt' => false,
                    'code' => null,
                    'code_type' => null,
                    'taxes' => [14 => 500],
                ],
            ],
            'totals' => [
                'net' => 10000,
                'exempt' => 0,
                'tax' => 1900,
                'total' => 12400,
                'non_billable' => 0,
            ],
            'taxes' => [14 => 500],
        ]);

        $this->pipeline(Compile::class)
            ->isolatePipe(BuildXml::class)
            ->send($compilation)
            ->assertPassable(function (Compilation $result) {
                $xml = $result->document->saveXML();

                static::assertStringContainsString('<ImptoReten>', $xml);
                static::assertStringContainsString('<TipoImp>14</TipoImp>', $xml);
                static::assertStringContainsString('<MontoImp>500</MontoImp>', $xml);

                return true;
            });
    }

    public function test_item_tax_codes_output_cod_imp_adic(): void
    {
        $compilation = $this->makeCompilation([
            'items' => [
                [
                    'name' => 'Item 1',
                    'description' => null,
                    'quantity' => 1,
                    'unit' => null,
                    'unit_price' => 10000,
                    'discount_percentage' => 0,
                    'exempt' => false,
                    'code' => null,
                    'code_type' => null,
                    'taxes' => [14 => 500],
                ],
            ],
            'totals' => [
                'net' => 10000,
                'exempt' => 0,
                'tax' => 1900,
                'total' => 12400,
                'non_billable' => 0,
            ],
        ]);

        $this->pipeline(Compile::class)
            ->isolatePipe(BuildXml::class)
            ->send($compilation)
            ->assertPassable(function (Compilation $result) {
                $xml = $result->document->saveXML();

                static::assertStringContainsString('<CodImpAdic>14</CodImpAdic>', $xml);

                return true;
            });
    }

    public function test_global_modifier_description_output_glosa_dr(): void
    {
        $compilation = $this->makeCompilation([
            'global_modifiers' => [
                [
                    'type' => 'D',
                    'value_type' => '%',
                    'value' => 10,
                    'target' => 0,
                    'description' => 'Bulk discount on order',
                ],
            ],
        ]);

        $this->pipeline(Compile::class)
            ->isolatePipe(BuildXml::class)
            ->send($compilation)
            ->assertPassable(function (Compilation $result) {
                $xml = $result->document->saveXML();

                static::assertStringContainsString('<GlosaDR>Bulk discount on order</GlosaDR>', $xml);

                return true;
            });
    }

    public function test_global_modifier_with_target_output_ind_exedr(): void
    {
        $compilation = $this->makeCompilation([
            'global_modifiers' => [
                [
                    'type' => 'D',
                    'value_type' => '%',
                    'value' => 10,
                    'target' => 1,
                    'description' => null,
                ],
            ],
        ]);

        $this->pipeline(Compile::class)
            ->isolatePipe(BuildXml::class)
            ->send($compilation)
            ->assertPassable(function (Compilation $result) {
                $xml = $result->document->saveXML();

                static::assertStringContainsString('<IndExeDR>1</IndExeDR>', $xml);

                return true;
            });
    }
}
