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
readonly class DocumentTotalsData implements Arrayable, ArrayAccess, Jsonable, JsonSerializable
{
    /**
     * Create a new Document Totals Data instance.
     */
    public function __construct(
        public int $net = 0,
        public int $exempt = 0,
        public int $tax = 0,
        public int $total = 0,
        public int $nonBillable = 0,
    ) {
        //
    }

    /**
     * Create a new instance fluently
     */
    public static function make(
        int $net = 0,
        int $exempt = 0,
        int $tax = 0,
        int $total = 0,
        int $nonBillable = 0,
    ): static {
        return new static($net, $exempt, $tax, $total, $nonBillable);
    }

    /**
     * Create a new instance from an array.
     */
    public static function fromArray(array $array): static
    {
        return static::make(
            (int) ($array['net'] ?? 0),
            (int) ($array['exempt'] ?? 0),
            (int) ($array['tax'] ?? 0),
            (int) ($array['total'] ?? 0),
            (int) ($array['non_billable'] ?? 0),
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
     * Return the validation rules for the document totals data.
     *
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return DteRules::TOTALS;
    }

    /**
     * Validate the document totals data against the SII XSD rules.
     */
    public function validate(): void
    {
        validator($this->toArray(), static::rules())->validate();
    }

    /**
     * Convert the instance to an array.
     *
     * @return array{net: int, exempt: int, tax: int, total: int, non_billable: int}
     */
    public function toArray(): array
    {
        return [
            'net' => $this->net,
            'exempt' => $this->exempt,
            'tax' => $this->tax,
            'total' => $this->total,
            'non_billable' => $this->nonBillable,
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
     * @return array{net: int, exempt: int, tax: int, total: int, non_billable: int}
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
        throw new LogicException('DocumentTotalsData is read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('DocumentTotalsData is read-only.');
    }
}
