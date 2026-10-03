<?php

namespace Laragear\Dte\Actions\RcvParsing;

use Illuminate\Pipeline\Pipeline;
use Laragear\Dte\Enums\RcvType;
use Laragear\Rut\Rut;

/**
 * @method ParsingContext thenReturn()
 */
class Parse extends Pipeline
{
    /**
     * The processing sequence mapped natively.
     *
     * @var class-string[]
     */
    protected $pipes = [
        Pipes\NormalizeToStreamResource::class,
        Pipes\ApplyEncodingStreamFilter::class,
        Pipes\YieldLazyCollection::class,
    ];

    /**
     * Process natively executing the stream bounds mapping execution logic safely.
     */
    public function forBatch(mixed $source, RcvType $type, Rut $companyRut): ParsingContext
    {
        return $this->send(new ParsingContext($source, $type, $companyRut))->thenReturn();
    }
}
