<?php

namespace Tests\Unit\Builders\Fixtures;

use DateTimeImmutable;
use Generator;
use Laragear\Dte\Builders\CreditNoteBuilder;
use Laragear\Dte\Builders\DebitNoteBuilder;
use Laragear\Dte\Builders\DispatchGuideBuilder;
use Laragear\Dte\Builders\InvoiceBuilder;
use Laragear\Dte\Builders\InvoiceLiquidationBuilder;
use Laragear\Dte\Builders\PurchaseInvoiceBuilder;
use Laragear\Dte\Builders\ReceiptBuilder;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Data\ReceiverData;
use Laragear\Dte\Data\ReferenceData;
use Laragear\Dte\Enums\DteType;
use Laragear\Rut\Facades\Generator as RutGenerator;

class BuilderFixture
{
    /**
     * Return every concrete document builder and its SII type.
     *
     * @return Generator<string, array{class-string, DteType}>
     */
    public static function builders(): Generator
    {
        yield 'invoice' => [InvoiceBuilder::class, DteType::Invoice];
        yield 'receipt' => [ReceiptBuilder::class, DteType::Receipt];
        yield 'credit note' => [CreditNoteBuilder::class, DteType::CreditNote];
        yield 'debit note' => [DebitNoteBuilder::class, DteType::DebitNote];
        yield 'dispatch guide' => [DispatchGuideBuilder::class, DteType::DispatchGuide];
        yield 'purchase invoice' => [PurchaseInvoiceBuilder::class, DteType::PurchaseInvoice];
        yield 'invoice liquidation' => [InvoiceLiquidationBuilder::class, DteType::InvoiceLiquidation];
    }

    /**
     * Return note builders requiring document references.
     *
     * @return Generator<string, array{class-string, string}>
     */
    public static function noteBuilders(): Generator
    {
        yield 'credit note' => [CreditNoteBuilder::class, 'A Nota de Crédito must contain at least one reference.'];
        yield 'debit note' => [DebitNoteBuilder::class, 'A Nota de Débito must contain at least one reference.'];
    }

    /**
     * Create valid issuer data.
     */
    public static function issuer(): IssuerData
    {
        return IssuerData::make(
            RutGenerator::asCompanies()->makeOne(),
            'Example Company LLC',
            'Software services',
            '620200',
            'Main Street 123',
            'Santiago',
            '2025-01-01',
            80,
            'Santiago',
        );
    }

    /**
     * Create valid receiver data.
     */
    public static function receiver(): ReceiverData
    {
        return ReceiverData::make(
            RutGenerator::asCompanies()->makeOne(),
            'Customer Company LLC',
            'Retail',
            'customer@example.com',
            'Customer Street 456',
            'Providencia',
            'Santiago',
        );
    }

    /**
     * Create a taxable detail line.
     */
    public static function item(): Item
    {
        return Item::make('Consulting service', 1000, quantity: 2, discountPercentage: 10);
    }

    /**
     * Create a valid invoice reference.
     */
    public static function reference(): ReferenceData
    {
        return ReferenceData::make(DteType::Invoice, '100', new DateTimeImmutable('2026-08-01'), 'Price correction', 3);
    }
}
