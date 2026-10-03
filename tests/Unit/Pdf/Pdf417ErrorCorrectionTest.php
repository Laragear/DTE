<?php

namespace Tests\Unit\Pdf;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Laragear\Dte\Pdf\Ted\Pdf417ErrorCorrection;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class Pdf417ErrorCorrectionTest extends TestCase
{
    public function test_load_factors_throws_runtime_exception_on_file_not_found(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Unable to load PDF417 error correction data file.');

        $files = $this->createStub(Filesystem::class);
        $files->method('get')->willThrowException(new FileNotFoundException);

        new Pdf417ErrorCorrection($files);
    }
}
