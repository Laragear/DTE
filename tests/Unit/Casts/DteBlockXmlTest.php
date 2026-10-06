<?php

namespace Tests\Unit\Casts;

use Laragear\Dte\Casts\DteCommissions;
use Laragear\Dte\Casts\DteEmissionGeoref;
use Laragear\Dte\Casts\DteHeaderIdDoc;
use Laragear\Dte\Casts\DteHeaderOtherCurrency;
use Laragear\Dte\Casts\DteSubtotals;
use Laragear\Dte\Casts\DteTimberHandling;
use Tests\TestCase;
use XMLWriter;

class DteBlockXmlTest extends TestCase
{
    public function test_id_doc_appends_payments_and_indicators(): void
    {
        $writer = new XMLWriter;
        $writer->openMemory();

        DteHeaderIdDoc::make([
            'document_type' => 33,
            'issued_on' => '2026-08-15',
            'ind_traslado' => 1,
            'tipo_despacho' => 2,
            'ind_mnt_neto' => 1,
            'non_rebillable' => 1,
            'payment' => ['condition' => 2, 'expiration_date' => '2026-09-15'],
            'payments' => [
                ['date' => '2026-08-20', 'amount' => 500, 'gloss' => 'Advance'],
            ],
        ])->toXml($writer, 101);

        $xml = $writer->outputMemory();

        static::assertStringContainsString('<TipoDTE>33</TipoDTE>', $xml);
        static::assertStringContainsString('<Folio>101</Folio>', $xml);
        static::assertStringContainsString('<FchEmis>2026-08-15</FchEmis>', $xml);
        static::assertStringContainsString('<IndNoRebaja>1</IndNoRebaja>', $xml);
        static::assertStringContainsString('<FmaPago>2</FmaPago>', $xml);
        static::assertStringContainsString('<FchVenc>2026-09-15</FchVenc>', $xml);
        static::assertStringContainsString('<MntPagos>', $xml);
        static::assertStringContainsString('<FchPago>2026-08-20</FchPago>', $xml);
        static::assertStringContainsString('<MntPago>500</MntPago>', $xml);
        static::assertStringContainsString('<GlosaPagos>Advance</GlosaPagos>', $xml);
        static::assertStringContainsString('<IndTraslado>1</IndTraslado>', $xml);
        static::assertStringContainsString('<TipoDespacho>2</TipoDespacho>', $xml);
        static::assertStringContainsString('<IndMntNeto>1</IndMntNeto>', $xml);
    }

    public function test_other_currency_appends_the_otra_moneda_block(): void
    {
        $writer = new XMLWriter;
        $writer->openMemory();

        DteHeaderOtherCurrency::make([
            'currency' => 'DOLAR USA',
            'exchange_rate' => '950.5',
            'tax' => '190',
            'total' => '1190',
            'withheld_taxes' => [
                ['type' => 14, 'rate' => '10', 'amount' => '100'],
            ],
        ])->toXml($writer);

        $xml = $writer->outputMemory();

        static::assertStringContainsString('<OtraMoneda>', $xml);
        static::assertStringContainsString('<TpoMoneda>DOLAR USA</TpoMoneda>', $xml);
        static::assertStringContainsString('<TpoCambio>950.5</TpoCambio>', $xml);
        static::assertStringContainsString('<IVAOtrMnda>190</IVAOtrMnda>', $xml);
        static::assertStringContainsString('<ImpRetOtrMnda>', $xml);
        static::assertStringContainsString('<TipoImpOtrMnda>14</TipoImpOtrMnda>', $xml);
        static::assertStringContainsString('<TasaImpOtrMnda>10</TasaImpOtrMnda>', $xml);
        static::assertStringContainsString('<VlrImpOtrMnda>100</VlrImpOtrMnda>', $xml);
        static::assertStringContainsString('<MntTotOtrMnda>1190</MntTotOtrMnda>', $xml);
    }

