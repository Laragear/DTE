<?php

namespace Tests\Unit\Enums;

use Generator;
use InvalidArgumentException;
use Laragear\Dte\Enums\SiiTaxes;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SiiTaxesTest extends TestCase
{
    public function test_iva_rate_returns_config_value(): void
    {
        $this->config('dte.taxes.iva_rate', 21);

        static::assertSame(21, SiiTaxes::ivaRate());
    }

    public function test_iva_decimal_returns_float(): void
    {
        $this->config('dte.taxes.iva_rate', 19);

        static::assertSame(0.19, SiiTaxes::ivaDecimal());
    }

    public static function providesInvalidIvaRate(): Generator
    {
        yield 'below zero' => [-1];
        yield 'zero' => [0];
        yield 'below one hundred' => [101];
    }

    #[DataProvider('providesInvalidIvaRate')]
    public function test_throws_when_iva_rate_is_out_of_range(int|float $rate): void
    {
        $this->config('dte.taxes.iva_rate', $rate);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs("The dte.taxes.iva_rate configuration must be between 1 and 100. Got: {$rate}");

        SiiTaxes::ivaRate();
    }
}
