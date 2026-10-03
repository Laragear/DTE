<?php

namespace Tests\Unit;

use Generator;
use Illuminate\Support\Facades\Facade;
use Laragear\Dte\Actions\Aec\CompileAec;
use Laragear\Dte\Builders\AecCessionBuilder;
use Laragear\Dte\Builders\CreditNoteBuilder;
use Laragear\Dte\Builders\DebitNoteBuilder;
use Laragear\Dte\Builders\DispatchGuideBuilder;
use Laragear\Dte\Builders\DocumentBuilder;
use Laragear\Dte\Builders\InvoiceBuilder;
use Laragear\Dte\Builders\InvoiceLiquidationBuilder;
use Laragear\Dte\Builders\PurchaseInvoiceBuilder;
use Laragear\Dte\Builders\ReceiptBuilder;
use Laragear\Dte\Facades\SiiAecCession;
use Laragear\Dte\Facades\SiiCreditNote;
use Laragear\Dte\Facades\SiiDebitNote;
use Laragear\Dte\Facades\SiiDispatchGuide;
use Laragear\Dte\Facades\SiiInvoice;
use Laragear\Dte\Facades\SiiInvoiceLiquidation;
use Laragear\Dte\Facades\SiiPurchaseInvoice;
use Laragear\Dte\Facades\SiiReceipt;
use Laragear\Dte\Models\SiiDte;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BuilderTest extends TestCase
{
    /**
     * @return Generator<string, array{class-string, class-string}>
     */
    public static function builders(): Generator
    {
        yield 'invoice' => [SiiInvoice::class, InvoiceBuilder::class];
        yield 'receipt' => [SiiReceipt::class, ReceiptBuilder::class];
        yield 'credit note' => [SiiCreditNote::class, CreditNoteBuilder::class];
        yield 'debit note' => [SiiDebitNote::class, DebitNoteBuilder::class];
        yield 'dispatch guide' => [SiiDispatchGuide::class, DispatchGuideBuilder::class];
        yield 'purchase invoice' => [SiiPurchaseInvoice::class, PurchaseInvoiceBuilder::class];
        yield 'invoice liquidation' => [SiiInvoiceLiquidation::class, InvoiceLiquidationBuilder::class];
    }

    /**
     * @param  class-string<Facade>  $facade
     * @param  class-string<DocumentBuilder>  $expected
     */
    #[DataProvider('builders')]
    public function test_builds_documents_through_the_service_container(string $facade, string $expected): void
    {
        $instance = $this->mock($expected);

        static::assertSame($instance, $this->app->make($expected));
    }

    /**
     * @param  class-string<Facade>  $facade
     * @param  class-string  $expected
     */
    #[DataProvider('builders')]
    public function test_facade_resolves_the_correct_builder(string $facade, string $expected): void
    {
        static::assertInstanceOf($expected, $facade::getFacadeRoot());
    }

    public function test_builds_aec(): void
    {
        static::assertInstanceOf(CompileAec::class, $this->app->make(CompileAec::class));
    }

    public function test_builds_aec_cession(): void
    {
        $dte = new SiiDte;
        $instance = $this->mock(AecCessionBuilder::class);
        $instance->expects('forDte')->with($dte)->andReturn($instance);

        static::assertSame($instance, SiiAecCession::forDte($dte));
    }
}
