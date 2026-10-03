<?php

namespace Laragear\Dte\Contracts;

use Laragear\Dte\Data\IssuerData;

interface Issuable
{
    /**
     * Returns an Issuable representation of the object.
     */
    public function toIssuer(): IssuerData;
}
