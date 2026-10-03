<?php

namespace Laragear\Dte\Pdf\Ted;

class Pdf417Barcode
{
    /**
     * Create a new PDF417 barcode instance.
     *
     * @param  array<int, int>  $codeWords
     * @param  array<int, array<int, int>>  $codes
     */
    public function __construct(
        public array $codeWords,
        public int $columns,
        public int $rows,
        public array $codes,
        public int $securityLevel,
    ) {
        //
    }
}
