<?php

namespace Tests\Unit\Builders;

use Laragear\Dte\Builders\Iecv\IecvBuilder;
use Laragear\Dte\Builders\PurchaseInvoiceBuilder;
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Data\CompanyData;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\IecvProperty;
use Laragear\Dte\Enums\IecvType;
use Laragear\Dte\Models\SiiDte;
use Laragear\Rut\Rut;
use Tests\DatabaseTestCase;
use Tests\Unit\Builders\Fixtures\BuilderFixture;

class CommonUseIvaTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ConfigurationManager::setCompany(fn () => CompanyData::make(
            IssuerData::make(
                '76.123.456-0',
                'Test Company',
                'Software',
                ['620100'],
                'Test Address 123',
                'Santiago',
                '2025-01-01',
                76000,
                'Santiago',
                '+56212345678',
                'test@example.com',
                'Casa Matriz',
            ),
        ));
    }

    protected function purchaseInvoice(): PurchaseInvoiceBuilder
    {
        return $this->app
            ->make(PurchaseInvoiceBuilder::class)
            ->issuedBy(BuilderFixture::issuer())
            ->receivedBy(BuilderFixture::receiver());
    }

    /*
    |--------------------------------------------------------------------------
    | Happy paths
    |--------------------------------------------------------------------------
    */

    public function test_flag_is_false_by_default(): void
    {
        static::assertFalse($this->purchaseInvoice()->hasCommonUseIva());
    }

    public function test_flag_returns_the_builder_fluently(): void
    {
        $builder = $this->purchaseInvoice();

        static::assertSame($builder, $builder->withCommonUseIva());
        static::assertTrue($builder->hasCommonUseIva());
    }

    public function test_flag_can_be_turned_off_explicitly(): void
    {
        static::assertFalse($this->purchaseInvoice()->withCommonUseIva(false)->hasCommonUseIva());
    }

    public function test_flag_persists_on_the_built_document(): void
    {
        $dte = $this->purchaseInvoice()
            ->addItem(BuilderFixture::item())
            ->withCommonUseIva()
            ->buildSync();

        static::assertTrue($dte->iva_common_use);

        // The regression that matters: the flag must survive a refetch, or the
        // purchase silently drops out of the IVA Uso Común totals.
        static::assertTrue($dte->fresh()->iva_common_use);
    }

    public function test_flag_defaults_to_false_on_the_persisted_document(): void
    {
        $dte = $this->purchaseInvoice()
            ->addItem(BuilderFixture::item())
            ->buildSync();

        static::assertFalse($dte->fresh()->iva_common_use);
    }

    public function test_flag_survives_a_hydrate_and_rebuild(): void
    {
        $dte = $this->purchaseInvoice()
            ->addItem(BuilderFixture::item())
            ->withCommonUseIva()
            ->buildSync();

        $rehydrated = $this->app
            ->make(PurchaseInvoiceBuilder::class)
            ->hydrate($dte->fresh());

        static::assertTrue($rehydrated->hasCommonUseIva());
        static::assertTrue($rehydrated->attributes()['iva_common_use']);
    }

    public function test_flagged_purchase_is_grouped_into_the_uso_comun_totals(): void
    {
        $flagged = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'document_type' => DteType::PurchaseInvoice,
            'folio' => 781,
            'amount_net' => 30082,
            'amount_taxes' => 5716,
            'amount_total' => 35798,
            'iva_common_use' => true,
            'status' => DteStatus::Accepted,
        ]);

        $regular = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'document_type' => DteType::PurchaseInvoice,
            'folio' => 9,
            'amount_net' => 10388,
            'amount_taxes' => 1974,
            'amount_total' => 12362,
            'iva_common_use' => false,
            'status' => DteStatus::Accepted,
        ]);

        $xml = $this->app->make(IecvBuilder::class)->build(
            dtes: collect([$flagged, $regular]),
            type: IecvType::Purchases,
            period: '2024-03',
            resolutionDate: '2024-01-01',
            resolutionNumber: 123,
            senderRut: Rut::parse('11111111-1'),
            properties: [IecvProperty::CommonIvaFactor->of(0.60)],
        );

        static::assertStringContainsString('<TotOpIVAUsoComun>1</TotOpIVAUsoComun>', $xml);
        static::assertStringContainsString('<TotIVAUsoComun>5716</TotIVAUsoComun>', $xml);
        static::assertStringContainsString('<FctProp>0.6</FctProp>', $xml);
        static::assertStringContainsString('<TotCredIVAUsoComun>3430</TotCredIVAUsoComun>', $xml);

        // The regular purchase keeps its own IVA line and is not counted as Uso Común.
        static::assertStringContainsString('<MntIVA>1974</MntIVA>', $xml);
    }

    public function test_flagged_purchase_emits_the_detalle_uso_comun_amount(): void
    {
        $dte = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'document_type' => DteType::PurchaseInvoice,
            'folio' => 781,
            'amount_net' => 30082,
            'amount_taxes' => 5716,
            'amount_total' => 35798,
            'iva_common_use' => true,
            'status' => DteStatus::Accepted,
        ]);

        $xml = $this->app->make(IecvBuilder::class)->build(
            dtes: collect([$dte]),
            type: IecvType::Purchases,
            period: '2024-03',
            resolutionDate: '2024-01-01',
            resolutionNumber: 123,
            senderRut: Rut::parse('11111111-1'),
            properties: [IecvProperty::CommonIvaFactor->of(0.60)],
        );

        static::assertStringContainsString('<IVAUsoComun>5716</IVAUsoComun>', $xml);
        // A Uso Común purchase must not also emit a plain MntIVA line.
        static::assertStringNotContainsString('<MntIVA>', $xml);
    }
}
