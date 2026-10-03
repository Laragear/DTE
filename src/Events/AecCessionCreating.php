<?php

namespace Laragear\Dte\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Laragear\Dte\Builders\AecCessionBuilder;

class AecCessionCreating
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public AecCessionBuilder $builder,
    ) {
        //
    }
}
