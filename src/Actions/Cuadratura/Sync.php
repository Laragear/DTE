<?php

namespace Laragear\Dte\Actions\Cuadratura;

use Illuminate\Pipeline\Pipeline;
use Laragear\Dte\Actions\RcvParsing\ParsingContext;

/**
 * @method CuadraturaContext thenReturn()
 */
class Sync extends Pipeline
{
    /**
     * The processing sequence mapped natively.
     *
     * @var class-string[]
     */
    protected $pipes = [
        Pipes\ReconcileRcvStream::class,
        Pipes\DowngradeOrphanedDocuments::class,
    ];

    /**
     * Process Cuadratura tracking metrics mapping DB bounds smoothly seamlessly.
     *
     * @return array<string, int>
     */
    public function forParsing(ParsingContext $parsingContext): array
    {
        return $this->send(new CuadraturaContext($parsingContext))->thenReturn()->metrics;
    }
}
