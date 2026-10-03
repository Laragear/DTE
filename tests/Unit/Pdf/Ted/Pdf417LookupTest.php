<?php

namespace Tests\Unit\Pdf\Ted;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use Laragear\Dte\Pdf\Ted\Pdf417Lookup;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class Pdf417LookupTest extends TestCase
{
    /*
     |--------------------------------------------------------------------------
     | Happy paths
     |--------------------------------------------------------------------------
     */

    public function test_get_code_returns_integer_for_valid_indices(): void
    {
        $lookup = $this->app->make(Pdf417Lookup::class);

        $code = $lookup->getCode(0, 0);

        static::assertIsInt($code);
    }

    public function test_get_code_returns_consistent_value_for_same_indices(): void
    {
        $lookup = $this->app->make(Pdf417Lookup::class);

        static::assertSame($lookup->getCode(0, 0), $lookup->getCode(0, 0));
        static::assertSame($lookup->getCode(1, 0), $lookup->getCode(1, 0));
        static::assertSame($lookup->getCode(2, 0), $lookup->getCode(2, 0));
    }

    public function test_get_code_returns_different_values_for_different_tables(): void
    {
        $lookup = $this->app->make(Pdf417Lookup::class);

        static::assertNotSame(
            $lookup->getCode(0, 1),
            $lookup->getCode(1, 1),
        );
    }

    public function test_calculate_error_correction_words_returns_array_of_integers(): void
    {
        $lookup = $this->app->make(Pdf417Lookup::class);

        $result = $lookup->calculateErrorCorrectionWords([1, 2, 3, 4, 5], 5);

        static::assertIsArray($result);
        static::assertNotEmpty($result);
        static::assertIsInt($result[0]);
    }

    public function test_calculate_error_correction_words_is_consistent(): void
    {
        $lookup = $this->app->make(Pdf417Lookup::class);

        $first = $lookup->calculateErrorCorrectionWords([1, 2, 3, 4, 5], 5);
        $second = $lookup->calculateErrorCorrectionWords([1, 2, 3, 4, 5], 5);

        static::assertSame($first, $second);
    }

    public function test_different_security_levels_produce_different_results(): void
    {
        $lookup = $this->app->make(Pdf417Lookup::class);

        $level0 = $lookup->calculateErrorCorrectionWords([1, 2, 3, 4, 5], 0);
        $level5 = $lookup->calculateErrorCorrectionWords([1, 2, 3, 4, 5], 5);

        static::assertNotSame($level0, $level5);
    }

    public function test_all_security_levels_produce_non_empty_results(): void
    {
        $lookup = $this->app->make(Pdf417Lookup::class);

        for ($level = 0; $level <= 8; $level++) {
            $result = $lookup->calculateErrorCorrectionWords([1, 2, 3, 4, 5], $level);

            static::assertNotEmpty($result, "Security level {$level} should produce correction words.");
            static::assertIsInt($result[0], "Security level {$level} should produce integers.");
        }
    }

    /*
     |--------------------------------------------------------------------------
     | Sad paths
     |--------------------------------------------------------------------------
     */

    public function test_get_code_throws_on_invalid_table(): void
    {
        $lookup = $this->app->make(Pdf417Lookup::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid code word [99][0].');

        $lookup->getCode(99, 0);
    }

    public function test_get_code_throws_on_invalid_word(): void
    {
        $lookup = $this->app->make(Pdf417Lookup::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid code word [0][99999].');

        $lookup->getCode(0, 99999);
    }

    public function test_calculate_error_correction_words_throws_on_invalid_level(): void
    {
        $lookup = $this->app->make(Pdf417Lookup::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Invalid correction level given: "99". Valid values are 0-8.');

        $lookup->calculateErrorCorrectionWords([1, 2, 3], 99);
    }

    /*
     |--------------------------------------------------------------------------
     | Angry paths
     |--------------------------------------------------------------------------
     */

    public function test_throws_runtime_exception_when_codewords_file_not_found(): void
    {
        $this->mock(Filesystem::class, function (MockInterface $mock): void {
            $mock->expects('get')->andThrow(FileNotFoundException::class);
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Unable to load PDF417 code words data file.');

        $this->app->make(Pdf417Lookup::class);
    }

    public function test_throws_runtime_exception_when_factors_file_not_found(): void
    {
        $this->mock(Filesystem::class, function (MockInterface $mock): void {
            $mock->shouldReceive('get')->once()->andReturn(serialize([]));
            $mock->shouldReceive('get')->once()->andThrow(FileNotFoundException::class);
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Unable to load PDF417 error correction data file.');

        $this->app->make(Pdf417Lookup::class);
    }
}
