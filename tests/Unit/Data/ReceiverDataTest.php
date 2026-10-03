<?php

namespace Tests\Unit\Data;

use Laragear\Dte\Data\ReceiverData;
use LogicException;
use PHPUnit\Framework\TestCase;

class ReceiverDataTest extends TestCase
{
    public function test_from_json(): void
    {
        $json = '{"rut":"76000000-0","name":"Test Corp","email":"test@example.com"}';

        $data = ReceiverData::fromJson($json);

        static::assertSame('76.000.000-0', $data->rut->format());
        static::assertSame('Test Corp', $data->name);
        static::assertSame('test@example.com', $data->email);
    }

    public function test_make_from_array_serialization_and_array_access(): void
    {
        $viaMake = ReceiverData::make('76000000-0', 'Test Corp', 'Commerce', 'test@example.com');
        $viaArray = ReceiverData::fromArray([
            'rut' => '76000000-0',
            'name' => 'Test Corp',
            'activity' => 'Commerce',
            'email' => 'test@example.com',
        ]);

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
        $data = ReceiverData::make('76000000-0', 'Test Corp');

        $this->expectException(LogicException::class);

        $data['name'] = 'Other';
    }

    public function test_offset_unset_throws(): void
    {
        $data = ReceiverData::make('76000000-0', 'Test Corp');

        $this->expectException(LogicException::class);

        unset($data['name']);
    }
}
