<?php

namespace Tests\Unit\Pdf\Ted;

use Dom\XMLDocument;
use Dom\XPath;
use InvalidArgumentException;
use Laragear\Dte\Pdf\Ted\Pdf417Barcode;
use Laragear\Dte\Pdf\Ted\Pdf417Encoder;
use RuntimeException;
use Tests\TestCase;

class Pdf417EncoderTest extends TestCase
{
    protected function createEncoder(): Pdf417Encoder
    {
        return $this->app->make(Pdf417Encoder::class);
    }

    /*
     |--------------------------------------------------------------------------
     | Happy paths
     |--------------------------------------------------------------------------
     */

    public function test_encode_produces_valid_barcode(): void
    {
        $encoder = $this->createEncoder();

        $barcode = $encoder->encode('hello world');

        static::assertNotEmpty($barcode->codeWords);
        static::assertGreaterThan(0, $barcode->columns);
        static::assertGreaterThan(0, $barcode->rows);
        static::assertNotEmpty($barcode->codes);
        static::assertSame($encoder->getColumns(), $barcode->columns);
        static::assertSame($encoder->getSecurityLevel(), $barcode->securityLevel);
    }

    public function test_encode_with_real_ted_data(): void
    {
        $doc = XMLDocument::createFromFile(static::STUBS.'/static/dte-33-1-signed.xml');

        $ted = new XPath($doc)->query('//DTE/Documento/TED')->item(0)->C14N();

        $barcode = $this->createEncoder()->encode($ted);

        static::assertGreaterThan(10, count($barcode->codeWords));
        static::assertGreaterThan(1, $barcode->rows);
    }

    public function test_encode_produces_barcode_with_codes(): void
    {
        $encoder = $this->createEncoder();

        $barcode = $encoder->encode('test');

        static::assertCount($barcode->rows, $barcode->codes);
    }

    public function test_get_columns_returns_default(): void
    {
        $encoder = $this->createEncoder();

        static::assertSame(Pdf417Encoder::DEFAULT_COLUMNS, $encoder->getColumns());
    }

    public function test_set_columns_sets_value(): void
    {
        $encoder = $this->createEncoder();

        $encoder->setColumns(15);

        static::assertSame(15, $encoder->getColumns());
    }

    public function test_get_security_level_returns_default(): void
    {
        $encoder = $this->createEncoder();

        static::assertSame(Pdf417Encoder::DEFAULT_SECURITY_LEVEL, $encoder->getSecurityLevel());
    }

    public function test_set_security_level_sets_value(): void
    {
        $encoder = $this->createEncoder();

        $encoder->setSecurityLevel(3);

        static::assertSame(3, $encoder->getSecurityLevel());
    }

    /*
     |--------------------------------------------------------------------------
     | Sad paths
     |--------------------------------------------------------------------------
     */

    public function test_set_columns_throws_below_minimum(): void
    {
        $encoder = $this->createEncoder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Column count must be between 1 and 30. Given: 0');

        $encoder->setColumns(0);
    }

    public function test_set_columns_throws_above_maximum(): void
    {
        $encoder = $this->createEncoder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Column count must be between 1 and 30. Given: 31');

        $encoder->setColumns(31);
    }

    public function test_set_security_level_throws_below_minimum(): void
    {
        $encoder = $this->createEncoder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Security level must be between 0 and 8. Given: -1');

        $encoder->setSecurityLevel(-1);
    }

    public function test_set_security_level_throws_above_maximum(): void
    {
        $encoder = $this->createEncoder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Security level must be between 0 and 8. Given: 9');

        $encoder->setSecurityLevel(9);
    }

    /*
     |--------------------------------------------------------------------------
     | Angry paths
     |--------------------------------------------------------------------------
     */

    public function test_encode_throws_on_excessive_data(): void
    {
        $encoder = $this->createEncoder();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Barcode (must have|exceeds)/');

        $encoder->encode(str_repeat('a', 2000));
    }

    public function test_encode_produces_barcode_when_padding_not_needed(): void
    {
        $encoder = $this->createEncoder();
        $encoder->setColumns(5);
        $encoder->setSecurityLevel(5);

        $barcode = $encoder->encode('test');

        static::assertInstanceOf(Pdf417Barcode::class, $barcode);
        static::assertNotEmpty($barcode->codes);
    }

    public function test_encode_with_length_divisible_by_six(): void
    {
        $encoder = $this->createEncoder();

        $barcode = $encoder->encode('abcdef');

        static::assertInstanceOf(Pdf417Barcode::class, $barcode);
        static::assertNotEmpty($barcode->codeWords);
    }

    public function test_encode_throws_on_excessive_code_words_with_high_columns(): void
    {
        $encoder = $this->createEncoder();
        $encoder->setColumns(30);
        $encoder->setSecurityLevel(0);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Barcode exceeds maximum of 925 code words/');

        $encoder->encode(str_repeat('a', 1200));
    }
}
