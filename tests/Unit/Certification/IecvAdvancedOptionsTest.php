<?php

namespace Tests\Unit\Certification;

use Laragear\Dte\Builders\Iecv\IecvBuilder;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\IecvProperty;
use Laragear\Dte\Enums\IecvType;
use Laragear\Dte\Models\SiiDte;
use Laragear\Rut\Rut;
use Tests\DatabaseTestCase;

class IecvAdvancedOptionsTest extends DatabaseTestCase
{
    public function test_compiles_advanced_book_with_uso_comun_and_retentions(): void
    {
        $dteUsoComun = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'receiver_rut' => Rut::parse('22222222-2'),
            'folio' => 781,
            'document_type' => DteType::Invoice, // 33
            'amount_exempt' => 0,
            'amount_net' => 30082,
            'amount_taxes' => 5716, // 19%
            'amount_total' => 35798,
            'iva_common_use' => true,
        ]);

        $dteRetention = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'receiver_rut' => Rut::parse('22222222-2'),
            'folio' => 9,
            'document_type' => DteType::PurchaseInvoice, // 46
            'amount_exempt' => 0,
            'amount_net' => 10388,
            'amount_taxes' => 1974,
            'taxes' => [15 => 1974],
            'amount_total' => 10388,
        ]);

        $builder = $this->app->make(IecvBuilder::class);

        $xmlString = $builder->build(
            dtes: collect([$dteUsoComun, $dteRetention]),
            type: IecvType::Purchases,
            period: '2024-03',
            resolutionDate: '2024-01-01',
            resolutionNumber: 123,
            senderRut: Rut::parse('11111111-1'),
            properties: [IecvProperty::CommonIvaFactor->of(0.60)],
        );

        static::assertStringContainsString('<TotOpIVAUsoComun>1</TotOpIVAUsoComun>', $xmlString);
        static::assertStringContainsString('<TotIVAUsoComun>5716</TotIVAUsoComun>', $xmlString);
        static::assertStringContainsString('<FctProp>0.6</FctProp>', $xmlString);
        static::assertStringContainsString('<TotCredIVAUsoComun>3430</TotCredIVAUsoComun>', $xmlString);
        static::assertStringContainsString(
            '<TotOtrosImp><CodImp>15</CodImp><TotMntImp>1974</TotMntImp></TotOtrosImp>',
            $xmlString,
        );

        static::assertStringContainsString('<IVAUsoComun>5716</IVAUsoComun>', $xmlString);
        static::assertStringContainsString('<OtrosImp><CodImp>15</CodImp><MntImp>1974</MntImp></OtrosImp>', $xmlString);
    }
}
