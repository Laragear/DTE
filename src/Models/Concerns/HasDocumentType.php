<?php

namespace Laragear\Dte\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Laragear\Dte\Enums\DteType;

/**
 * @method Builder<static>|static whereDocumentType(DteType $type)
 * @method Builder<static>|static invoices()
 * @method Builder<static>|static exemptInvoices()
 * @method Builder<static>|static receipts()
 * @method Builder<static>|static creditNotes()
 * @method Builder<static>|static debitNotes()
 * @method Builder<static>|static dispatchGuides()
 * @method Builder<static>|static invoiceLiquidations()
 * @method Builder<static>|static purchaseInvoices()
 */
trait HasDocumentType
{
    /**
     * Local scope to filter records by DTE type.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function scopeWhereDocumentType(Builder $query, DteType $type): Builder
    {
        return $query->where('document_type', $type->value);
    }

    /**
     * Local scope to filter records by invoices.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function scopeInvoices(Builder $query): Builder
    {
        return $this->scopeWhereDocumentType($query, DteType::Invoice);
    }

    /**
     * Local scope to filter records by exempt invoices.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function scopeExemptInvoices(Builder $query): Builder
    {
        return $this->scopeWhereDocumentType($query, DteType::InvoiceExempt);
    }

    /**
     * Local scope to filter records by receipts.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function scopeReceipts(Builder $query): Builder
    {
        return $this->scopeWhereDocumentType($query, DteType::Receipt);
    }

    /**
     * Local scope to filter records by creditNotes.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function scopeCreditNotes(Builder $query): Builder
    {
        return $this->scopeWhereDocumentType($query, DteType::CreditNote);
    }

    /**
     * Local scope to filter records by debitNotes.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function scopeDebitNotes(Builder $query): Builder
    {
        return $this->scopeWhereDocumentType($query, DteType::DebitNote);
    }

    /**
     * Local scope to filter records by dispatchGuides.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function scopeDispatchGuides(Builder $query): Builder
    {
        return $this->scopeWhereDocumentType($query, DteType::DispatchGuide);
    }

    /**
     * Local scope to filter records by invoiceLiquidations.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function scopeInvoiceLiquidations(Builder $query): Builder
    {
        return $this->scopeWhereDocumentType($query, DteType::InvoiceLiquidation);
    }

    /**
     * Local scope to filter records by purchaseInvoices.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function scopePurchaseInvoices(Builder $query): Builder
    {
        return $this->scopeWhereDocumentType($query, DteType::PurchaseInvoice);
    }
}
