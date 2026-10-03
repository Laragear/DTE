<?php

namespace Laragear\Dte\Data;

use Illuminate\Support\Carbon;
use Laragear\Dte\Enums\DteType;
use Laragear\Rut\Rut;

readonly class RcvRecord
{
    /**
     * Create a new RCV Record instance.
     */
    public function __construct(
        public Rut $issuer,
        public Rut $receiver,
        public DteType $documentType,
        public int $folio,
        public int $amountTotal,
        public string $characterization,
        public ?Carbon $issuedOn = null,
        public ?Carbon $acknowledgedAt = null,
    ) {
    //
    }
}
