<?php

namespace Laragear\Dte\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Laragear\Dte\Builders\DocumentBuilder;

class DteCreating
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public DocumentBuilder $builder,
    ) {
        //
    }
}
