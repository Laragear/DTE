<?php

namespace Laragear\Dte\Builders\Concerns;

use Laragear\Dte\Data\Item;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\SiiTaxes;
use function round;

/**
 * Calculates document totals with global modifiers and tax retention effects.
 */
trait TotalsCalculator
{
    abstract public function totals(): array;

    abstract public function globalModifiers(): array;

    abstract public function documentType();

    /**
     * @return list<Item>
     */
    abstract public function items(): array;

    /**
     * Return document totals after applying document-specific rules.
     *
     * @return array{net: int, exempt: int, tax: int, total: int, non_billable: int}
     */
    protected function calculatedTotals(): array
    {
        ['net' => $net, 'exempt' => $exempt, 'tax' => $tax] = $this->applyGlobalModifiers(
            $this->totals(),
        );

        $taxesEffect = $this->calculateRetentionsEffect();

        return [
            'net' => $net,
            'exempt' => $exempt,
            'tax' => $tax,
            'total' => $net + $exempt + $tax + $taxesEffect,
            'non_billable' => $this->nonBillableAmount,
        ];
    }

    /**
     * Apply global modifiers (discounts/surcharges) and recalculate IVA.
     *
     * @param  array{net: int, exempt: int, tax: int, total: int}  $base
     * @return array{net: int, exempt: int, tax: int}
     */
    protected function applyGlobalModifiers(array $base): array
    {
        $net = $base['net'];
        $exempt = $base['exempt'];
        $modified = false;

        foreach ($this->globalModifiers() as $modifier) {
            $modified = true;
            $isDiscount = $modifier['type'] === 'D';
            $sourceAmount = $modifier['target'] === 1 ? $exempt : $net;

            $modValue = $modifier['value_type'] === '%'
                ? (int) round($sourceAmount * ($modifier['value'] / 100), mode: PHP_ROUND_HALF_UP)
                : (int) round($modifier['value']);

            $effect = $isDiscount ? -$modValue : $modValue;

            if ($modifier['target'] === 1) {
                $exempt += $effect;
            } else {
                $net += $effect;
            }
        }

        $tax = $modified
            ? $this->recalculateTaxAfterModifiers($net)
            : $base['tax'];

        return ['net' => $net, 'exempt' => $exempt, 'tax' => $tax];
    }

    /**
     * Recalculate the tax amount based on the post-modifier net amount.
     */
    protected function recalculateTaxAfterModifiers(int $net): int
    {
        if ($this->documentType() === DteType::InvoiceExempt || $this->documentType() === DteType::ExemptReceipt) {
            return 0;
        }

        return (int) round($net * SiiTaxes::ivaDecimal(), mode: PHP_ROUND_HALF_UP);
    }

    /**
     * Calculate the net effect of retentions (subtract) and additional taxes (add).
     */
    protected function calculateRetentionsEffect(): int
    {
        $taxesEffect = 0;

        foreach ($this->items() as $item) {
            foreach ($item->taxes as $code => $amount) {
                if (SiiTaxes::isRetention($code)) {
                    $taxesEffect -= $amount;
                } else {
                    $taxesEffect += $amount;
                }
            }
        }

        return $taxesEffect;
    }
}
