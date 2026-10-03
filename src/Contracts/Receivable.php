<?php

namespace Laragear\Dte\Contracts;

use Laragear\Dte\Data\ReceiverData;

interface Receivable
{
    /**
     * Returns a Receivable representation of the object.
     */
    public function toReceiver(): ReceiverData;
}
