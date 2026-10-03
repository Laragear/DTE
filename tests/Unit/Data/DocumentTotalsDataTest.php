<?php

namespace Tests\Unit\Data;

use Laragear\Dte\Data\DocumentTotalsData;
use LogicException;
use PHPUnit\Framework\TestCase;

class DocumentTotalsDataTest extends TestCase
{
    public function test_make_from_array_from_json(): void
    {
        $array = ['net' => 10000, 'exempt' => 0, 'tax' => 1900, 'total' => 11900, 'non_billable' => 0];

        $viaMake = DocumentTotalsData::make(10000, 0, 1900, 11900);
        $viaArray = DocumentTotalsData::fromArray($array);
        $viaJson = DocumentTotalsData::fromJson(json_encode($array));

        static::assertSame($array, $viaMake->toArray());
        static::assertSame($array, $viaArray->toArray());
        static::assertSame($array, $viaJson->toArray());
        static::assertSame(json_encode($array), $viaArray->toJson());
        static::assertSame($array, $viaArray->jsonSerialize());
    }

    public function test_array_access_reads_and_writes_throw(): void
    {
        $data = DocumentTotalsData::fromArray(['net' => 1, 'total' => 2]);

        static::assertSame(1, $data['net']);
        static::assertSame(0, $data['exempt']);
        static::assertTrue(isset($data['total']));

        $this->expectException(LogicException::class);

        unset($data['net']);
    }

    public function test_missing_keys_default_to_zero(): void
    {
        $data = DocumentTotalsData::fromArray([]);

        static::assertSame(['net' => 0, 'exempt' => 0, 'tax' => 0, 'total' => 0, 'non_billable' => 0],
            $data->toArray());
    }

    public function test_offset_set_throws(): void
    {
        $data = DocumentTotalsData::fromArray(['net' => 1]);

        $this->expectException(LogicException::class);

        $data['net'] = 2;
    }
}
