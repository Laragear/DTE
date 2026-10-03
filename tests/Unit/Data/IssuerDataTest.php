<?php

namespace Tests\Unit\Data;

use Laragear\Dte\Data\IssuerData;
use LogicException;
use PHPUnit\Framework\TestCase;

class IssuerDataTest extends TestCase
{
    public function test_from_json(): void
    {
        $json = '{"rut":"76000000-0","name":"Test Corp","activity":"Commerce",'
            .'"activity_code":"6201","address":"Main St","commune":"Santiago",'
            .'"resolution_date":"2024-01-01","resolution_number":1}';

        $data = IssuerData::fromJson($json);

        static::assertSame('76.000.000-0', $data->rut->format());
        static::assertSame('Test Corp', $data->name);
        static::assertSame('Commerce', $data->activity);
        static::assertSame('6201', $data->activityCode);
        static::assertSame(1, $data->resolutionNumber);
    }

    public function test_make_from_array_serialization_and_array_access(): void
    {
        $array = [
            'rut' => '76000000-0',
            'name' => 'Test Corp',
            'activity' => 'Commerce',
            'activity_code' => '6201',
            'address' => 'Main St',
            'commune' => 'Santiago',
            'resolution_date' => '2024-01-01',
            'resolution_number' => 1,
            'city' => null,
            'telephone' => null,
            'email' => null,
            'branch' => null,
        ];

        $viaMake = IssuerData::make(
            '76000000-0', 'Test Corp', 'Commerce', '6201', 'Main St', 'Santiago', '2024-01-01', 1,
        );
        $viaArray = IssuerData::fromArray($array);

        static::assertSame('760000000', $viaMake->toArray()['rut']);
        static::assertSame($viaMake->toArray(), $viaArray->toArray());
        static::assertSame(json_encode($viaMake->toArray()), $viaMake->toJson());
        static::assertSame($viaMake->toArray(), $viaMake->jsonSerialize());
        static::assertSame('Test Corp', $viaArray['name']);
        static::assertTrue(isset($viaArray['rut']));
        static::assertFalse(isset($viaArray['missing']));
        static::assertNull($viaArray['missing']);
    }

    public function test_offset_set_throws(): void
    {
        $data = IssuerData::fromArray([
            'rut' => '76000000-0',
            'name' => 'Test Corp',
            'activity' => 'Commerce',
            'activity_code' => '6201',
            'address' => 'Main St',
            'commune' => 'Santiago',
            'resolution_date' => '2024-01-01',
            'resolution_number' => 1,
        ]);

        $this->expectException(LogicException::class);

        $data['name'] = 'Other';
    }

    public function test_offset_unset_throws(): void
    {
        $data = IssuerData::fromArray([
            'rut' => '76000000-0',
            'name' => 'Test Corp',
            'activity' => 'Commerce',
            'activity_code' => '6201',
            'address' => 'Main St',
            'commune' => 'Santiago',
            'resolution_date' => '2024-01-01',
            'resolution_number' => 1,
        ]);

        $this->expectException(LogicException::class);

        unset($data['name']);
    }
}
