<?php

namespace Laragear\Dte\Casts;

use Laragear\Dte\Validation\DteRules;
use XMLWriter;

use function blank;
use function round;

/**
 * The Documento > Detalle block: the item detail lines.
 *
 * @property list<array<string, mixed>> $items The detail lines:
 *     name, unit_price, quantity, description, unit, code, code_type,
 *     discount_percentage, discount_amount, exempt, taxes.
 */
class DteDetailItems extends DteBlock
{
    /**
     * Validation rules for the block attributes.
     *
     * @var array<string, string>
     */
    public const array RULES = DteRules::DETAIL_ITEMS;

    /**
     * The default attributes applied when a key is missing.
     *
     * @return array<string, mixed>
     */
    protected static function defaults(): array
    {
        return ['items' => []];
    }

    /**
     * Append every Detalle line.
     */
    public function toXml(XMLWriter $writer): void
    {
        foreach ($this['items'] as $index => $item) {
            $writer->startElement('Detalle');

            $writer->writeElement('NroLinDet', (string) ($index + 1));
            $this->appendItemCode($writer, $item);
            $this->positiveElement($writer, 'IndExe', $item['exempt'] ? 1 : 0);
            $this->appendItemValues($writer, $item);

            $writer->endElement(); // Detalle
        }
    }

    /**
     * Append an optional item code.
     *
     * @param  array<string, mixed>  $item
     */
    protected function appendItemCode(XMLWriter $writer, array $item): void
    {
        if (blank($item['code'] ?? null)) {
            return;
        }

        $writer->startElement('CdgItem');
        $writer->writeElement('TpoCodigo', $item['code_type'] ?? 'INT1');
        $writer->writeElement('VlrCodigo', $item['code']);
        $writer->endElement(); // CdgItem
    }

    /**
     * Append item description and monetary values.
     *
     * @param  array<string, mixed>  $item
     */
    protected function appendItemValues(XMLWriter $writer, array $item): void
    {
        $writer->writeElement('NmbItem', (string) $item['name']);
        $this->optionalElement($writer, 'DscItem', $item['description'] ?? null);

        $quantity = (float) $item['quantity'];

        // A zero quantity marks a tax-only line. The SII rejects a literal zero
        // because Dec12_6Type has minInclusive 0.000001, so it is emitted as 1
        // and the unit price is left out entirely.
        $writer->writeElement('QtyItem', $this->decimal($quantity <= 0 ? 1.0 : $quantity));

        $this->optionalElement($writer, 'UnmdItem', $item['unit'] ?? null);

        $itemTotal = $this->itemTotal($item);

        if ($itemTotal > 0 && $quantity > 0) {
            $writer->writeElement('PrcItem', $this->decimal((float) $item['unit_price']));
        }

        $this->appendItemDiscount($writer, $item);
        $this->appendItemTaxes($writer, $item);

        $writer->writeElement('MontoItem', (string) $itemTotal);
    }

    /**
     * Append the line discount as a percentage and its resolved amount.
     *
     * @param  array<string, mixed>  $item
     */
    protected function appendItemDiscount(XMLWriter $writer, array $item): void
    {
        $discountAmount = $this->itemDiscountAmount($item);

        if ($discountAmount <= 0) {
            return;
        }

        $percentage = (float) ($item['discount_percentage'] ?? 0);

        if ($percentage > 0) {
            $writer->writeElement('DescuentoPct', (string) $percentage);
        }

        $writer->writeElement('DescuentoMonto', (string) round($discountAmount));
    }

    /**
     * Append every additional tax code applied to the line.
     *
     * @param  array<string, mixed>  $item
     */
    protected function appendItemTaxes(XMLWriter $writer, array $item): void
    {
        if (($item['taxes'] ?? []) !== []) {
            foreach ($item['taxes'] as $taxCode => $amount) {
                $writer->writeElement('CodImpAdic', (string) $taxCode);
            }
        }
    }

    /**
     * Calculate one persisted detail line total.
     *
     * @param  array<string, mixed>  $item
     */
    protected function itemTotal(array $item): int
    {
        $gross = $item['unit_price'] * $item['quantity'];
        $discount = $this->itemDiscountAmount($item);

        return round($gross - $discount);
    }

    /**
     * Resolve the line discount amount for XML output.
     *
     * @param  array<string, mixed>  $item
     */
    protected function itemDiscountAmount(array $item): float
    {
        // Priority (SII compliance):
        // 1. discount_percentage > 0 → auto-calculate from percentage, emit both fields
        // 2. discount_amount > 0 (no percentage) → use the fixed amount directly
        // 3. Both absent or non-positive → no discount
        $percentage = (float) ($item['discount_percentage'] ?? 0);

        if ($percentage > 0) {
            return (float) $item['unit_price'] * (float) $item['quantity'] * ($percentage / 100);
        }

        return max(0, (float) ($item['discount_amount'] ?? 0));
    }
}
