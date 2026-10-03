<?php

namespace Laragear\Dte\Events;

use Laragear\Dte\Models\SiiDte;

class DteBuilt
{
    /**
     * Create a new Dte Built instance.
     */
    public function __construct(public SiiDte $dte)
    {
        //
    }
}
