<?php

namespace Laragear\Dte\Data;

use Laragear\Rut\Rut;

readonly class CompanyData
{
    /**
     * Create a new Company Data instance.
     */
    public function __construct(
        public IssuerData $issuer,
        public Rut $senderRut,
    ) {
        //
    }

    /**
     * Create a new instance fluently.
     */
    public static function make(IssuerData $issuer, Rut|string|null $senderRut = null): static
    {
        return new static($issuer, Rut::parse($senderRut ?? $issuer->rut));
    }
}
