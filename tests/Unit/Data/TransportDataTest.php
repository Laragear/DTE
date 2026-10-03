<?php

namespace Tests\Unit\Data;

use DateTimeImmutable;
use Laragear\Dte\Data\TransportData;
use Laragear\Rut\Rut;
use LogicException;
use PHPUnit\Framework\TestCase;

class TransportDataTest extends TestCase
{
    public function test_make_from_array_from_json(): void
    {
        $array = [
            'vehicle_plate' => 'ABCD12',
            'trailer_plate' => null,
            'carrier_rut' => '76192083-9',
            'driver_rut' => null,
            'driver_name' => 'Juan Perez',
            'destination_address' => 'Av Siempre Viva 123',
            'destination_commune' => 'Santiago',
            'destination_city' => 'Santiago',
            'departure_at' => '2026-08-13 10:00:00',
            'arrival_at' => null,
        ];

        $viaArray = TransportData::fromArray($array);
        $viaJson = TransportData::fromJson(json_encode($array));

        static::assertSame('ABCD12', $viaArray->vehiclePlate);
        static::assertInstanceOf(Rut::class, $viaArray->carrierRut);
        static::assertInstanceOf(DateTimeImmutable::class, $viaArray->departureAt);
        static::assertSame('761920839', $viaArray->toArray()['carrier_rut']);
        static::assertSame(json_encode($viaArray->toArray()), $viaArray->toJson());
        static::assertSame($viaArray->toArray(), $viaJson->toArray());
    }

    public function test_array_access_reads_and_writes_throw(): void
    {
        $data = TransportData::make(vehiclePlate: 'ABCD12');

        static::assertSame('ABCD12', $data['vehicle_plate']);
        static::assertNull($data['driver_name']);
        static::assertTrue(isset($data['vehicle_plate']));

        $this->expectException(LogicException::class);

        $data['vehicle_plate'] = 'XXXX00';
    }

    public function test_empty_input_gives_nulls(): void
    {
        $data = TransportData::fromArray([]);

        static::assertSame([
            'vehicle_plate' => null,
            'trailer_plate' => null,
            'carrier_rut' => null,
            'driver_rut' => null,
            'driver_name' => null,
            'destination_address' => null,
            'destination_commune' => null,
            'destination_city' => null,
            'departure_at' => null,
            'arrival_at' => null,
        ], $data->toArray());
    }

    public function test_json_serialize_returns_array(): void
    {
        $data = TransportData::make(vehiclePlate: 'ABCD12');

        static::assertSame($data->toArray(), $data->jsonSerialize());
    }

    public function test_offset_unset_throws(): void
    {
        $data = TransportData::make(vehiclePlate: 'ABCD12');

        $this->expectException(LogicException::class);

        unset($data['vehicle_plate']);
    }

    public function test_invalid_rut_and_dates_resolve_to_null(): void
    {
        $data = TransportData::fromArray([
            'carrier_rut' => 'not-a-rut',
            'driver_rut' => 'also-invalid',
            'departure_at' => 'not-a-date',
            'arrival_at' => '',
        ]);

        static::assertNull($data->carrierRut);
        static::assertNull($data->driverRut);
        static::assertNull($data->departureAt);
        static::assertNull($data->arrivalAt);
    }
}
