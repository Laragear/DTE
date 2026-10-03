<?php

namespace Tests\Unit\Pdf\Ted;

use Laragear\Dte\Pdf\Ted\Pdf417CodeWords;
use RuntimeException;
use Tests\TestCase;

class Pdf417CodeWordsTest extends TestCase
{
    public function test_get_code_returns_integer_for_valid_indices(): void
    {
        $codeWords = $this->app->make(Pdf417CodeWords::class);

        $code = $codeWords->getCode(0, 0);

        static::assertIsInt($code);
    }

    public function test_get_code_returns_consistent_value_for_same_indices(): void
    {
        $codeWords = $this->app->make(Pdf417CodeWords::class);

        static::assertSame($codeWords->getCode(0, 0), $codeWords->getCode(0, 0));
        static::assertSame($codeWords->getCode(1, 0), $codeWords->getCode(1, 0));
        static::assertSame($codeWords->getCode(2, 0), $codeWords->getCode(2, 0));
    }

    public function test_get_code_returns_different_values_for_different_tables(): void
    {
        $codeWords = $this->app->make(Pdf417CodeWords::class);

        static::assertNotSame(
            $codeWords->getCode(0, 1),
            $codeWords->getCode(1, 1),
        );
    }

    public function test_get_code_throws_on_invalid_table(): void
    {
        $codeWords = $this->app->make(Pdf417CodeWords::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid code word [99][0].');

        $codeWords->getCode(99, 0);
    }

    public function test_get_code_throws_on_invalid_word(): void
    {
        $codeWords = $this->app->make(Pdf417CodeWords::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid code word [0][99999].');

        $codeWords->getCode(0, 99999);
    }
}
