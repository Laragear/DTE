<?php

namespace Laragear\Dte\Data;

use ArrayAccess;
use DateTimeImmutable;
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
readonly class PaymentTermData implements Arrayable, ArrayAccess, Jsonable, JsonSerializable
{
    /**
     * Create a new Payment Term Data instance.
     */
    public function __construct(
        public string $condition,
        public DateTimeImmutable $expirationDate,
    ) {
        //
    }

    /**
     * Create a new instance fluently
     */
    public static function make(
        string $condition,
        DateTimeImmutable|string $expirationDate,
    ): static {
        return new static($condition, static::parseDate($expirationDate));
    }

    /**
     * Create a new instance from an array.
     */
    public static function fromArray(array $array): static
    {
        return static::make(
            $array['condition'],
            static::parseDate($array['expiration_date']),
        );
    }

    /**
     * Create an instance from a JSON string.
     */
    public static function fromJson(string $json): static
    {
        return static::fromArray(json_decode($json, true, 2));
    }

    /**
     * Return the validation rules for the payment term data.
     *
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return DteRules::PAYMENT_TERM;
    }

    /**
     * Validate the payment term data against the SII XSD rules.
     */
    public function validate(): void
    {
        validator($this->toArray(), static::rules())->validate();
    }

    /**
     * Convert the instance to an array.
     *
     * @return array{condition: string, expiration_date: string}
     */
    public function toArray(): array
    {
        return [
            'condition' => $this->condition,
            'expiration_date' => $this->expirationDate->format('Y-m-d'),
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
     * @return array{condition: string, expiration_date: string}
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
        throw new LogicException('PaymentTermData is read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('PaymentTermData is read-only.');
    }

    /**
     * Parse a date value.
     */
    protected static function parseDate(DateTimeImmutable|string $value): DateTimeImmutable
    {
        return $value instanceof DateTimeImmutable ? $value : new DateTimeImmutable($value);
    }
}
