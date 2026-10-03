<?php

namespace Laragear\Dte\Builders\Concerns;

use InvalidArgumentException;
use Laragear\Dte\Enums\ModifierTarget;

trait HasGlobalModifiers
{
    /** @var list<array{type: string, value_type: string, value: float|int, target: int, description: string|null}> */
    protected array $globalModifiers = [];

    /**
     * Add a global discount to the document.
     */
    public function globalDiscount(
        float|int $value,
        bool $isPercent = false,
        ModifierTarget $target = ModifierTarget::DEFAULT,
        ?string $description = null,
    ): static {
        // Target: 0 = Affected/Taxable (Default; IndExeDR omitted)
        // 1 = Exempt (IndExeDR=1)
        // 2 = Non-billable (IndExeDR=2)
        return $this->addGlobalModifier('D', $value, $isPercent, $target, $description);
    }

    /**
     * Add a global surcharge to the document.
     */
    public function globalSurcharge(
        float|int $value,
        bool $isPercent = false,
        ModifierTarget $target = ModifierTarget::DEFAULT,
        ?string $description = null,
    ): static {
        // Target: 0 = Affected/Taxable (Default; IndExeDR omitted)
        // 1 = Exempt (IndExeDR=1)
        // 2 = Non-billable (IndExeDR=2)
        return $this->addGlobalModifier('R', $value, $isPercent, $target, $description);
    }

    /**
     * Internal method to add a modifier.
     */
    protected function addGlobalModifier(
        string $type,
        float|int $value,
        bool $isPercent,
        ModifierTarget $target,
        ?string $description,
    ): static {
        if ($value < 0) {
            throw new InvalidArgumentException('Global modifier value must be positive.');
        }

        if ($isPercent && $value > 100) {
            throw new InvalidArgumentException('Global percentage modifier cannot be greater than 100.');
        }

        $this->globalModifiers[] = [
            'type' => $type,
            'value_type' => $isPercent ? '%' : '$',
            'value' => $value,
            'target' => $target->value,
            'description' => $description,
        ];

        return $this;
    }

    /**
     * Return the global modifiers.
     */
    public function globalModifiers(): array
    {
        return $this->globalModifiers;
    }
}
