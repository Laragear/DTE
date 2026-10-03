<?php

namespace Laragear\Dte\Actions\CompileDte\Pipes;

use Closure;
use Laragear\Dte\Actions\CompileDte\Compilation;
use Laragear\Dte\Caf\CafManager;
use Laragear\Dte\Caf\Exceptions\DepletionException;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Models\SiiCaf;

class AcquireFolio
{
    /**
     * Create an Acquire Folio pipe instance.
     */
    public function __construct(
        protected CafManager $caf,
    ) {
        //
    }

    /**
     * Handle the incoming DTE compilation.
     *
     * @param  Closure(Compilation): Compilation  $next
     */
    public function handle(Compilation $compilation, Closure $next): Compilation
    {
        if ($compilation->dte->folio !== null && $compilation->dte->caf()->getParentKey() !== null) {
            return $next($compilation);
        }

        try {
            $this->allocate($compilation);
        } catch (DepletionException) {
            $compilation->dte->transitionTo(DteStatus::RequiresCaf);

            return $compilation;
        }

        return $next($compilation);
    }

    /**
     * Allocate and persist a CAF folio atomically.
     */
    protected function allocate(Compilation $compilation): void
    {
        $dte = $compilation->dte;

        $this->caf->allocate(
            $dte->issuer_rut,
            $dte->document_type,
            static function (SiiCaf $caf, int $folio) use ($dte): void {
                $dte->forceFill([
                    'sii_caf_id' => $caf->getKey(),
                    'folio' => $folio,
                ]);

                $dte->save();

                $dte->setRelation('caf', $caf);
            },
        );
    }
}
