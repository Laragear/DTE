<?php

namespace Tests\Unit\Data;

use Laragear\Dte\Data\GlobalModifierData;
use LogicException;
use PHPUnit\Framework\TestCase;

class GlobalModifierDataTest extends TestCase
{
    public function test_make_from_array_from_json(): void
    {
        $array = ['type' => 'D', 'value_type' => '%', 'value' => 10, 'target' => 0, 'description' => null];

        $viaMake = GlobalModifierData::make('D', '%', 10);
        $viaArray = GlobalModifierData::fromArray($array);
        $viaJson = GlobalModifierData::fromJson(json_encode($array));

        static::assertSame($array, $viaMake->toArray());
        static::assertSame($array, $viaArray->toArray());
        static::assertSame($array, $viaJson->toArray());
        static::assertSame(json_encode($array), $viaArray->toJson());
        static::assertSame($array, $viaArray->jsonSerialize());
    }

    public function test_array_access_reads_and_writes_throw(): void
    {
        $data = GlobalModifierData::make('R', '$', 500, 1, 'Surcharge');

        static::assertSame('R', $data['type']);
        static::assertTrue(isset($data['value']));
        static::assertFalse(isset($data['missing']));

        $this->expectException(LogicException::class);

        $data['type'] = 'D';
    }

    public function test_missing_target_defaults_to_zero(): void
    {
        $data = GlobalModifierData::fromArray(['type' => 'D', 'value_type' => '$', 'value' => 100]);

        static::assertSame(0, $data->target);
        static::assertNull($data->description);
    }

    public function test_offset_unset_throws(): void
    {
        $data = GlobalModifierData::make('D', '%', 10);

        $this->expectException(LogicException::class);

        unset($data['type']);
    }
}
