<?php

namespace Laragear\Dte\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Laragear\Dte\Models\SiiCaf;

class CafFoliosRestored
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  array<int|array{int, int}>  $folios
     */
    public function __construct(public SiiCaf $caf, public array $folios)
    {
        //
    }
}
