<?php

namespace Tests\Unit\Data;

use DateTimeImmutable;
use Laragear\Dte\Data\PaymentTermData;
use LogicException;
use Tests\TestCase;

class PaymentTermDataTest extends TestCase
{
    public function test_make_creates_payment_term_data(): void
    {
        $date = new DateTimeImmutable('2026-08-13');
        $term = PaymentTermData::make('Credit', $date);

        static::assertSame('Credit', $term->condition);
        static::assertSame($date, $term->expirationDate);
    }

    public function test_from_array_from_json_serialization_and_array_access(): void
    {
        $array = ['condition' => 'Credit', 'expiration_date' => '2026-08-13'];

        $viaArray = PaymentTermData::fromArray($array);
        $viaJson = PaymentTermData::fromJson(json_encode($array));

        static::assertSame($array, $viaArray->toArray());
        static::assertSame($array, $viaJson->toArray());
        static::assertSame(json_encode($array), $viaArray->toJson());
        static::assertSame($array, $viaArray->jsonSerialize());
        static::assertSame('Credit', $viaArray['condition']);
        static::assertTrue(isset($viaArray['condition']));
        static::assertFalse(isset($viaArray['missing']));
        static::assertNull($viaArray['missing']);
    }

    public function test_offset_set_throws(): void
    {
        $term = PaymentTermData::make('Credit', new DateTimeImmutable('2026-08-13'));

        $this->expectException(LogicException::class);

        $term['condition'] = 'Cash';
    }

    public function test_offset_unset_throws(): void
    {
        $term = PaymentTermData::make('Credit', new DateTimeImmutable('2026-08-13'));

        $this->expectException(LogicException::class);

        unset($term['condition']);
    }

    public function test_make_accepts_string_date(): void
    {
        $term = PaymentTermData::make('Credit', '2026-08-13');

        static::assertSame('2026-08-13', $term->expirationDate->format('Y-m-d'));
    }
}
