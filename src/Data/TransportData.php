<?php

namespace Laragear\Dte\Data;

use ArrayAccess;
use DateTimeImmutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use Laragear\Dte\Validation\DteRules;
use Laragear\Rut\Rut;
use LogicException;
use Throwable;

use function is_string;
use function json_decode;
use function json_encode;
use function validator;

/**
 * @implements ArrayAccess<string, mixed>
 */
readonly class TransportData implements Arrayable, ArrayAccess, Jsonable, JsonSerializable
{
    /**
     * Create a new Transport Data instance.
     */
    public function __construct(
        public ?string $vehiclePlate = null,
        public ?string $trailerPlate = null,
        public ?Rut $carrierRut = null,
        public ?Rut $driverRut = null,
        public ?string $driverName = null,
        public ?string $destinationAddress = null,
        public ?string $destinationCommune = null,
        public ?string $destinationCity = null,
        public ?DateTimeImmutable $departureAt = null,
        public ?DateTimeImmutable $arrivalAt = null,
    ) {
        //
    }

    /**
     * Create a new instance fluently
     */
    public static function make(
        ?string $vehiclePlate = null,
        ?string $trailerPlate = null,
        Rut|string|null $carrierRut = null,
        Rut|string|null $driverRut = null,
        ?string $driverName = null,
        ?string $destinationAddress = null,
        ?string $destinationCommune = null,
        ?string $destinationCity = null,
        DateTimeImmutable|string|null $departureAt = null,
        DateTimeImmutable|string|null $arrivalAt = null,
    ): static {
        return new static(
            $vehiclePlate,
            $trailerPlate,
            static::parseRut($carrierRut),
            static::parseRut($driverRut),
            $driverName,
            $destinationAddress,
            $destinationCommune,
            $destinationCity,
            static::parseDate($departureAt),
            static::parseDate($arrivalAt),
        );
    }

    /**
     * Create a new instance from an array.
     */
    public static function fromArray(array $array): static
    {
        return static::make(
            $array['vehicle_plate'] ?? null,
            $array['trailer_plate'] ?? null,
            $array['carrier_rut'] ?? null,
            $array['driver_rut'] ?? null,
            $array['driver_name'] ?? null,
            $array['destination_address'] ?? null,
            $array['destination_commune'] ?? null,
            $array['destination_city'] ?? null,
            $array['departure_at'] ?? null,
            $array['arrival_at'] ?? null,
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
     * Return the validation rules for the transport data.
     *
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return DteRules::TRANSPORT;
    }

    /**
     * Validate the transport data against the SII XSD rules.
     */
    public function validate(): void
    {
        validator($this->toArray(), static::rules())->validate();
    }

    /**
     * Convert the instance to an array.
     *
     * @return array{vehicle_plate: string|null, trailer_plate: string|null, carrier_rut: string|null, driver_rut: string|null, driver_name: string|null, destination_address: string|null, destination_commune: string|null, destination_city: string|null, departure_at: string|null, arrival_at: string|null}
     */
    public function toArray(): array
    {
        return [
            'vehicle_plate' => $this->vehiclePlate,
            'trailer_plate' => $this->trailerPlate,
            'carrier_rut' => $this->carrierRut?->formatRaw(),
            'driver_rut' => $this->driverRut?->formatRaw(),
            'driver_name' => $this->driverName,
            'destination_address' => $this->destinationAddress,
            'destination_commune' => $this->destinationCommune,
            'destination_city' => $this->destinationCity,
            'departure_at' => $this->departureAt?->format('Y-m-d H:i:s'),
            'arrival_at' => $this->arrivalAt?->format('Y-m-d H:i:s'),
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
     * @return array{vehicle_plate: string|null, trailer_plate: string|null, carrier_rut: string|null, driver_rut: string|null, driver_name: string|null, destination_address: string|null, destination_commune: string|null, destination_city: string|null, departure_at: string|null, arrival_at: string|null}
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
        throw new LogicException('TransportData is read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('TransportData is read-only.');
    }

    /**
     * Parse a RUT value or null.
     */
    protected static function parseRut(Rut|string|null $value): ?Rut
    {
        if ($value === null || $value instanceof Rut) {
            return $value;
        }

        try {
            return Rut::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Parse a datetime value or null.
     */
    protected static function parseDate(DateTimeImmutable|string|null $value): ?DateTimeImmutable
    {
        if ($value === null || $value instanceof DateTimeImmutable) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }
}
