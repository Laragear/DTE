<?php

namespace Tests\Unit\Data;

use Laragear\Dte\Data\ReferenceData;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\ReferenceType;
use LogicException;
use PHPUnit\Framework\TestCase;

class ReferenceDataTest extends TestCase
{
    public function test_make_with_null_date(): void
    {
        $data = ReferenceData::make(DteType::Invoice, '1', date: null);

        static::assertNull($data->date);
    }

    public function test_make_with_string_type_normalizes_to_reference_type(): void
    {
        $data = ReferenceData::make('SET', '1');

        static::assertInstanceOf(ReferenceType::class, $data->documentType);
        static::assertSame(ReferenceType::TestSet, $data->documentType);
    }

    public function test_make_with_string_date(): void
    {
        $data = ReferenceData::make(DteType::Invoice, '1', date: '2024-01-15');

        static::assertNotNull($data->date);
        static::assertSame('2024-01-15', $data->date->format('Y-m-d'));
    }

    public function test_from_json(): void
    {
        $json = '{"document_type":"33","folio":"1","date":"2024-01-15","reason":"test"}';

        $data = ReferenceData::fromJson($json);

        static::assertSame(DteType::Invoice, $data->documentType);
        static::assertSame('1', $data->folio);
        static::assertSame('test', $data->reason);
    }

    public function test_from_array_serialization_and_array_access(): void
    {
        $array = [
            'document_type' => 33,
            'folio' => '1',
            'date' => '2024-01-15',
            'reason' => 'test',
            'reference_code' => 1,
        ];

        $viaArray = ReferenceData::fromArray($array);

        static::assertSame($array, $viaArray->toArray());
        static::assertSame(json_encode($array), $viaArray->toJson());
        static::assertSame($array, $viaArray->jsonSerialize());
        static::assertSame(33, $viaArray['document_type']);
        static::assertTrue(isset($viaArray['folio']));
        static::assertFalse(isset($viaArray['missing']));
        static::assertNull($viaArray['missing']);
    }

    public function test_offset_set_throws(): void
    {
        $data = ReferenceData::make(DteType::Invoice, '1');

        $this->expectException(LogicException::class);

        $data['folio'] = '2';
    }

    public function test_offset_unset_throws(): void
    {
        $data = ReferenceData::make(DteType::Invoice, '1');

        $this->expectException(LogicException::class);

        unset($data['folio']);
    }

    public function test_make_with_int_type_and_invalid_date(): void
    {
        $data = ReferenceData::make(33, '1', date: 'not-a-date');

        static::assertSame(DteType::Invoice, $data->documentType);
        static::assertNull($data->date);
    }

    public function test_make_with_empty_string_date_returns_null(): void
    {
        $data = ReferenceData::make(DteType::Invoice, '1', date: '');

        static::assertNull($data->date);
    }
}
