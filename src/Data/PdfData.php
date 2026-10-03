<?php

namespace Laragear\Dte\Data;

readonly class PdfData
{
    /**
     * Create a new PDF Data instance.
     */
    public function __construct(
        public string $disk,
        public string $path,
    ) {
        //
    }
}
