<?php

namespace Laragear\Dte\Actions\CompileDte;

use Illuminate\Pipeline\Pipeline;
use Laragear\Dte\Models\SiiDte;

/**
 * @method Compilation thenReturn()
 */
class Compile extends Pipeline
{
    /**
     * The compilation stages.
     *
     * @var class-string[]
     */
    protected $pipes = [
        Pipes\FireDteCompilingEvent::class,
        Pipes\ValidateState::class,
        Pipes\AcquireFolio::class,
        Pipes\FireDteBuildingEvent::class,
        Pipes\BuildXml::class,
        Pipes\FireDteBuiltEvent::class,
        Pipes\GenerateTed::class,
        Pipes\ApplyTedToDom::class,
        Pipes\ApplyDigitalSignature::class,
        Pipes\XsdValidation::class,
        Pipes\FireDteCompiledEvent::class,
    ];

    /**
     * Send the DTE being compiled.
     */
    public function forDte(SiiDte $dte): SiiDte
    {
        return $this->send(new Compilation($dte))->thenReturn()->dte;
    }
}
