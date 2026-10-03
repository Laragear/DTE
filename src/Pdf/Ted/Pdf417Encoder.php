<?php

namespace Laragear\Dte\Pdf\Ted;

use InvalidArgumentException;
use RuntimeException;
use function base64_encode;
use function ceil;
use function strlen;

class Pdf417Encoder
{
    /**
     * The minimum number of columns allowed.
     */
    public const int MIN_COLUMNS = 1;

    /**
     * The maximum number of columns allowed.
     */
    public const int MAX_COLUMNS = 30;

    /**
     * The default column count for new encoders.
     */
    public const int DEFAULT_COLUMNS = 9;

    /**
     * The minimum error correction level allowed.
     */
    public const int MIN_SECURITY_LEVEL = 0;

    /**
     * The maximum error correction level allowed.
     */
    public const int MAX_SECURITY_LEVEL = 8;

    /**
     * The default error correction level for new encoders.
     */
    public const int DEFAULT_SECURITY_LEVEL = 5;

    /**
     * The minimum number of rows a barcode may have.
     */
    public const int MIN_ROWS = 3;

    /**
     * The maximum number of rows a barcode may have.
     */
    public const int MAX_ROWS = 90;

    /**
     * The maximum total code words a barcode may contain.
     */
    public const int MAX_CODE_WORDS = 925;

    /**
     * The start character prepended to every barcode row.
     */
    public const int START_CHARACTER = 0x1FEA8;

    /**
     * The stop character appended to every barcode row.
     */
    public const int STOP_CHARACTER = 0x3FA29;

    /**
     * The padding code word used to fill incomplete rows.
     */
    public const int PADDING_CODE_WORD = 900;

    /**
     * The primary byte encoding switch code word.
     */
    public const int BYTE_SWITCH_CODE = 901;

    /**
     * The alternate byte encoding switch code word.
     */
    public const int BYTE_SWITCH_CODE_ALT = 924;

    /**
     * The number of columns per row in the barcode matrix.
     */
    protected int $columns = self::DEFAULT_COLUMNS;

    /**
     * The error correction level applied to the barcode.
     */
    protected int $secLevel = self::DEFAULT_SECURITY_LEVEL;

    /**
     * Create a new PDF417 Encoder instance.
     */
    public function __construct(
        protected Pdf417Lookup $lookup,
        protected Pdf417Renderer $renderer,
    ) {
        //
    }

    /**
     * Encode TED data into a PDF417 barcode matrix.
     */
    public function encode(string $data): Pdf417Barcode
    {
        $codeWords = $this->encodeData($data);
        $secLevel = $this->secLevel;
        $columns = $this->columns;

        $codeGrid = array_chunk($codeWords, $columns);
        $rowCount = count($codeGrid);

        $this->validateBarcodeLimits($rowCount, count($codeWords));

        $encodedRows = $this->buildEncodedRows($codeGrid, $rowCount, $columns, $secLevel);

        return $this->createBarcode($encodedRows, $rowCount, $columns, $codeWords, $secLevel);
    }

    /**
     * Generate a base64-encoded data URI of the PDF417 barcode.
     */
    public function generate(string $data): string
    {
        return 'data:image/png;base64,'.base64_encode($this->renderer->render($this->encode($data)));
    }

    /**
     * Return the number of columns per row.
     */
    public function getColumns(): int
    {
        return $this->columns;
    }

    /**
     * Set the number of columns, validating the range.
     */
    public function setColumns(int $columns): void
    {
        $minimum = self::MIN_COLUMNS;
        $maximum = self::MAX_COLUMNS;

        if ($columns < $minimum || $columns > $maximum) {
            throw new InvalidArgumentException("Column count must be between $minimum and $maximum. Given: $columns");
        }

        $this->columns = $columns;
    }

    /**
     * Return the error correction security level.
     */
    public function getSecurityLevel(): int
    {
        return $this->secLevel;
    }

    /**
     * Set the error correction level, validating the range.
     */
    public function setSecurityLevel(int $secLevel): void
    {
        $minimum = self::MIN_SECURITY_LEVEL;
        $maximum = self::MAX_SECURITY_LEVEL;

        if ($secLevel < $minimum || $secLevel > $maximum) {
            throw new InvalidArgumentException("Security level must be between $minimum and $maximum. Given: $secLevel");
        }

        $this->secLevel = $secLevel;
    }

