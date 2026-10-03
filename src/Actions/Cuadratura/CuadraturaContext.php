<?php

namespace Laragear\Dte\Actions\Cuadratura;

use Laragear\Dte\Actions\RcvParsing\ParsingContext;

class CuadraturaContext
{
    /**
     * Create a new Cuadratura Context instance.
     */
    public function __construct(
        public readonly ParsingContext $parsingContext,
        public array $matchedLocalIds = [],
        public array $metrics = [
            'matched' => 0,
            'phantoms' => 0,
            'discrepancies' => 0,
            'orphans' => 0,
        ],
    ) {
        //
    }
}
