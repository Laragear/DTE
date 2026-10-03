<?php

namespace Laragear\Dte\Pdf\Ted;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use RuntimeException;
use function array_reverse;

class Pdf417ErrorCorrection
{
    /**
     * The Reed-Solomon error correction factors for each security level.
     *
     * @var array<int, array<int, int>>
     */
    protected array $factors;

    /**
     * Create a new Error Correction instance.
     */
    public function __construct(
        protected Filesystem $files,
    ) {
        $this->factors = $this->loadFactors();
    }

    /**
     * Load the Reed-Solomon factors from the binary data file.
     *
     * @return array<int, array<int, int>>
     */
    protected function loadFactors(): array
    {
        try {
            $contents = $this->files->get(__DIR__.'/stubs/error_correction.bin');
        } catch (FileNotFoundException $e) {
            throw new RuntimeException('Unable to load PDF417 error correction data file.', 0, $e);
        }

        /** @var array<int, array<int, int>> $factors */
        return unserialize($contents);
    }

    /**
     * Calculate the Reed-Solomon error correction code words for given data.
     *
     * @param  array<int, int>  $dataWords  The data code words.
     * @return array<int, int>
     */
    public function calculateErrorCorrectionWords(array $dataWords, int $securityLevel): array
    {
        // The correction level (0-8).
        $this->ensureValidLevel($securityLevel);

        return $this->calculateSyndrome($dataWords, $this->factors[$securityLevel])
                |> $this->invertCorrectionValues(...)
                |> array_reverse(...);
    }

    /**
     * Validate that the given security level has a factor table.
     */
    protected function ensureValidLevel(int $securityLevel): void
    {
        if (!isset($this->factors[$securityLevel])) {
            $message = sprintf('Invalid correction level given: "%s". Valid values are 0-8.', $securityLevel);

            throw new InvalidArgumentException($message);
        }
    }

    /**
     * Calculate the raw syndrome values using the Reed-Solomon algorithm.
     *
     * @param  array<int, int>  $dataWords
     * @param  array<int, int>  $factors
     * @return array<int, int>
     */
    protected function calculateSyndrome(array $dataWords, array $factors): array
    {
        $wordCount = count($factors);
        $errorCorrectionWords = array_fill(0, $wordCount, 0);
        $lastIndex = $wordCount - 1;

        foreach ($dataWords as $value) {
            $syndrome = ($value + $errorCorrectionWords[$lastIndex]) % 929;

            $this->updateSyndromeRow($syndrome, $lastIndex, $factors, $errorCorrectionWords);
        }

        return $errorCorrectionWords;
    }

    /**
     * Update a single row of syndrome values in place.
     *
     * @param  array<int, int>  $factors
     * @param  array<int, int>  $errorCorrectionWords
     */
    protected function updateSyndromeRow(
        int $syndrome,
        int $lastIndex,
        array $factors,
        array &$errorCorrectionWords,
    ): void {
        for ($i = $lastIndex; $i >= 0; $i -= 1) {
            $previousWord = $errorCorrectionWords[$i - 1] ?? 0;
            $errorCorrectionWords[$i] = ($previousWord + 929 - ($syndrome * $factors[$i]) % 929) % 929;
        }
    }

    /**
     * Invert the syndrome values using 929-complement for non-zero entries.
     *
     * @param  array<int, int>  $errorCorrectionWords
     * @return array<int, int>
     */
    protected function invertCorrectionValues(array $errorCorrectionWords): array
    {
        foreach ($errorCorrectionWords as &$codeWord) {
            if ($codeWord > 0) {
                $codeWord = 929 - $codeWord;
            }
        }

        unset($codeWord);

        return $errorCorrectionWords;
    }
}
