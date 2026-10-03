<?php

namespace Laragear\Dte\Actions\Aec\Pipes;

use Closure;
use InvalidArgumentException;
use Laragear\Dte\Actions\Aec\AecData;
use Laragear\Dte\Enums\DteType;
use function in_array;

class ValidateDte
{
    /**
     * Handle the incoming AEC Data.
     *
     * @param  Closure(AecData): AecData  $next
     */
    public function handle(AecData $data, Closure $next): AecData
    {
        if (!in_array($data->dte->document_type, DteType::AEC_TRANSFERABLE, true)) {
            throw new InvalidArgumentException('The DTE type cannot be transferred through an AEC.');
        }

        if ($data->dte->folio === null || $data->dte->issued_on === null || $data->dte->payload?->xml === null) {
            throw new InvalidArgumentException('The AEC requires a compiled DTE with a folio and signed XML payload.');
        }

        return $next($data);
    }
}
