<?php

namespace Laragear\Dte\Console\Commands;

use Illuminate\Console\Command;
use Laragear\Dte\Services\PackDtesService;

class PackReadyDtesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dte:pack-ready';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Packs signed DTEs into envelopes and dispatches processing.';

    /**
     * Execute the console command.
     */
    public function handle(PackDtesService $service): int
    {
        $count = $service->pack();

        $this->info($count === 0 ? 'No signed DTEs available to pack.' : "Packed {$count} envelope(s).");

        return self::SUCCESS;
    }
}