    /**
     * Build the encoded rows from the grid of code words.
     *
     * @param  array<int, array<int, int>>  $codeGrid
     * @return array<int, array<int, int>>
     */
    protected function buildEncodedRows(array $codeGrid, int $rowCount, int $columns, int $secLevel): array
    {
        $encodedRows = [];

        foreach ($codeGrid as $rowNumber => $row) {
            $encodedRows[] = $this->encodeRow($rowNumber, $row, $rowCount, $columns, $secLevel);
        }

        return $encodedRows;
    }

    /**
     * Encode a single row with start/stop markers and correction words.
     *
     * @param  array<int, int>  $row
     * @return array<int, int>
     */
    protected function encodeRow(int $rowNumber, array $row, int $rowCount, int $columns, int $secLevel): array
    {
        $tableId = $rowNumber % 3;

        $leftCodeWord = $this->getLeftCodeWord($rowNumber, $rowCount, $columns, $secLevel);
        $rightCodeWord = $this->getRightCodeWord($rowNumber, $rowCount, $columns, $secLevel);

        return [
            self::START_CHARACTER,
            $this->lookup->getCode($tableId, $leftCodeWord),
            ...$this->encodeDataWords($tableId, $row),
            $this->lookup->getCode($tableId, $rightCodeWord),
            self::STOP_CHARACTER,
        ];
    }

    /**
     * Encode data words using the active base-3 table.
     *
     * @param  array<int, int>  $row
     * @return array<int, int>
     */
    protected function encodeDataWords(int $tableId, array $row): array
    {
        $codeWords = [];

        foreach ($row as $dataWord) {
            $codeWords[] = $this->lookup->getCode($tableId, $dataWord);
        }

        return $codeWords;
    }

    /**
     * Assemble the barcode result object from all computed parts.
     *
     * @param  array<int, array<int, int>>  $encodedRows
     * @param  array<int, int>  $codeWords
     */
    protected function createBarcode(
        array $encodedRows,
        int $rowCount,
        int $columns,
        array $codeWords,
        int $secLevel
    ): Pdf417Barcode {
        return new Pdf417Barcode(
            $codeWords,
            $columns,
            $rowCount,
            $encodedRows,
            $secLevel,
        );
    }

    /**
     * Ensure the barcode row and word counts respect specification limits.
     */
    protected function validateBarcodeLimits(int $rowCount, int $codeWordCount): void
    {
        if ($rowCount < self::MIN_ROWS || $rowCount > self::MAX_ROWS) {
            throw new RuntimeException('Barcode must have between '.self::MIN_ROWS.' and '.self::MAX_ROWS." rows. Got: $rowCount");
        }

        if ($codeWordCount > self::MAX_CODE_WORDS) {
            throw new RuntimeException('Barcode exceeds maximum of '.self::MAX_CODE_WORDS." code words. Got: $codeWordCount");
        }
    }

    /**
     * Prepare data code words with padding and error correction.
     *
     * @return array<int, int>
     */
    protected function encodeData(string $data): array
    {
        $dataWords = $data
                |> $this->encodeByteData(...)
                |> $this->padDataWords(...)
                |> $this->prependLengthSpecifier(...);

        return array_merge($dataWords,
            $this->lookup->calculateErrorCorrectionWords($dataWords, $this->secLevel));
    }

    /**
     * Add padding words so the total aligns to the column count.
     *
     * @param  array<int, int>  $dataWords
     * @return array<int, int>
     */
    protected function padDataWords(array $dataWords): array
    {
        return array_merge(
            $dataWords, $this->getPadding(count($dataWords), 2 ** ($this->secLevel + 1), $this->columns)
        );
    }

    /**
     * Prepend the length specifier as the first data code word.
     *
     * @param  array<int, int>  $dataWords
     * @return array<int, int>
     */
    protected function prependLengthSpecifier(array $dataWords): array
    {
        array_unshift($dataWords, count($dataWords) + 1);

        return $dataWords;
    }

