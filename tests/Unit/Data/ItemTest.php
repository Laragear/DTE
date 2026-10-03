<?php

namespace Tests\Unit\Data;

use InvalidArgumentException;
use JsonException;
use Laragear\Dte\Data\Item;
use LogicException;
use PHPUnit\Framework\TestCase;

class ItemTest extends TestCase
{
    public function test_make_throws_when_name_exceeds_80_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The name of the item cannot exceed 80 characters.');

        Item::make(str_repeat('a', 81), 1000);
    }

    public function test_make_throws_when_quantity_is_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('An item cannot have 0 quantity.');

        Item::make('Product', 1000, quantity: -1);
    }

    public function test_from_json(): void
    {
        $json = '{"name":"Widget","unit_price":1000,"quantity":2}';

        $item = Item::fromJson($json);

        static::assertSame('Widget', $item->name);
        static::assertSame(1000.0, $item->unitPrice);
        static::assertSame(2.0, $item->quantity);
    }

    public function test_from_array_serialization_and_array_access(): void
    {
        $array = [
            'name' => 'Widget',
            'unit_price' => 1000.0,
            'quantity' => 2.0,
            'description' => 'A widget',
            'unit' => 'UN',
            'code' => 'W1',
            'code_type' => 'INT1',
            'discount_percentage' => 0.0,
            'discount_amount' => null,
            'exempt' => false,
            'taxes' => [],
        ];

        $viaMake = Item::make('Widget', 1000, 2, 'A widget', 'UN', 'W1', 'INT1');
        $viaArray = Item::fromArray($array);

        static::assertSame($array, $viaMake->toArray());
        static::assertSame($array, $viaArray->toArray());
        static::assertSame(json_encode($array), $viaArray->toJson());
        static::assertSame($array, $viaArray->jsonSerialize());
        static::assertSame('Widget', $viaArray['name']);
        static::assertTrue(isset($viaArray['name']));
        static::assertFalse(isset($viaArray['missing']));
        static::assertNull($viaArray['missing']);
    }

    public function test_to_json_throws_on_unencodable_payload(): void
    {
        $item = Item::make('Widget', 1000, description: "\xB1\x31");

        $this->expectException(JsonException::class);

        $item->toJson();
    }

    public function test_offset_set_throws(): void
    {
        $data = Item::make('Widget', 1000);

        $this->expectException(LogicException::class);

        $data['name'] = 'Other';
    }

    public function test_offset_unset_throws(): void
    {
        $data = Item::make('Widget', 1000);

        $this->expectException(LogicException::class);

        unset($data['name']);
    }
}
