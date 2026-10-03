<?php

namespace Tests\Unit\Builders\Concerns;

use InvalidArgumentException;
use Laragear\Dte\Builders\Concerns\HasGlobalModifiers;
use Laragear\Dte\Enums\ModifierTarget;
use Tests\TestCase;

class HasGlobalModifiersTest extends TestCase
{
    protected function getTrait()
    {
        return new class {
            use HasGlobalModifiers;
        };
    }

    public function test_adds_global_discount_and_surcharge(): void
    {
        $class = $this->getTrait();

        $class->globalDiscount(1500.5, false, ModifierTarget::Exempt, 'Discount desc');
        $class->globalSurcharge(10, true, ModifierTarget::Default, 'Surcharge desc');

        $modifiers = $class->globalModifiers();

        static::assertCount(2, $modifiers);
        static::assertSame('D', $modifiers[0]['type']);
        static::assertSame('$', $modifiers[0]['value_type']);
        static::assertSame(1500.5, $modifiers[0]['value']);
        static::assertSame(1, $modifiers[0]['target']);
        static::assertSame('Discount desc', $modifiers[0]['description']);

        static::assertSame('R', $modifiers[1]['type']);
        static::assertSame('%', $modifiers[1]['value_type']);
        static::assertSame(10, $modifiers[1]['value']);
        static::assertSame(0, $modifiers[1]['target']);
        static::assertSame('Surcharge desc', $modifiers[1]['description']);
    }

    public function test_throws_if_value_negative(): void
    {
        $class = $this->getTrait();
        $this->expectException(InvalidArgumentException::class);
        $class->globalDiscount(-5);
    }

    public function test_throws_if_percent_greater_than_100(): void
    {
        $class = $this->getTrait();
        $this->expectException(InvalidArgumentException::class);
        $class->globalSurcharge(101, true);
    }
}
