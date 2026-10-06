<?php

namespace Laragear\Dte\Actions\CompileDte;

use Illuminate\Pipeline\Pipeline;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Models\SiiDte;
use Throwable;

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
        try {
            return $this->send(new Compilation($dte))->thenReturn()->dte;
        } catch (Throwable $e) {
            $dte->refresh();

            // Only this run's claim is reset: a Pending status means the claim
            // was lost (or never won) and another process owns the document.
            if ($dte->status === DteStatus::Building || $dte->status === DteStatus::Signing) {
                $dte->failToDraft('compile', $e);
            }

            throw $e;
        }
    }
}
