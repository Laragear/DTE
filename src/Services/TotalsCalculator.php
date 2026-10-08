<?php

namespace Laragear\Dte\Services;

use Laragear\Dte\Data\Item;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\SiiTaxes;

use function round;

/**
 * Calculates document totals from detail items, global modifiers and retentions.
 */
class TotalsCalculator
{
    /**
     * Return the document totals after all document-specific rules.
     *
     * @param  list<Item>  $items
     * @param  array<array-key, array<string, mixed>>  $modifiers  Global modifier rows: type (D|R), value_type (%|$), value, target.
     * @param  int|null  $exemptAmountOverride  Forced exempt amount for exempt invoices.
     * @param  array{net: int, exempt: int, tax: int, total?: int, non_billable?: int}|null  $baseTotals  Pre-computed base totals; item sums when null.
     * @return array{net: int, exempt: int, tax: int, total: int, taxes: array<int, int>}
     */
    public function calculate(
        array $items,
        array $modifiers,
        DteType $documentType,
        ?int $exemptAmountOverride = null,
        ?array $baseTotals = null,
    ): array {
        if ($documentType === DteType::InvoiceExempt) {
            $exempt = $exemptAmountOverride ?? $this->allItemsAmount($items);

            return ['net' => 0, 'exempt' => $exempt, 'tax' => 0, 'total' => $exempt, 'taxes' => $this->aggregateTaxes($items)];
        }

        $base = $baseTotals;

        if ($base === null) {
            $base = [
                'net' => $net = $this->taxableAmount($items),
                'exempt' => $this->exemptAmount($items),
                'tax' => $this->taxFor($net, $documentType),
            ];
        }

        ['net' => $net, 'exempt' => $exempt, 'tax' => $tax] = $this->applyGlobalModifiers($base, $modifiers, $documentType);

        $total = $net + $exempt + $tax + $this->retentionsEffect($items);

        return [
            'net' => $net,
            'exempt' => $exempt,
            'tax' => $tax,
            'total' => $total,
            'taxes' => $this->aggregateTaxes($items),
        ];
    }

    /**
     * Sum the taxable item amounts.
     *
     * @param  list<Item>  $items
     */
    private function taxableAmount(array $items): int
    {
        $amount = 0;

        foreach ($items as $item) {
            if ($item->exempt) {
                continue;
            }

            $amount += $item->calculateAmount();
        }

        return $amount;
    }

    /**
     * Sum the exempt item amounts.
     *
     * @param  list<Item>  $items
     */
    private function exemptAmount(array $items): int
    {
        $amount = 0;

        foreach ($items as $item) {
            if (! $item->exempt) {
                continue;
            }

            $amount += $item->calculateAmount();
        }

        return $amount;
    }

    /**
     * Sum every item amount regardless of its tax indicator.
     *
     * @param  list<Item>  $items
     */
    private function allItemsAmount(array $items): int
    {
        $amount = 0;

        foreach ($items as $item) {
            $amount += $item->calculateAmount();
        }

        return $amount;
    }

    /**
     * Apply global modifiers (discounts/surcharges) and recalculate IVA.
     *
     * @param  array<array-key, array<string, mixed>>  $modifiers
     * @param  array{net: int, exempt: int, tax: int, total?: int, non_billable?: int}  $base
     * @return array{net: int, exempt: int, tax: int}
     */
    private function applyGlobalModifiers(array $base, array $modifiers, DteType $documentType): array
    {
        $net = $base['net'];
        $exempt = $base['exempt'];
        $modified = false;

        foreach ($modifiers as $modifier) {
            $modified = true;
            $sourceAmount = $modifier['target'] === 1 ? $exempt : $net;

            $modValue = $modifier['value_type'] === '%'
                ? (int) round($sourceAmount * ($modifier['value'] / 100), mode: PHP_ROUND_HALF_UP)
                : (int) round($modifier['value']);

            $effect = $modifier['type'] === 'D' ? -$modValue : $modValue;

            if ($modifier['target'] === 1) {
                $exempt += $effect;

                continue;
            }

            $net += $effect;
        }

        $tax = $modified
            ? $this->taxFor($net, $documentType)
            : $base['tax'];

        return ['net' => $net, 'exempt' => $exempt, 'tax' => $tax];
    }

    /**
     * Calculate the IVA amount for the given net amount.
     */
    private function taxFor(int $net, DteType $documentType): int
    {
        if ($documentType === DteType::InvoiceExempt || $documentType === DteType::ExemptReceipt) {
            return 0;
        }

        return (int) round($net * SiiTaxes::ivaDecimal(), mode: PHP_ROUND_HALF_UP);
    }

    /**
     * Calculate the net effect of retentions (subtract) and additional taxes (add).
     *
     * @param  list<Item>  $items
     */
    private function retentionsEffect(array $items): int
    {
        $taxesEffect = 0;

        foreach ($items as $item) {
            foreach ($item->taxes as $code => $amount) {
                $taxesEffect += SiiTaxes::isRetention($code) ? -$amount : $amount;
            }
        }

        return $taxesEffect;
    }

    /**
     * Aggregate item-level taxes into a keyed array.
     *
     * @param  list<Item>  $items
     * @return array<int, int> [ taxCode => totalAmount ]
     */
    private function aggregateTaxes(array $items): array
    {
        $taxes = [];

        foreach ($items as $item) {
            foreach ($item->taxes as $taxCode => $amount) {
                $taxes[$taxCode] = ($taxes[$taxCode] ?? 0) + $amount;
            }
        }

        return $taxes;
    }
}
