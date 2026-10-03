<?php

namespace Laragear\Dte\Builders\Concerns;

use InvalidArgumentException;

trait HasExemptions
{
    protected bool $taxExempt = false;

    protected ?int $exemptAmountOverride = null;

    /**
     * Mark the document as globally exempt from IVA.
     */
    public function markAsTaxExempt(?int $amount = null): static
    {
        if ($amount !== null && $amount < 0) {
            throw new InvalidArgumentException('The exempt amount override cannot be negative.');
        }

        $this->taxExempt = true;
        $this->exemptAmountOverride = $amount;

        return $this;
    }

    /**
     * Determine whether the document is globally exempt from IVA.
     */
    public function isTaxExempt(): bool
    {
        return $this->taxExempt;
    }

    /**
     * Return the explicit exempt amount when one was configured.
     */
    public function exemptAmountOverride(): ?int
    {
        return $this->exemptAmountOverride;
    }
}
