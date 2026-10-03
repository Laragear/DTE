<?php

namespace Tests\Unit\Models\Concerns;

use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\SiiDte;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\DatabaseTestCase;

class HasDocumentTypeTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        SiiDte::factory()->invoice()->create(['id' => 1]);
        SiiDte::factory()->exemptInvoice()->create(['id' => 2]);
        SiiDte::factory()->receipt()->create(['id' => 3]);
        SiiDte::factory()->creditNote()->create(['id' => 4]);
        SiiDte::factory()->debitNote()->create(['id' => 5]);
        SiiDte::factory()->dispatchGuide()->create(['id' => 6]);
        SiiDte::factory()->invoiceLiquidation()->create(['id' => 7]);
        SiiDte::factory()->purchaseInvoice()->create(['id' => 8]);
    }

    public function test_scope_invoices_filters_correctly(): void
    {
        $invoices = SiiDte::invoices()->pluck('id');

        static::assertCount(1, $invoices);
        static::assertTrue($invoices->contains(1));
    }

    public function test_scope_exempt_invoices_filters_correctly(): void
    {
        $exemptInvoices = SiiDte::exemptInvoices()->pluck('id');

        static::assertCount(1, $exemptInvoices);
        static::assertTrue($exemptInvoices->contains(2));
    }

    public function test_scope_receipts_filters_correctly(): void
    {
        $receipts = SiiDte::receipts()->pluck('id');

        static::assertCount(1, $receipts);
        static::assertTrue($receipts->contains(3));
    }

    public function test_scope_credit_notes_filters_correctly(): void
    {
        $creditNotes = SiiDte::creditNotes()->pluck('id');

        static::assertCount(1, $creditNotes);
        static::assertTrue($creditNotes->contains(4));
    }

    public function test_scope_debit_notes_filters_correctly(): void
    {
        $debitNotes = SiiDte::debitNotes()->pluck('id');

        static::assertCount(1, $debitNotes);
        static::assertTrue($debitNotes->contains(5));
    }

    public function test_scope_dispatch_guides_filters_correctly(): void
    {
        $dispatchGuides = SiiDte::dispatchGuides()->pluck('id');

        static::assertCount(1, $dispatchGuides);
        static::assertTrue($dispatchGuides->contains(6));
    }

    public function test_scope_invoice_liquidations_filters_correctly(): void
    {
        $invoiceLiquidations = SiiDte::invoiceLiquidations()->pluck('id');

        static::assertCount(1, $invoiceLiquidations);
        static::assertTrue($invoiceLiquidations->contains(7));
    }

    public function test_scope_purchase_invoices_filters_correctly(): void
    {
        $purchaseInvoices = SiiDte::purchaseInvoices()->pluck('id');

        static::assertCount(1, $purchaseInvoices);
        static::assertTrue($purchaseInvoices->contains(8));
    }

    public static function providesDocumentTypeScopes(): array
    {
        return [
            'invoices' => [DteType::Invoice],
            'exempt invoices' => [DteType::InvoiceExempt],
            'receipts' => [DteType::Receipt],
            'credit notes' => [DteType::CreditNote],
            'debit notes' => [DteType::DebitNote],
            'dispatch guides' => [DteType::DispatchGuide],
            'invoice liquidations' => [DteType::InvoiceLiquidation],
            'purchase invoices' => [DteType::PurchaseInvoice],
        ];
    }

    #[DataProvider('providesDocumentTypeScopes')]
    public function test_scope_where_document_type_filters_by_type(DteType $type): void
    {
        $results = SiiDte::whereDocumentType($type)->pluck('id');

        static::assertCount(1, $results);
    }
}
