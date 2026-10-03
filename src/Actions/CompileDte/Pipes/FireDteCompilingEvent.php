<?php

namespace Laragear\Dte\Actions\CompileDte\Pipes;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Laragear\Dte\Actions\CompileDte\Compilation;
use Laragear\Dte\Events\DteCompiling;

class FireDteCompilingEvent
{
    /**
     * Create a new Fire Compiling Dte Event instance.
     */
    public function __construct(protected Dispatcher $event)
    {
        //
    }

    /**
     * Handle the incoming DTE compilation.
     *
     * @param  Closure(Compilation): Compilation  $next
     */
    public function handle(Compilation $compilation, Closure $next): Compilation
    {
        $this->event->dispatch(new DteCompiling($compilation->dte));

        return $next($compilation);
    }
}
