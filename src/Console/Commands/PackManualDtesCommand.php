<?php

namespace Laragear\Dte\Console\Commands;

use Illuminate\Console\Command;
use Laragear\Dte\Services\PackDtesService;

class PackManualDtesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dte:pack-manual {ids* : DTE IDs to pack} {--sync : Process the envelopes immediately instead of queueing} {--retry-failed : Re-send failed DTEs whose folio may have been consumed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Packs the given DTEs into envelopes, bypassing batch thresholds.';

    /**
     * Execute the console command.
     */
    public function handle(PackDtesService $service): int
    {
        $envelopes = $service->packManual(
            collect($this->argument('ids'))->map(static fn($id): int => (int) $id)->all(),
            $this->option('sync'),
            $this->option('retry-failed'),
        );

        if ($envelopes->isEmpty()) {
            $this->info('No DTEs to pack.');

            return self::SUCCESS;
        }

        $this->info("Packed {$envelopes->count()} envelope(s): ".$envelopes->map->getKey()->implode(', '));

        return self::SUCCESS;
    }
}
