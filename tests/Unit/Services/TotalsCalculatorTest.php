<?php

namespace Tests\Unit\Services;

use Laragear\Dte\Data\Item;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Services\TotalsCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TotalsCalculatorTest extends TestCase
{
    /**
     * Provides items with a taxable and an exempt line.
     *
     * @return array<string, array{0: list<Item>, 1: array{net: int, exempt: int, tax: int, total: int, taxes: array<int, int>}}>
     */
    public static function providesBaseSums(): array
    {
        return [
            'taxable only' => [
                [new Item('A', 1000, 2)],
                ['net' => 2000, 'exempt' => 0, 'tax' => 380, 'total' => 2380, 'taxes' => []],
            ],
            'exempt only' => [
                [new Item('A', 500, 1, exempt: true)],
                ['net' => 0, 'exempt' => 500, 'tax' => 0, 'total' => 500, 'taxes' => []],
            ],
            'mixed' => [
                [new Item('A', 1000, 1), new Item('B', 200, 1, exempt: true)],
                ['net' => 1000, 'exempt' => 200, 'tax' => 190, 'total' => 1390, 'taxes' => []],
            ],
        ];
    }

    #[DataProvider('providesBaseSums')]
    public function test_calculates_base_sums_and_iva(array $items, array $expected): void
    {
        static::assertSame($expected, $this->calculate($items, DteType::Invoice));
    }

    public function test_applies_percentage_discount_on_net(): void
    {
        $items = [new Item('A', 1000, 10)];

        // 10% discount: net 10000 - 1000 = 9000, IVA 1710, total 10710.
        static::assertSame(
            ['net' => 9000, 'exempt' => 0, 'tax' => 1710, 'total' => 10710, 'taxes' => []],
            $this->calculate($items, DteType::Invoice, [['type' => 'D', 'value_type' => '%', 'value' => 10, 'target' => 0]]),
        );
    }

    public function test_applies_fixed_surcharge_on_exempt(): void
    {
        $items = [new Item('A', 500, 1, exempt: true)];

        // Surcharge $100 on exempt: 500 + 100 = 600, no IVA, total 600.
        static::assertSame(
            ['net' => 0, 'exempt' => 600, 'tax' => 0, 'total' => 600, 'taxes' => []],
            $this->calculate($items, DteType::Invoice, [['type' => 'R', 'value_type' => '$', 'value' => 100, 'target' => 1]]),
        );
    }

    public function test_recalculated_tax_targets_the_post_modifier_net(): void
    {
        $items = [new Item('A', 1000, 1)];

        // 50% surcharge: net 1500, IVA recalculated to 285, total 1785.
        static::assertSame(
            ['net' => 1500, 'exempt' => 0, 'tax' => 285, 'total' => 1785, 'taxes' => []],
            $this->calculate($items, DteType::Invoice, [['type' => 'R', 'value_type' => '%', 'value' => 50, 'target' => 0]]),
        );
    }

    public function test_untouched_totals_keep_the_original_tax(): void
    {
        $items = [new Item('A', 1000.5, 1)];

        static::assertSame(
            ['net' => 1001, 'exempt' => 0, 'tax' => 190, 'total' => 1191, 'taxes' => []],
            $this->calculate($items, DteType::Invoice),
        );
    }

    public function test_retentions_subtract_and_additional_taxes_add(): void
    {
        $items = [
            new Item('A', 1000, 1, taxes: ['15' => 100]),
            new Item('B', 1000, 1, taxes: ['25' => 50]),
        ];

        $totals = $this->calculate($items, DteType::Invoice);

        static::assertSame(2000, $totals['net']);
        static::assertSame(380, $totals['tax']);
        // Total: 2000 + 0 + 380 + 50 (additional) - 100 (retention) = 2330.
        static::assertSame(2330, $totals['total']);
        static::assertSame(['15' => 100, '25' => 50], $totals['taxes']);
    }

    public function test_exempt_receipt_has_no_tax_even_with_modifiers(): void
    {
        $items = [new Item('A', 1000, 1)];

        static::assertSame(
            ['net' => 1500, 'exempt' => 0, 'tax' => 0, 'total' => 1500, 'taxes' => []],
            $this->calculate($items, DteType::ExemptReceipt, [['type' => 'R', 'value_type' => '%', 'value' => 50, 'target' => 0]]),
        );
    }

    public function test_exempt_invoice_uses_the_override_amount(): void
    {
        $items = [new Item('A', 1000, 1)];

        static::assertSame(
            ['net' => 0, 'exempt' => 5000, 'tax' => 0, 'total' => 5000, 'taxes' => []],
            $this->calculate($items, DteType::InvoiceExempt, exemptOverride: 5000),
        );
    }

    public function test_exempt_invoice_falls_back_to_all_items(): void
    {
        $items = [
            new Item('A', 1000, 1),
            new Item('B', 1000, 1, exempt: true),
        ];

        static::assertSame(
            ['net' => 0, 'exempt' => 2000, 'tax' => 0, 'total' => 2000, 'taxes' => []],
            $this->calculate($items, DteType::InvoiceExempt),
        );
    }

    public function test_zero_quantity_item_amounts_to_nothing(): void
    {
        $items = [
            new Item('A', 1000, 1),
            new Item('Tax only', 1000, 0, taxes: ['25' => 50]),
        ];

        $totals = $this->calculate($items, DteType::Invoice);

        static::assertSame(1000, $totals['net']);
        // Total: 1000 + 0 + 190 (IVA) + 50 (additional tax) = 1240.
        static::assertSame(1240, $totals['total']);
    }

    public function test_item_discounts_reduce_the_line_amount(): void
    {
        $items = [
            new Item('A', 1000, 2, discountPercentage: 10),
            new Item('B', 1000, 1, discountAmount: 100),
        ];

        // 2000 - 200 + 1000 - 100 = 2700, IVA 513, total 3213.
        static::assertSame(
            ['net' => 2700, 'exempt' => 0, 'tax' => 513, 'total' => 3213, 'taxes' => []],
            $this->calculate($items, DteType::Invoice),
        );
    }

    public function test_rounds_up_half_percentages(): void
    {
        $items = [new Item('A', 999, 1)];

        // 999 * 0.19 = 189.81 -> 190.
        static::assertSame(190, $this->calculate($items, DteType::Invoice)['tax']);
    }

    /**
     * Calculate totals for the given items and modifiers.
     *
     * @param  list<Item>  $items
     * @param  list<array<string, mixed>>  $modifiers
     */
    protected function calculate(array $items, DteType $type, array $modifiers = [], ?int $exemptOverride = null): array
    {
        return app(TotalsCalculator::class)->calculate($items, $modifiers, $type, $exemptOverride);
    }
}
