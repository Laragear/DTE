<?php

namespace Laragear\Dte\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Laragear\Dte\Enums\DteType;
use Laragear\Rut\Rut;

class CafDepleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Rut $issuer, public DteType $documentType)
    {
        //
    }
}
