<?php

namespace Laragear\Dte\Events;

use Laragear\Dte\Models\SiiDte;

class DteBuilding
{
    /**
     * Create a new Dte Building instance.
     */
    public function __construct(public SiiDte $dte)
    {
        //
    }
}
