<?php

namespace Laragear\Dte\Data;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use Laragear\Dte\Validation\DteRules;
use Laragear\Rut\Rut;
use LogicException;

use function json_decode;
use function json_encode;
use function validator;

/**
 * @implements ArrayAccess<string, mixed>
 */
readonly class ReceiverData implements Arrayable, ArrayAccess, Jsonable, JsonSerializable
{
    /**
     * Create a new Receiver Data instance.
     */
    public function __construct(
        public Rut $rut,
        public string $name,
        public ?string $activity = null,
        public ?string $email = null,
        public ?string $address = null,
        public ?string $commune = null,
        public ?string $city = null,
    ) {
        //
    }

    /**
     * Create a new instance fluently
     */
    public static function make(
        Rut|string $rut,
        string $name,
        ?string $activity = null,
        ?string $email = null,
        ?string $address = null,
        ?string $commune = null,
        ?string $city = null,
    ): static {
        return new static(Rut::parse($rut), $name, $activity, $email, $address, $commune, $city);
    }

    /**
     * Create a new instance from an array.
     */
    public static function fromArray(array $array): static
    {
        return static::make(
            $array['rut'],
            $array['name'],
            $array['activity'] ?? null,
            $array['email'] ?? null,
            $array['address'] ?? null,
            $array['commune'] ?? null,
            $array['city'] ?? null,
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
     * Return the validation rules for the receiver data.
     *
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return DteRules::RECEIVER;
    }

    /**
     * Validate the receiver data against the SII XSD rules.
     */
    public function validate(): void
    {
        validator($this->toArray(), static::rules())->validate();
    }

    /**
     * Convert the instance to an array.
     *
     * @return array{rut: string, name: string, activity: string|null, email: string|null, address: string|null, commune: string|null, city: string|null}
     */
    public function toArray(): array
    {
        return [
            'rut' => $this->rut->formatRaw(),
            'name' => $this->name,
            'activity' => $this->activity,
            'email' => $this->email,
            'address' => $this->address,
            'commune' => $this->commune,
            'city' => $this->city,
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
     * @return array{rut: string, name: string, activity: string|null, email: string|null, address: string|null, commune: string|null, city: string|null}
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
        throw new LogicException('ReceiverData is read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('ReceiverData is read-only.');
    }
}
