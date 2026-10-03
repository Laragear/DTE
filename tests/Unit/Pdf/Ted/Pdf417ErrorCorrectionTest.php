<?php

namespace Tests\Unit\Pdf\Ted;

use InvalidArgumentException;
use Laragear\Dte\Pdf\Ted\Pdf417ErrorCorrection;
use Tests\TestCase;

class Pdf417ErrorCorrectionTest extends TestCase
{
    public function test_calculate_error_correction_words_returns_array_of_integers(): void
    {
        $correction = $this->app->make(Pdf417ErrorCorrection::class);

        $result = $correction->calculateErrorCorrectionWords([1, 2, 3, 4, 5], 5);

        static::assertIsArray($result);
        static::assertNotEmpty($result);
        static::assertIsInt($result[0]);
    }

    public function test_calculate_error_correction_words_is_consistent(): void
    {
        $correction = $this->app->make(Pdf417ErrorCorrection::class);

        $first = $correction->calculateErrorCorrectionWords([1, 2, 3, 4, 5], 5);
        $second = $correction->calculateErrorCorrectionWords([1, 2, 3, 4, 5], 5);

        static::assertSame($first, $second);
    }

    public function test_different_security_levels_produce_different_results(): void
    {
        $correction = $this->app->make(Pdf417ErrorCorrection::class);

        $level0 = $correction->calculateErrorCorrectionWords([1, 2, 3, 4, 5], 0);
        $level5 = $correction->calculateErrorCorrectionWords([1, 2, 3, 4, 5], 5);

        static::assertNotSame($level0, $level5);
    }

    public function test_all_security_levels_produce_non_empty_results(): void
    {
        $correction = $this->app->make(Pdf417ErrorCorrection::class);

        for ($level = 0; $level <= 8; $level++) {
            $result = $correction->calculateErrorCorrectionWords([1, 2, 3, 4, 5], $level);

            static::assertNotEmpty($result, "Security level {$level} should produce correction words.");
            static::assertIsInt($result[0], "Security level {$level} should produce integers.");
        }
    }

    public function test_calculate_error_correction_words_throws_on_invalid_level(): void
    {
        $correction = $this->app->make(Pdf417ErrorCorrection::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Invalid correction level given: "99". Valid values are 0-8.');

        $correction->calculateErrorCorrectionWords([1, 2, 3], 99);
    }
}