    public function test_subtotals_append_the_sub_tot_info_block(): void
    {
        $writer = new XMLWriter;
        $writer->openMemory();

        DteSubtotals::make([
            'items' => [
                [
                    'description' => 'Group A',
                    'order' => 1,
                    'net' => 1000,
                    'tax' => 190,
                    'total' => 1190,
                    'detail_lines' => [1, 2],
                ],
            ],
        ])->toXml($writer);

        $xml = $writer->outputMemory();

        static::assertStringContainsString('<SubTotInfo>', $xml);
        static::assertStringContainsString('<GlosaSTI>Group A</GlosaSTI>', $xml);
        static::assertStringContainsString('<OrdenSTI>1</OrdenSTI>', $xml);
        static::assertStringContainsString('<SubTotNetoSTI>1000</SubTotNetoSTI>', $xml);
        static::assertStringContainsString('<SubTotIVASTI>190</SubTotIVASTI>', $xml);
        static::assertStringContainsString('<ValSubtotSTI>1190</ValSubtotSTI>', $xml);
        static::assertStringContainsString('<LineasDeta>1</LineasDeta>', $xml);
        static::assertStringContainsString('<LineasDeta>2</LineasDeta>', $xml);
    }

    public function test_commissions_append_the_comisiones_block(): void
    {
        $writer = new XMLWriter;
        $writer->openMemory();

        DteCommissions::make([
            'items' => [
                ['type' => 'C', 'description' => 'Broker fee', 'rate' => '5', 'net' => 500, 'exempt' => 0, 'tax' => 95],
            ],
        ])->toXml($writer);

        $xml = $writer->outputMemory();

        static::assertStringContainsString('<Comisiones>', $xml);
        static::assertStringContainsString('<NroLinCom>1</NroLinCom>', $xml);
        static::assertStringContainsString('<TipoMovim>C</TipoMovim>', $xml);
        static::assertStringContainsString('<Glosa>Broker fee</Glosa>', $xml);
        static::assertStringContainsString('<TasaComision>5</TasaComision>', $xml);
        static::assertStringContainsString('<ValComNeto>500</ValComNeto>', $xml);
        static::assertStringContainsString('<ValComExe>0</ValComExe>', $xml);
        static::assertStringContainsString('<ValComIVA>95</ValComIVA>', $xml);
    }

    public function test_emission_georef_appends_the_geo_ref_block(): void
    {
        $writer = new XMLWriter;
        $writer->openMemory();

        DteEmissionGeoref::make([
            'latitude' => '-33.4489',
            'longitude' => '-70.6693',
            'reference_system' => 1,
        ])->toXml($writer);

        $xml = $writer->outputMemory();

        static::assertStringContainsString('<GeoRefEmision>', $xml);
        static::assertStringContainsString('<LatitudEmision>-33.4489</LatitudEmision>', $xml);
        static::assertStringContainsString('<LongitudEmision>-70.6693</LongitudEmision>', $xml);
        static::assertStringContainsString('<SistemaReferencia>1</SistemaReferencia>', $xml);
    }

    public function test_timber_handling_appends_the_manejo_madera_block(): void
    {
        $writer = new XMLWriter;
        $writer->openMemory();

        DteTimberHandling::make([
            'origin_commune' => 13101,
            'origin_block' => 12,
            'origin_property' => 34,
            'conaf_plan_code' => 'PLAN-2026-001',
            'origin_latitude' => '-38.7359',
            'origin_longitude' => '-72.5902',
            'reference_system' => 1,
        ])->toXml($writer);

        $xml = $writer->outputMemory();

        static::assertStringContainsString('<ManejoMadera>', $xml);
        static::assertStringContainsString('<ComunaRolOrigen>13101</ComunaRolOrigen>', $xml);
        static::assertStringContainsString('<MnzRolOrigen>12</MnzRolOrigen>', $xml);
        static::assertStringContainsString('<PrdRolOrigen>34</PrdRolOrigen>', $xml);
        static::assertStringContainsString('<CodPlanConaf>PLAN-2026-001</CodPlanConaf>', $xml);
        static::assertStringContainsString('<LatitudOrigenMadera>-38.7359</LatitudOrigenMadera>', $xml);
        static::assertStringContainsString('<LongitudOrigenMadera>-72.5902</LongitudOrigenMadera>', $xml);
        static::assertStringContainsString('<SistemareferenciaMadera>1</SistemareferenciaMadera>', $xml);
        static::assertStringNotContainsString('<ComunaRolDestino>', $xml);
    }

    public function test_empty_optional_blocks_emit_nothing(): void
    {
        foreach ([DteHeaderOtherCurrency::class, DteEmissionGeoref::class, DteTimberHandling::class] as $block) {
            $writer = new XMLWriter;
            $writer->openMemory();

            $block::make()->toXml($writer);

            static::assertSame('', $writer->outputMemory(), $block.' should emit nothing when empty.');
        }
    }
}
