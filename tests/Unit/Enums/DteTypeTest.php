<?php

namespace Tests\Unit\Enums;

use Generator;
use Laragear\Dte\Enums\DteType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DteTypeTest extends TestCase
{
    public function test_test_invoice_is_default(): void
    {
        static::assertSame(DteType::Invoice, DteType::DEFAULT);
    }

    public static function providesDocumentTypes(): Generator
    {
        yield 'InvoicePhysical' => ['InvoicePhysical', 30];
        yield 'InvoicePhysicalExempt' => ['InvoicePhysicalExempt', 32];
        yield 'Invoice' => ['Invoice', 33];
        yield 'InvoiceExempt' => ['InvoiceExempt', 34];
        yield 'Receipt' => ['Receipt', 39];
        yield 'ExemptReceipt' => ['ExemptReceipt', 41];
        yield 'InvoiceLiquidation' => ['InvoiceLiquidation', 43];
        yield 'PurchaseInvoice' => ['PurchaseInvoice', 46];
        yield 'DispatchGuide' => ['DispatchGuide', 52];
        yield 'DebitNote' => ['DebitNote', 56];
        yield 'CreditNote' => ['CreditNote', 61];
    }

    #[DataProvider('providesDocumentTypes')]
    public function test_defines_supported_document_types(string $type, int $code): void
    {
        static::assertSame(DteType::{$type}, DteType::from($code));
    }

    public function test_is_receipt(): void
    {
        static::assertTrue(DteType::Receipt->isReceipt());
        static::assertTrue(DteType::ExemptReceipt->isReceipt());
        static::assertFalse(DteType::Invoice->isReceipt());
        static::assertFalse(DteType::CreditNote->isReceipt());
    }

    public static function providesDocumentTypesWithSchema(): Generator
    {
        yield 'InvoicePhysical' => [DteType::InvoicePhysical, null];
        yield 'InvoicePhysicalExempt' => [DteType::InvoicePhysicalExempt, null];

        yield 'Invoice' => [DteType::Invoice, 'DTE_v10.xsd'];
        yield 'InvoiceExempt' => [DteType::InvoiceExempt, 'DTE_v10.xsd'];
        yield 'Receipt' => [DteType::Receipt, 'DTE_v10.xsd'];
        yield 'ExemptReceipt' => [DteType::ExemptReceipt, 'DTE_v10.xsd'];
        yield 'InvoiceLiquidation' => [DteType::InvoiceLiquidation, 'DTE_v10.xsd'];
        yield 'PurchaseInvoice' => [DteType::PurchaseInvoice, 'DTE_v10.xsd'];
        yield 'DispatchGuide' => [DteType::DispatchGuide, 'DTE_v10.xsd'];
        yield 'DebitNote' => [DteType::DebitNote, 'DTE_v10.xsd'];
        yield 'CreditNote' => [DteType::CreditNote, 'DTE_v10.xsd'];
    }

    #[DataProvider('providesDocumentTypesWithSchema')]
    public function test_schema_xsd(DteType $type, ?string $schema): void
    {
        static::assertSame($schema, $type->schemaXsd());
    }
}
