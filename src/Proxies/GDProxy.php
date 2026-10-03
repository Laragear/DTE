<?php

namespace Laragear\Dte\Proxies;

use Closure;
use GdImage;
use RuntimeException;
use Throwable;
use function imagebmp;
use function imagecolorallocate;
use function imagecopy;
use function imagecopyresampled;
use function imagecreatetruecolor;
use function imagefilledrectangle;
use function imagegif;
use function imagejpeg;
use function imagepng;
use function imagesetpixel;
use function ob_get_clean;
use function ob_start;

/** @internal */
class GDProxy
{
    /**
     * Create a new true-color image of the given dimensions.
     */
    public function createImage(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor($width, $height);

        if ($image === false) {
            throw new RuntimeException("Could not create a {$width}x{$height} true-color image.");
        }

        return $image;
    }

    /**
     * Allocate a color for the given true-color image.
     */
    public function allocateColor(GdImage $image, int $red, int $green, int $blue): int
    {
        $color = imagecolorallocate($image, $red, $green, $blue);

        if ($color === false) {
            throw new RuntimeException('Could not allocate the requested color.');
        }

        return $color;
    }

    /**
     * Draw a filled rectangle on the image.
     */
    public function fillRectangle(GdImage $image, int $x1, int $y1, int $x2, int $y2, int $color): bool
    {
        return imagefilledrectangle($image, $x1, $y1, $x2, $y2, $color);
    }

    /**
     * Set a single pixel to the given color.
     */
    public function setPixel(GdImage $image, int $x, int $y, int $color): bool
    {
        return imagesetpixel($image, $x, $y, $color);
    }

    /**
     * Resample an image to different dimensions with smoothing.
     */
    public function resample(
        GdImage $destination,
        GdImage $source,
        int $destinationX,
        int $destinationY,
        int $sourceX,
        int $sourceY,
        int $destinationWidth,
        int $destinationHeight,
        int $sourceWidth,
        int $sourceHeight,
    ): bool {
        return imagecopyresampled(
            $destination,
            $source,
            $destinationX,
            $destinationY,
            $sourceX,
            $sourceY,
            $destinationWidth,
            $destinationHeight,
            $sourceWidth,
            $sourceHeight,
        );
    }

    /**
     * Copy a rectangular region of one image onto another.
     */
    public function copy(
        GdImage $destination,
        GdImage $source,
        int $destinationX,
        int $destinationY,
        int $sourceX,
        int $sourceY,
        int $sourceWidth,
        int $sourceHeight,
    ): bool {
        return imagecopy(
            $destination,
            $source,
            $destinationX,
            $destinationY,
            $sourceX,
            $sourceY,
            $sourceWidth,
            $sourceHeight,
        );
    }

    /**
     * Render the image as PNG binary data.
     */
    public function toPng(GdImage $image, int $quality = -1, int $filters = -1): string
    {
        return $this->captureOutput(static function () use ($image, $quality, $filters): void {
            imagepng($image, null, $quality, $filters);
        });
    }

    /**
     * Render the image as JPEG binary data.
     */
    public function toJpeg(GdImage $image, int $quality = -1): string
    {
        return $this->captureOutput(static function () use ($image, $quality): void {
            imagejpeg($image, null, $quality);
        });
    }

    /**
     * Render the image as GIF binary data.
     */
    public function toGif(GdImage $image): string
    {
        return $this->captureOutput(static function () use ($image): void {
            imagegif($image);
        });
    }

    /**
     * Render the image as BMP binary data.
     */
    public function toBmp(GdImage $image, bool $compressed = true): string
    {
        return $this->captureOutput(static function () use ($image, $compressed): void {
            imagebmp($image, null, $compressed);
        });
    }

    /**
     * Capture the output of a callback into a binary string.
     */
    protected function captureOutput(Closure $callback): string
    {
        ob_start();

        try {
            $callback();
        } catch (Throwable $e) {
            ob_end_clean();

            throw $e;
        }

        return ob_get_clean() ?: '';
    }
}
