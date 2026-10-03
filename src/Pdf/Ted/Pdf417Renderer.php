<?php

namespace Laragear\Dte\Pdf\Ted;

use GdImage;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Laragear\Dte\Proxies\GDProxy;
use function strlen;

class Pdf417Renderer
{
    /**
     * Maps output format extensions to their MIME content types.
     *
     * @var array<string, string>
     */
    protected const array FORMATS = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
    ];

    /**
     * Configurable rendering options for scale, ratio, padding, and colors.
     *
     * @var array<string, int|string>
     */
    protected array $options = [
        'format' => 'png',
        'quality' => 90,
        'scale' => 3,
        'ratio' => 3,
        'padding' => 20,
        'color' => '#000000',
        'bgColor' => '#ffffff',
    ];

    /**
     * Create a new renderer with the given options.
     */
    public function __construct(
        protected GDProxy $gd,
        array $options = [],
    ) {
        foreach ($options as $optionKey => $optionValue) {
            if (array_key_exists($optionKey, $this->options)) {
                $this->options[$optionKey] = $optionValue;
            }
        }

        $validationErrors = $this->validateOptions();

        if ($validationErrors !== []) {
            throw new InvalidArgumentException(implode("\n", $validationErrors));
        }
    }

    /**
     * Return MIME content type for the configured output format.
     */
    public function getContentType(): ?string
    {
        return Arr::get(self::FORMATS, $this->options['format']);
    }

    /**
     * Render a PDF417 barcode into binary image data.
     */
    public function render(Pdf417Barcode $data): string
    {
        $pixelGrid = $this->getPixelGrid($data);

        [$gridWidth, $gridHeight] = $this->resolveGridDimensions($pixelGrid);
        [$scaledWidth, $scaledHeight] = $this->calculateScaledDimensions($gridWidth, $gridHeight);

        $baseImage = $this->createBaseImage($pixelGrid, $gridWidth, $gridHeight);
        $scaledImage = $this->scaleImage($baseImage, $gridWidth, $gridHeight, $scaledWidth, $scaledHeight);
        $paddedImage = $this->createPaddedImage($scaledImage, $scaledWidth, $scaledHeight);

        return $this->encodeToBinary($paddedImage);
    }

    /**
     * Convert the codeword matrix into a grid of boolean pixel values.
     *
     * @return array<int, array<int, bool>>
     */
    protected function getPixelGrid(Pdf417Barcode $barcode): array
    {
        $pixelGrid = [];

        foreach ($barcode->codes as $row) {
            $pixelGrid[] = $this->pixelsForRow($row);
        }

        return $pixelGrid;
    }

    /**
     * Expand a single codeword row into a flat list of boolean pixel values.
     *
     * @param  array<int, int>  $row
     * @return array<int, bool>
     */
    protected function pixelsForRow(array $row): array
    {
        $pixelRow = [];

        foreach ($row as $codeWord) {
            $binary = decbin($codeWord);

            for ($i = 0; $i < strlen($binary); $i++) {
                $pixelRow[] = (bool) $binary[$i];
            }
        }

        return $pixelRow;
    }

    /**
     * Validate all rendering options and return collected error messages.
     *
     * @return array<int, string>
     */
    protected function validateOptions(): array
    {
        return array_values(array_filter([
            $this->validateFormat(),
            $this->validateNumericOption('scale', 1, 20),
            $this->validateNumericOption('ratio', 1, 10),
            $this->validateNumericOption('padding', 0, 50),
            $this->validateNumericOption('quality', 0, 100),
            $this->validateColorOption('color'),
            $this->validateColorOption('bgColor'),
        ], static fn(?string $error) => $error !== null));
    }

    /**
     * Validate the output format is a recognized extension.
     */
    protected function validateFormat(): ?string
    {
        $format = $this->options['format'];

        if (!array_key_exists($format, self::FORMATS)) {
            $formats = implode(', ', array_keys(self::FORMATS));

            return "Invalid option \"format\": \"$format\". Expected one of: $formats.";
        }

        return null;
    }

    /**
     * Validate a numeric option falls within the given bounds.
     */
    protected function validateNumericOption(string $option, int $minimum, int $maximum): ?string
    {
        $optionValue = $this->options[$option];

        if (!is_numeric($optionValue) || $optionValue < $minimum || $optionValue > $maximum) {
            return "Invalid option \"$option\": \"$optionValue\". Expected an integer between $minimum and $maximum.";
        }

        return null;
    }

    /**
     * Validate a color option is a parseable hex string.
     */
    protected function validateColorOption(string $option): ?string
    {
        $color = $this->options[$option];

        if ($this->parseColor($color) === null) {
            return "Invalid option \"$option\": \"$color\". Supported color formats: \"#000000\".";
        }

        return null;
    }

    /**
     * Determine the grid dimensions from the pixel data.
     *
     * @param  array<int, array<int, bool>>  $pixelGrid
     * @return array{0: int, 1: int}
     */
    protected function resolveGridDimensions(array $pixelGrid): array
    {
        return [count($pixelGrid[0]), count($pixelGrid)];
    }

    /**
     * Calculate scaled dimensions from grid size and configured scale/ratio.
     *
     * @return array{0: int, 1: int}
     */
    protected function calculateScaledDimensions(int $gridWidth, int $gridHeight): array
    {
        $scale = (int) $this->options['scale'];
        $ratio = (int) $this->options['ratio'];

        return [$gridWidth * $scale, $gridHeight * $scale * $ratio];
    }

    /**
     * Create the base image by drawing the pixel grid.
     *
     * @param  array<int, array<int, bool>>  $pixelGrid
     */
    protected function createBaseImage(array $pixelGrid, int $gridWidth, int $gridHeight): GdImage
    {
        $background = $this->parseColor($this->options['bgColor']);
        $foreground = $this->parseColor($this->options['color']);

        $baseImage = $this->gd->createImage($gridWidth, $gridHeight);
        $backgroundColor = $this->gd->allocateColor($baseImage, ...$background);
        $foregroundColor = $this->gd->allocateColor($baseImage, ...$foreground);

        $this->gd->fillRectangle($baseImage, 0, 0, $gridWidth, $gridHeight, $backgroundColor);
        $this->drawPixelData($baseImage, $pixelGrid, $foregroundColor);

        return $baseImage;
    }

    /**
     * Set foreground pixels on the image for each true value in the grid.
     *
     * @param  array<int, array<int, bool>>  $pixelGrid
     */
    protected function drawPixelData(GdImage $image, array $pixelGrid, int $foregroundColor): void
    {
        foreach ($pixelGrid as $y => $row) {
            foreach ($row as $x => $value) {
                if ($value) {
                    $this->gd->setPixel($image, $x, $y, $foregroundColor);
                }
            }
        }
    }

    /**
     * Scale the base image using resampling.
     */
    protected function scaleImage(
        GdImage $baseImage,
        int $gridWidth,
        int $gridHeight,
        int $scaledWidth,
        int $scaledHeight
    ): GdImage {
        $background = $this->parseColor($this->options['bgColor']);

        $scaledImage = $this->gd->createImage($scaledWidth, $scaledHeight);
        $backgroundColor = $this->gd->allocateColor($scaledImage, ...$background);

        $this->gd->fillRectangle($scaledImage, 0, 0, $scaledWidth, $scaledHeight, $backgroundColor);
        $this->gd->resample($scaledImage, $baseImage, 0, 0, 0, 0, $scaledWidth, $scaledHeight, $gridWidth, $gridHeight);

        return $scaledImage;
    }

    /**
     * Create the final padded image by placing the scaled image on a background.
     */
    protected function createPaddedImage(GdImage $scaledImage, int $scaledWidth, int $scaledHeight): GdImage
    {
        $padding = (int) $this->options['padding'];
        $background = $this->parseColor($this->options['bgColor']);

        $finalWidth = $scaledWidth + 2 * $padding;
        $finalHeight = $scaledHeight + 2 * $padding;

        $finalImage = $this->gd->createImage($finalWidth, $finalHeight);
        $backgroundColor = $this->gd->allocateColor($finalImage, ...$background);

        $this->gd->fillRectangle($finalImage, 0, 0, $finalWidth, $finalHeight, $backgroundColor);
        $this->gd->copy($finalImage, $scaledImage, $padding, $padding, 0, 0, $scaledWidth, $scaledHeight);

        return $finalImage;
    }

    /**
     * Encode the final image into binary data of the configured format.
     */
    protected function encodeToBinary(GdImage $image): string
    {
        $format = $this->options['format'];
        $quality = (int) $this->options['quality'];

        return match ($format) {
            'png' => $this->gd->toPng($image),
            'jpg' => $this->gd->toJpeg($image, $quality),
            'gif' => $this->gd->toGif($image),
            'bmp' => $this->gd->toBmp($image),
            default => throw new InvalidArgumentException("Unsupported barcode format [{$format}]."),
        };
    }

    /**
     * Parse a hex color string into its RGB components.
     *
     * @return array{0: int, 1: int, 2: int}|null
     */
    protected function parseColor(string $color): ?array
    {
        $components = [];

        if (preg_match('/^#([0-9a-fA-F]{6})$/', $color, $components)) {
            $hex = $components[1];

            return [
                hexdec($hex[0].$hex[1]),
                hexdec($hex[2].$hex[3]),
                hexdec($hex[4].$hex[5]),
            ];
        }

        return null;
    }
}
