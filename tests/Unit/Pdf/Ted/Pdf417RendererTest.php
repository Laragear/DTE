<?php

namespace Tests\Unit\Pdf\Ted;

use Dom\XMLDocument;
use InvalidArgumentException;
use Laragear\Dte\Pdf\Ted\Pdf417Barcode;
use Laragear\Dte\Pdf\Ted\Pdf417Encoder;
use Laragear\Dte\Pdf\Ted\Pdf417Renderer;
use Laragear\Dte\Pdf\TedExtractor;
use Laragear\Dte\Proxies\GDProxy;
use Tests\TestCase;

class Pdf417RendererTest extends TestCase
{
    protected function createRenderer(array $options): Pdf417Renderer
    {
        return new Pdf417Renderer(
            $this->app->make(GDProxy::class),
            $options,
        );
    }

    /*
     |--------------------------------------------------------------------------
     | Happy paths
     |--------------------------------------------------------------------------
     */

    public function test_default_content_type_is_png(): void
    {
        $renderer = $this->app->make(Pdf417Renderer::class);

        static::assertSame('image/png', $renderer->getContentType());
    }

    public function test_get_content_type_for_jpg_format(): void
    {
        $renderer = $this->createRenderer(['format' => 'jpg']);

        static::assertSame('image/jpeg', $renderer->getContentType());
    }

    public function test_get_content_type_for_gif_format(): void
    {
        $renderer = $this->createRenderer(['format' => 'gif']);

        static::assertSame('image/gif', $renderer->getContentType());
    }

    public function test_get_content_type_for_bmp_format(): void
    {
        $renderer = $this->createRenderer(['format' => 'bmp']);

        static::assertSame('image/bmp', $renderer->getContentType());
    }

    public function test_renders_static_ted_binary(): void
    {
        $renderer = $this->app->make(Pdf417Renderer::class);

        $ted = $this->app->make(TedExtractor::class)->extract(
            XMLDocument::createFromFile(static::STUBS.'/static/dte-33-1-signed.xml')->saveXml()
        );

        static::assertSame(
            static::getStub('static/ted.png'),
            $renderer->render($this->app->make(Pdf417Encoder::class)->encode($ted))
        );
    }

    public function test_render_produces_png_binary_data(): void
    {
        $renderer = $this->app->make(Pdf417Renderer::class);

        $barcode = new Pdf417Barcode([], 2, 2, [[1, 0], [1, 1]], 0);

        $output = $renderer->render($barcode);

        static::assertNotEmpty($output);
        static::assertTrue(str_starts_with($output, "\x89PNG"));
    }

    public function test_option_overrides_are_applied(): void
    {
        $renderer = $this->createRenderer(['quality' => 50, 'scale' => 2]);

        static::assertSame('image/png', $renderer->getContentType());
        static::assertNotEmpty($renderer->render(new Pdf417Barcode([], 1, 1, [[1]], 0)));
    }

    public function test_render_produces_jpg_binary_data(): void
    {
        $renderer = $this->createRenderer(['format' => 'jpg', 'quality' => 80]);

        $barcode = new Pdf417Barcode([], 2, 2, [[1, 0], [1, 1]], 0);

        $output = $renderer->render($barcode);

        static::assertNotEmpty($output);
        static::assertTrue(str_starts_with($output, "\xFF\xD8"));
    }

    public function test_render_produces_gif_binary_data(): void
    {
        $renderer = $this->createRenderer(['format' => 'gif']);

        $barcode = new Pdf417Barcode([], 2, 2, [[1, 0], [1, 1]], 0);

        $output = $renderer->render($barcode);

        static::assertNotEmpty($output);
        static::assertTrue(str_starts_with($output, 'GIF'));
    }

    public function test_render_produces_bmp_binary_data(): void
    {
        $renderer = $this->createRenderer(['format' => 'bmp']);

        $barcode = new Pdf417Barcode([], 2, 2, [[1, 0], [1, 1]], 0);

        $output = $renderer->render($barcode);

        static::assertNotEmpty($output);
        static::assertTrue(str_starts_with($output, 'BM'));
    }

    /*
     |--------------------------------------------------------------------------
     | Sad paths
     |--------------------------------------------------------------------------
     */

    public function test_throws_on_invalid_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Invalid option "format": "tiff". Expected one of: jpg, png, gif, bmp.');

        $this->createRenderer(['format' => 'tiff']);
    }

    public function test_throws_on_invalid_scale(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Invalid option "scale": "0". Expected an integer between 1 and 20.');

        $this->createRenderer(['scale' => 0]);
    }

    public function test_throws_on_scale_above_maximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Invalid option "scale": "21". Expected an integer between 1 and 20.');

        $this->createRenderer(['scale' => 21]);
    }

    public function test_throws_on_invalid_ratio(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Invalid option "ratio": "0". Expected an integer between 1 and 10.');

        $this->createRenderer(['ratio' => 0]);
    }

    public function test_throws_on_invalid_padding(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Invalid option "padding": "-1". Expected an integer between 0 and 50.');

        $this->createRenderer(['padding' => -1]);
    }

    public function test_throws_on_invalid_quality(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Invalid option "quality": "-1". Expected an integer between 0 and 100.');

        $this->createRenderer(['quality' => -1]);
    }

    public function test_throws_on_invalid_color(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Invalid option "color": "invalid". Supported color formats: "#000000".');

        $this->createRenderer(['color' => 'invalid']);
    }

    public function test_throws_on_invalid_background_color(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Invalid option "bgColor": "invalid". Supported color formats: "#000000".');

        $this->createRenderer(['bgColor' => 'invalid']);
    }

    public function test_throws_with_all_validation_errors_when_multiple_options_are_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid option "format".*Invalid option "scale"/s');

        $this->createRenderer(['format' => 'tiff', 'scale' => 0]);
    }
}
