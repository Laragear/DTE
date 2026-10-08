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
        Pipes\DetectOrphanedDocuments::class,
    ];

    /**
     * Process Cuadratura tracking metrics mapping DB bounds smoothly seamlessly.
     *
     * @return array<string, int>
     */
    public function forParsing(ParsingContext $parsingContext): array
    {
        $context = new CuadraturaContext($parsingContext, $parsingContext->period);

        return $this->send($context)->thenReturn()->metrics;
    }
}
