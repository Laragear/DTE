<?php

namespace Laragear\Dte\Builders;

use DateTimeImmutable;
use Laragear\Dte\Builders\Concerns\HasExemptions;
use Laragear\Dte\Builders\Concerns\HasItems;
use Laragear\Dte\Builders\Concerns\HasPaymentTerms;
use Laragear\Dte\Builders\Concerns\HasReferences;
use Laragear\Dte\Data\PaymentTermData;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\SiiDtePayload;
use LogicException;

class InvoiceBuilder extends DocumentBuilder
{
    use HasExemptions;
    use HasItems;
    use HasPaymentTerms;
    use HasReferences;

    /**
     * Configure an electronic exempt invoice.
     */
    public function asExempt(?int $amount = null): static
    {
        return $this->markAsTaxExempt($amount);
    }

    /**
     * Return invoice type 33 or exempt invoice type 34.
     */
    public function documentType(): DteType
    {
        return $this->isTaxExempt() ? DteType::InvoiceExempt : DteType::Invoice;
    }

    /**
     * Apply global invoice exemption rules to calculated totals.
     *
     * @return array{net: int, exempt: int, tax: int, total: int}
     */
    protected function calculatedTotals(): array
    {
        if (!$this->isTaxExempt()) {
            return parent::calculatedTotals();
        }

        $amount = $this->exemptAmountOverride() ?? $this->allItemsAmount();

        return [
            'net' => 0,
            'exempt' => $amount,
            'tax' => 0,
            'total' => $amount,
            'non_billable' => $this->nonBillableAmount,
        ];
    }

    /**
     * Ensure taxable invoices contain a taxable line.
     */
    protected function validateSpecific(): void
    {
        $this->validateB2bReceiver();

        if (!$this->isTaxExempt() && $this->netAmount() === 0 && $this->exemptAmount() > 0) {
            throw new LogicException('An invoice containing only exempt items must use document type 34.');
        }
    }

    /**
     * Return exemption input for payload persistence.
     *
     * @return array<string, mixed>
     */
    protected function additionalData(): array
    {
        return [
            'tax_exempt' => $this->isTaxExempt(),
            'exempt_amount_override' => $this->exemptAmountOverride(),
            'payment' => $this->paymentTermsData(),
        ];
    }

    /**
     * Restore the exemption and payment state from the persisted payload.
     */
    protected function hydrateAdditional(SiiDtePayload $payload): void
    {
        $idDoc = $payload->header_id_doc;

        $this->taxExempt = (bool) $idDoc->tax_exempt;
        $this->exemptAmountOverride = $idDoc->exempt_amount_override;

        if ($payment = $idDoc['payment']) {
            $this->paymentTerm = PaymentTermData::make(
                $payment['condition'],
                new DateTimeImmutable($payment['expiration_date']),
            );
        }
    }

    /**
     * Sum every invoice item regardless of its tax indicator.
     */
    protected function allItemsAmount(): int
    {
        $amount = 0;

        foreach ($this->items() as $item) {
            $amount += $this->itemAmount($item);
        }

        return $amount;
    }
}
