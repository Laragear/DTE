<?php

namespace Laragear\Dte\Enums;

use Laragear\Dte\Builders\CreditNoteBuilder;
use Laragear\Dte\Builders\DebitNoteBuilder;
use Laragear\Dte\Builders\DispatchGuideBuilder;
use Laragear\Dte\Builders\DocumentBuilder;
use Laragear\Dte\Builders\InvoiceBuilder;
use Laragear\Dte\Builders\InvoiceLiquidationBuilder;
use Laragear\Dte\Builders\PurchaseInvoiceBuilder;
use Laragear\Dte\Builders\ReceiptBuilder;
use LogicException;

enum DteType: int
{
    use Concerns\EnumHelpers;

    public const self DEFAULT = self::Invoice;

    /**
     * DTE types that can be transferred through an AEC cession.
     *
     * @var list<DteType>
     */
    public const array AEC_TRANSFERABLE = [
        self::Invoice,
        self::InvoiceExempt,
        self::InvoiceLiquidation,
        self::PurchaseInvoice,
    ];

    /**
     * DTE types the SII Reclamo Webservice operates on.
     *
     * @var list<DteType>
     */
    public const array CLAIMABLE = [
        self::Invoice,
        self::InvoiceExempt,
        self::InvoiceLiquidation,
    ];

    /** Paper invoice (Factura) */
    case InvoicePhysical = 30;

    /** Exempt paper invoice (Factura de Ventas y Servicios no Afectos o exentos IVA) */
    case InvoicePhysicalExempt = 32;

    /** Electronic invoice (Factura Electrónica) */
    case Invoice = 33;

    /** Exempt electronic invoice (Factura Electrónica de Ventas y Servicios no afectos o exentos IVA) */
    case InvoiceExempt = 34;

    /** Electronic receipt. */
    case Receipt = 39;

    /** Exempt electronic receipt. */
    case ExemptReceipt = 41;

    /** Electronic invoice liquidation. */
    case InvoiceLiquidation = 43;

    /** Electronic purchase invoice. */
    case PurchaseInvoice = 46;

    /** Electronic dispatch guide. */
    case DispatchGuide = 52;

    /** Electronic debit note. */
    case DebitNote = 56;

    /** Electronic credit note. */
    case CreditNote = 61;

    /**
     * Check if the DTE Type can be ammended.
     */
    public function isAmendable(): bool
    {
        return $this === DteType::Invoice
            || $this === DteType::InvoiceExempt
            || $this === DteType::InvoiceLiquidation;
    }

    /**
     * Check if the DTE Type can be claimed through the SII Reclamo Webservice.
     */
    public function isClaimable(): bool
    {
        return in_array($this, self::CLAIMABLE, true);
    }

    /**
     * Check if the DTE Type cannot be claimed through the SII Reclamo Webservice.
     */
    public function isNotClaimable(): bool
    {
        return ! $this->isClaimable();
    }

    /**
     * Check if the DTE Type cannot be amended.
     */
    public function isNotAmendable(): bool
    {
        return ! $this->isAmendable();
    }

    /**
     * Check if the DTE Type is essentially a receipt.
     */
    public function isReceipt(): bool
    {
        return $this === self::Receipt || $this === self::ExemptReceipt;
    }

    /**
     * Returns the label.
     */
    public function label(): string
    {
        return match ($this) {
            self::InvoicePhysical => 'Factura',
            self::InvoicePhysicalExempt => 'Factura Exenta',
            self::Invoice => 'Factura Electrónica',
            self::InvoiceExempt => 'Factura Electrónica Exenta',
            self::Receipt => 'Boleta',
            self::ExemptReceipt => 'Boleta Exenta',
            self::InvoiceLiquidation => 'Liquidación de Factura',
            self::PurchaseInvoice => 'Factura de Compra',
            self::DispatchGuide => 'Guía de Despacho',
            self::DebitNote => 'Nota de Débito',
            self::CreditNote => 'Nota de Crédito',
        };
    }

    /**
     * Returns the schema the document is bound to, or null if it not supported by it.
     */
    public function schemaXsd(): ?string
    {
        return match ($this) {
            self::Invoice,
            self::InvoiceExempt,
            self::Receipt,
            self::ExemptReceipt,
            self::InvoiceLiquidation,
            self::PurchaseInvoice,
            self::DispatchGuide,
            self::DebitNote,
            self::CreditNote => 'DTE_v10.xsd',
            default => null
        };
    }

    /**
     * Returns the builder class for this DTE type.
     *
     * @return class-string<DocumentBuilder>
     */
    public function builderClass(): string
    {
        return match ($this) {
            DteType::Invoice => InvoiceBuilder::class,
            DteType::Receipt => ReceiptBuilder::class,
            DteType::CreditNote => CreditNoteBuilder::class,
            DteType::DebitNote => DebitNoteBuilder::class,
            DteType::DispatchGuide => DispatchGuideBuilder::class,
            DteType::PurchaseInvoice => PurchaseInvoiceBuilder::class,
            DteType::InvoiceLiquidation => InvoiceLiquidationBuilder::class,
            default => throw new LogicException(
                "DTE type [{$this->value}] does not have a builder class.",
            ),
        };
    }
}
