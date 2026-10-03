<?php

namespace Laragear\Dte\Data;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonSerializable;
use Laragear\Dte\Validation\DteRules;
use LogicException;

use function json_decode;
use function json_encode;
use function max;
use function round;
use function validator;

/**
 * @implements ArrayAccess<string, mixed>
 */
readonly class Item implements Arrayable, ArrayAccess, Jsonable, JsonSerializable
{
    /**
     * Create a new Item Data instance.
     *
     * @param  array<string, int>  $taxes  Dictionary of tax codes to their applied amounts on this item
     */
    public function __construct(
        public string $name,
        public float $unitPrice,
        public float $quantity = 1.0,
        public ?string $description = null,
        public ?string $unit = null,
        public ?string $code = null,
        public ?string $codeType = null,
        public float $discountPercentage = 0.0,
        public bool $exempt = false,
        public array $taxes = [],
        public ?float $discountAmount = null,
    ) {
        //
    }

    /**
     * Create a new instance fluently
     *
     * @param  array<string, int>  $taxes
     */
    public static function make(
        string $name,
        float $unitPrice,
        float $quantity = 1.0,
        ?string $description = null,
        ?string $unit = null,
        ?string $code = null,
        ?string $codeType = null,
        float $discountPercentage = 0.0,
        bool $exempt = false,
        array $taxes = [],
        ?float $discountAmount = null,
    ): static {
        if (Str::length($name) > 80) {
            throw new InvalidArgumentException("The name of the item cannot exceed 80 characters. Received: $name");
        }

        if ($quantity < 0) {
            throw new InvalidArgumentException('An item cannot have 0 quantity.');
        }

        return new static(
            $name,
            $unitPrice,
            $quantity,
            $description,
            $unit,
            $code,
            $codeType,
            $discountPercentage,
            $exempt,
            $taxes,
            $discountAmount,
        );
    }

    /**
     * Create a new instance from an array.
     */
    public static function fromArray(array $array): static
    {
        return static::make(
            $array['name'],
            $array['unit_price'],
            $array['quantity'],
            $array['description'] ?? null,
            $array['unit'] ?? null,
            $array['code'] ?? null,
            $array['code_type'] ?? null,
            $array['discount_percentage'] ?? 0,
            $array['exempt'] ?? false,
            $array['taxes'] ?? [],
            $array['discount_amount'] ?? null,
        );
    }

    /**
     * Create an instance from a JSON string.
     */
    public static function fromJson(string $json): static
    {
        return static::fromArray(json_decode($json, true, 5));
    }

    /**
     * Return the validation rules for the item data.
     *
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return DteRules::ITEM;
    }

    /**
     * Validate the item data against the SII XSD rules.
     */
    public function validate(): void
    {
        validator($this->toArray(), static::rules())->validate();
    }

    /**
     * Convert the instance to an array.
     *
     * @return array{name: string, unit_price: float, quantity: float, description: string|null, unit: string|null, code: string|null, code_type: string|null, discount_percentage: float, discount_amount: float|null, exempt: bool, taxes: array<string, int>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'unit_price' => $this->unitPrice,
            'quantity' => $this->quantity,
            'description' => $this->description,
            'unit' => $this->unit,
            'code' => $this->code,
            'code_type' => $this->codeType,
            'discount_percentage' => $this->discountPercentage,
            'discount_amount' => $this->discountAmount,
            'exempt' => $this->exempt,
            'taxes' => $this->taxes,
        ];
    }

    /**
     * Convert the instance to JSON.
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{name: string, unit_price: float, quantity: float, description: string|null, unit: string|null, code: string|null, code_type: string|null, discount_percentage: float, discount_amount: float|null, exempt: bool, taxes: array<string, int>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->toArray()[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->toArray()[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('Item is read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('Item is read-only.');
    }

    /**
     * Calculate the discount amount for this item.
     */
    public function calculateDiscount(): float
    {
        // Priority (SII compliance):
        // 1. discountPercentage > 0 → auto-calculate from percentage
        // 2. discountAmount > 0 (no percentage) → use the fixed amount directly
        // 3. Both absent or non-positive → no discount
        if ($this->discountPercentage > 0) {
            return $this->unitPrice * $this->quantity * ($this->discountPercentage / 100);
        }

        return max(0, $this->discountAmount ?? 0);
    }

    /**
     * Calculate the line amount after discount.
     */
    public function calculateAmount(): int
    {
        $amount = $this->unitPrice * $this->quantity;
        $discount = $this->calculateDiscount();

        return (int) round($amount - $discount, mode: PHP_ROUND_HALF_UP);
    }
}
