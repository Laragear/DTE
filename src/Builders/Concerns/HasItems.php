<?php

namespace Laragear\Dte\Builders\Concerns;

use InvalidArgumentException;
use Laragear\Dte\Contracts\Itemable;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Enums\SiiTaxes;
use OverflowException;
use function count;
use function is_string;
use function round;

trait HasItems
{
    protected const int MAX_ITEMS = 60;

    /** @var list<Item> */
    protected array $items = [];

    /**
     * Add a detail line to the document.
     */
    public function addItem(Itemable|Item|string $item, int|array|null $total = null, ?bool $isExempt = null): static
    {
        if ($item instanceof Itemable) {
            $item = $item->toItem();
        }

        if (is_string($item)) {
            $item = new Item($item, $total, 1, exempt: $isExempt ?? false);
        }

        if (count($this->items) >= static::MAX_ITEMS) {
            throw new OverflowException('A DTE cannot contain more than 60 item lines.');
        }

        if ($item->discountPercentage < 0 || $item->discountPercentage > 100) {
            throw new InvalidArgumentException('The item discount percentage must be between 0 and 100.');
        }

        if ($item->unitPrice < 0 ) {
            throw new InvalidArgumentException('The item unit price must not be negative.');
        }

        if ($item->quantity <= 0) {
            $item = new Item(
                $item->name,
                0.0,
                $item->quantity,
                $item->description,
                $item->unit,
                $item->code,
                $item->codeType,
                $item->discountPercentage,
                $item->exempt,
                $item->taxes,
            );
        } elseif ($item->unitPrice <= 0) {
            throw new InvalidArgumentException('The item unit price must be greater than zero.');
        }

        $this->items[] = $item;

        return $this;
    }

    /**
     * Return the document detail lines.
     *
     * @return list<Item>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * Return a line amount after its percentage discount.
     */
    public function itemAmount(Item $item): int
    {
        return $item->calculateAmount();
    }

    /**
     * Return the taxable net amount.
     */
    public function netAmount(): int
    {
        return $this->amountFor(false);
    }

    /**
     * Return the non-taxable or exempt amount.
     */
    public function exemptAmount(): int
    {
        return $this->amountFor(true);
    }

    /**
     * Return the value-added tax amount.
     */
    public function taxAmount(): int
    {
        return (int) round($this->netAmount() * SiiTaxes::ivaDecimal(), mode: PHP_ROUND_HALF_UP);
    }

    /**
     * Return the final amount payable.
     */
    public function totalAmount(): int
    {
        return $this->netAmount() + $this->exemptAmount() + $this->taxAmount();
    }

    /**
     * Return all calculated document totals.
     *
     * @return array{net: int, exempt: int, tax: int, total: int}
     */
    public function totals(): array
    {
        return [
            'net' => $this->netAmount(),
            'exempt' => $this->exemptAmount(),
            'tax' => $this->taxAmount(),
            'total' => $this->totalAmount(),
        ];
    }

    /**
     * Sum item amounts by their exemption state.
     */
    protected function amountFor(bool $exempt): int
    {
        $amount = 0;

        foreach ($this->items as $item) {
            if ($item->exempt === $exempt) {
                $amount += $this->itemAmount($item);
            }
        }

        return $amount;
    }
}
