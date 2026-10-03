<?php

namespace Tests\Unit\Pdf;

use Laragear\Dte\Pdf\Ted\Pdf417Encoder;
use Laragear\Dte\Pdf\Ted\Pdf417Lookup;
use Laragear\Dte\Pdf\Ted\Pdf417Renderer;
use PHPUnit\Framework\TestCase;

class Pdf417EncoderTest extends TestCase
{
    public function test_generate_returns_base64_data_uri_png(): void
    {
        $lookup = $this->createStub(Pdf417Lookup::class);
        $renderer = $this->createStub(Pdf417Renderer::class);
        $renderer->method('render')->willReturn("\x89PNG\r\n\x1a\n");

        $encoder = new Pdf417Encoder($lookup, $renderer);
        $result = $encoder->generate(str_repeat('x', 100));

        static::assertStringStartsWith('data:image/png;base64,', $result);
    }
}
