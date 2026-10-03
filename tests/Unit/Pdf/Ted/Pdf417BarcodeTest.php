<?php

namespace Tests\Unit\Pdf\Ted;

use Laragear\Dte\Pdf\Ted\Pdf417Barcode;
use Tests\TestCase;

class Pdf417BarcodeTest extends TestCase
{
    public function test_constructs_with_all_properties(): void
    {
        $barcode = new Pdf417Barcode([1, 2, 3], 5, 3, [[1, 2], [3, 4], [5, 6]], 7);

        static::assertSame([1, 2, 3], $barcode->codeWords);
        static::assertSame(5, $barcode->columns);
        static::assertSame(3, $barcode->rows);
        static::assertSame([[1, 2], [3, 4], [5, 6]], $barcode->codes);
        static::assertSame(7, $barcode->securityLevel);
    }
}
