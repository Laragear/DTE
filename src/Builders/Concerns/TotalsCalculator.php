<?php

namespace Laragear\Dte\Builders\Concerns;

use Laragear\Dte\Data\Item;
use Laragear\Dte\Services\TotalsCalculator as TotalsCalculatorService;

/**
 * Calculates document totals with global modifiers and tax retention effects.
 */
trait TotalsCalculator
{
    /**
     * @return array{net: int, exempt: int, tax: int, total: int}
     */
    abstract public function totals(): array;

    /**
     * @return list<array{type: string, value_type: string, value: float|int, target: int, description: string|null}>
     */
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
        $totals = app(TotalsCalculatorService::class)->calculate(
            $this->items(),
            $this->globalModifiers(),
            $this->documentType(),
            baseTotals: $this->totals(),
        );

        $totals['non_billable'] = $this->nonBillableAmount;

        return $totals;
    }
}
