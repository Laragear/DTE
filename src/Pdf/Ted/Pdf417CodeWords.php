<?php

namespace Laragear\Dte\Pdf\Ted;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

class Pdf417CodeWords
{
    /**
     * The cached codeword lookup tables loaded from binary storage.
     *
     * @var array<int, array<int, int>>
     */
    protected array $codes;

    /**
     * Create a new Code Words instance.
     */
    public function __construct(
        protected Filesystem $files,
    ) {
        // I know it's bad pattern to load things on construction, but there is
        // no point in not loading the Code Words to the class. The class only
        // exposes one method to calculate the codes so I'll allow it myself.
        $this->codes = $this->loadCodes();
    }

    /**
     * Load the codified lookup tables from the binary data file.
     *
     * @return array<int, array<int, int>>
     */
    protected function loadCodes(): array
    {
        try {
            $contents = $this->files->get(__DIR__.'/stubs/codewords.bin');
        } catch (FileNotFoundException $e) {
            throw new RuntimeException('Unable to load PDF417 code words data file.', 0, $e);
        }

        /** @var array<int, array<int, int>> $codes */
        $codes = unserialize($contents);

        return $codes;
    }

    /**
     * Return the codified value for the given table and code word index.
     */
    public function getCode(int $table, int $word): int
    {
        return $this->codes[$table][$word]
            ?? throw new RuntimeException("Invalid code word [$table][$word].");
    }
}
