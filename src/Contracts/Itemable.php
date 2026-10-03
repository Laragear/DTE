<?php

namespace Laragear\Dte\Contracts;

use Laragear\Dte\Data\Item;

interface Itemable
{
    /**
     * Returns an Itemable representation of the object.
     */
    public function toItem(): Item;
}
