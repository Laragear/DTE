<?php

namespace Laragear\Dte\Actions\Aec\Pipes;

use Closure;
use Illuminate\Support\DateFactory;
use Laragear\Dte\Actions\Aec\AecData;

class PrepareAecContext
{
    /**
     * Create a new Prepare AEC Context instance.
     */
    public function __construct(
        protected DateFactory $date,
    ) {
        //
    }

    /**
     * Handle the incoming AEC Data.
     *
     * @param  Closure(AecData): AecData  $next
     */
    public function handle(AecData $data, Closure $next): AecData
    {
        $data->signedAt ??= $this->date->now('America/Santiago')->toDateTimeImmutable();

        $data->aecID = 'AEC-'.$data->dte->document_type->value.'-'.$data->dte->folio;
        $data->dtecID = 'DTECedido-'.$data->dte->document_type->value.'-'.$data->dte->folio;
        $data->cessionID = 'Cesion-1-'.$data->dte->folio;

        return $next($data);
    }
}
