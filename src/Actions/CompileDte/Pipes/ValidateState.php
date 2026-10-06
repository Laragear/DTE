<?php

namespace Laragear\Dte\Actions\CompileDte\Pipes;

use Closure;
use Illuminate\Support\DateFactory;
use Laragear\Dte\Actions\CompileDte\Compilation;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Models\SiiDte;
use LogicException;

class ValidateState
{
    /**
     * Create a new Validate State instance.
     */
    public function __construct(protected DateFactory $date)
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
        $dte = $compilation->dte;

        if ($dte->status === DteStatus::Pending) {
            $this->updateModel($dte);
        } elseif ($dte->status->isNotCompilable()) {
            throw new LogicException('Only pending or building DTE documents may be compiled.');
        }

        return $next($compilation);
    }

    /**
     * Updates the model in the database using a direct database query.
     */
    protected function updateModel(SiiDte $dte): void
    {
        $now = $this->date->now();

        // We will do a clever check. Since the truth is on the database, we will run a
        // direct UPDATE query into the row. If the query returns 1 row affected, then
        // the model data is updated, and we can sync the changes for the next pipes.
        // A previous failure reason is consumed when a new compile starts.
        $updated = $dte->newModelQuery()
            ->whereKey($dte->getKey())
            ->where('status', DteStatus::Pending)
            ->update(['status' => DteStatus::Building, 'failure' => null, 'updated_at' => $now]);

        if ($updated < 1) {
            throw new LogicException('The DTE document is already being processed.');
        }

        $dte->forceFill([
            'status' => DteStatus::Building,
            'failure' => null,
            'updated_at' => $now,
        ]);

        $dte->syncChanges();
    }
}