    /**
     * Encode raw bytes into PDF417 code words using Byte mode.
     *
     * @return array<int, int>
     */
    protected function encodeByteData(string $data): array
    {
        $codeWords = [$this->determineByteSwitchCode($data)];

        for ($i = 0, $chunkCount = (int) ceil(strlen($data) / 6); $i < $chunkCount; $i++) {
            $chunk = substr($data, $i * 6, 6);

            foreach ($this->encodeChunkData($chunk) as $codeWord) {
                $codeWords[] = $codeWord;
            }
        }

        return $codeWords;
    }

    /**
     * Choose the byte switch code word based on data length.
     */
    protected function determineByteSwitchCode(string $data): int
    {
        return strlen($data) % 6 === 0
            ? self::BYTE_SWITCH_CODE_ALT
            : self::BYTE_SWITCH_CODE;
    }

    /**
     * Encode a 6-byte chunk or an incomplete chunk.
     *
     * @return array<int, int>
     */
    protected function encodeChunkData(string $chunk): array
    {
        return strlen($chunk) === 6
            ? $this->encodeChunk($chunk)
            : $this->encodeIncompleteChunk($chunk);
    }

    /**
     * Encode a full 6-byte chunk into five code words.
     *
     * @return array<int, int>
     */
    protected function encodeChunk(string $chunk): array
    {
        return $this->divideChunkSum($this->calculateChunkSum($chunk));
    }

    /**
     * Compute the big-integer sum of a 6-byte chunk.
     */
    protected function calculateChunkSum(string $chunk): string
    {
        $sum = '0';

        for ($i = 0; $i < 6; $i++) {
            $char = substr($chunk, 5 - $i, 1);
            $value = bcmul(bcpow(256, $i), ord($char));
            $sum = bcadd($sum, $value);
        }

        return $sum;
    }

    /**
     * Split a chunk sum into five base-900 code words.
     *
     * @return array<int, int>
     */
    protected function divideChunkSum(string $sum): array
    {
        for ($i = 0, $codeWords = []; $i < 5; $i++) {
            $codeWord = (int) bcmod($sum, 900);

            $sum = bcdiv($sum, 900);

            array_unshift($codeWords, $codeWord);
        }

        return $codeWords;
    }

    /**
     * Encode a partial chunk as individual byte values.
     *
     * @return array<int, int>
     */
    protected function encodeIncompleteChunk(string $chunk): array
    {
        for ($i = 0, $codeWords = []; $i < strlen($chunk); $i++) {
            $codeWords[] = ord($chunk[$i]);
        }

        return $codeWords;
    }

    /**
     * Compute the left-side codeword for the given row.
     */
    protected function getLeftCodeWord(int $rowNumber, int $rowCount, int $columns, int $secLevel): int
    {
        return 30 * intval($rowNumber / 3) + $this->calculateLeftSegment($rowNumber, $rowCount, $columns, $secLevel);
    }

    /**
     * Calculate the left segment value based on the row table.
     */
    protected function calculateLeftSegment(int $rowNumber, int $rowCount, int $columns, int $secLevel): int
    {
        return match ($rowNumber % 3) {
            0 => intval(($rowCount - 1) / 3),
            1 => $secLevel * 3 + ($rowCount - 1) % 3,
            2 => $columns - 1,
            default => 0,
        };
    }

    /**
     * Compute the right-side codeword for the given row.
     */
    protected function getRightCodeWord(int $rowNumber, int $rowCount, int $columns, int $secLevel): int
    {
        return 30 * intval($rowNumber / 3) + $this->calculateRightSegment($rowNumber, $rowCount, $columns, $secLevel);
    }

    /**
     * Calculate the right segment value based on the row table.
     */
    protected function calculateRightSegment(int $rowNumber, int $rowCount, int $columns, int $secLevel): int
    {
        return match ($rowNumber % 3) {
            0 => $columns - 1,
            1 => intval(($rowCount - 1) / 3),
            2 => $secLevel * 3 + ($rowCount - 1) % 3,
            default => 0,
        };
    }

    /**
     * Generate padding words to align the data to column boundaries.
     *
     * @return array<int, int>
     */
    protected function getPadding(int $dataCount, int $errorCorrectionCount, int $columns): array
    {
        $remainder = ($dataCount + $errorCorrectionCount + 1) % $columns;

        if ($remainder > 0) {
            $paddingCount = $columns - $remainder;

            return array_fill(0, $paddingCount, self::PADDING_CODE_WORD);
        }

        return [];
    }
}
