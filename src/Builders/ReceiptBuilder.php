<?php

namespace Laragear\Dte\Builders;

use Laragear\Dte\Builders\Concerns\HasItems;
use Laragear\Dte\Builders\Concerns\HasReferences;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\SiiRut;
use Laragear\Dte\Enums\SiiTaxes;
use Laragear\Rut\Rut;
use LogicException;
use function round;

class ReceiptBuilder extends DocumentBuilder
{
    use HasItems;
    use HasReferences;

    /**
     * Return electronic receipt type 39.
     */
    public function documentType(): DteType
    {
        return DteType::Receipt;
    }

    /**
     * Return net amount by removing IVA from gross consumer prices.
     */
    public function netAmount(): int
    {
        return (int) round($this->grossAmount() / (1 + SiiTaxes::ivaDecimal()), mode: PHP_ROUND_HALF_UP);
    }

    /**
     * Return IVA already included in gross consumer prices.
     */
    public function taxAmount(): int
    {
        return $this->grossAmount() - $this->netAmount();
    }

    /**
     * Return the gross amount payable.
     */
    public function totalAmount(): int
    {
        return $this->grossAmount();
    }

    /**
     * Use the official anonymous consumer RUT when no receiver is provided.
     */
    protected function receiverRut(): Rut
    {
        return $this->receiver?->rut ?? SiiRut::Consumer->toRut();
    }

    /**
     * Return consumer receiver data, falling back to anonymous SII defaults.
     *
     * @return array<string, mixed>
     */
    protected function receiverData(): array
    {
        if ($this->receiver !== null) {
            return parent::receiverData() ?? [];
        }

        // SII anonymous consumer defaults for boletas without an identified buyer.
        return [
            'rut' => SiiRut::Consumer->toRut()->formatRaw(),
            'name' => 'Sin nombre',
            'activity' => null,
            'email' => null,
            'address' => null,
            'commune' => null,
            'city' => null,
        ];
    }

    /**
     * Reject exempt lines because this builder emits affected type 39 only.
     */
    protected function validateSpecific(): void
    {
        if ($this->exemptAmount() > 0) {
            throw new LogicException('Electronic receipt type 39 cannot contain exempt items.');
        }
    }

    /**
     * Sum gross receipt line amounts.
     */
    protected function grossAmount(): int
    {
        $amount = 0;

        foreach ($this->items() as $item) {
            $amount += $this->itemAmount($item);
        }

        return $amount;
    }
}
