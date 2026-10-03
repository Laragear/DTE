<?php

namespace Laragear\Dte\Data;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use Laragear\Dte\Validation\DteRules;
use LogicException;

use function json_decode;
use function json_encode;
use function validator;

/**
 * @implements ArrayAccess<string, mixed>
 */
readonly class GlobalModifierData implements Arrayable, ArrayAccess, Jsonable, JsonSerializable
{
    /**
     * Create a new Global Modifier Data instance.
     */
    public function __construct(
        public string $type,
        public string $valueType,
        public float|int $value,
        public int $target = 0,
        public ?string $description = null,
    ) {
        // 'D' for discount, 'R' for surcharge
        // '%' for percent, '$' for amount
        //
    }

    /**
     * Create a new instance fluently
     */
    public static function make(
        string $type,
        string $valueType,
        float|int $value,
        int $target = 0,
        ?string $description = null,
    ): static {
        return new static($type, $valueType, $value, $target, $description);
    }

    /**
     * Create a new instance from an array.
     */
    public static function fromArray(array $array): static
    {
        return static::make(
            $array['type'],
            $array['value_type'],
            $array['value'],
            isset($array['target']) ? (int) $array['target'] : 0,
            $array['description'] ?? null,
        );
    }

    /**
     * Create an instance from a JSON string.
     */
    public static function fromJson(string $json): static
    {
        return static::fromArray(json_decode($json, true, 3));
    }

    /**
     * Return the validation rules for the global modifier data.
     *
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return DteRules::GLOBAL_MODIFIER;
    }

    /**
     * Validate the global modifier data against the SII XSD rules.
     */
    public function validate(): void
    {
        validator($this->toArray(), static::rules())->validate();
    }

    /**
     * Convert the instance to an array.
     *
     * @return array{type: string, value_type: string, value: float|int, target: int, description: string|null}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'value_type' => $this->valueType,
            'value' => $this->value,
            'target' => $this->target,
            'description' => $this->description,
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
     * @return array{type: string, value_type: string, value: float|int, target: int, description: string|null}
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
        throw new LogicException('GlobalModifierData is read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('GlobalModifierData is read-only.');
    }
}
