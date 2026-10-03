<?php

namespace Laragear\Dte\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\DateFactory;
use Laragear\Dte\Events\CafExpiring;
use Laragear\Dte\Events\CafNearDepleted;
use Laragear\Dte\Models\SiiCaf;

class CheckNearDepletedCafsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dte:check-cafs
                            {--threshold= : The percentage threshold to consider a CAF as near depleted (defaults to config)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for active CAFs nearing folio depletion or expiration and dispatch events';

    /**
     * Execute the console command.
     */
    public function handle(Dispatcher $event, Repository $config, DateFactory $date): int
    {
        $threshold = $this->getThreshold($config);

        $this->queryActiveCafs($date)
            ->chunk(100, static function ($cafs) use ($threshold, $event, $date): void {
                foreach ($cafs as $caf) {
                    static::checkDepletion($caf, $threshold, $event);
                    static::checkExpiration($caf, $date, $event);
                }
            });

        $this->info('CAF depletion and expiration check completed.');

        return self::SUCCESS;
    }

    /**
     * Resolve the depletion threshold from the option or configuration.
     */
    protected function getThreshold(Repository $config): float
    {
        return (float) ($this->option('threshold') ?? $config->get('dte.caf.depletion_threshold', 10));
    }

    /**
     * Build the query for active CAFs that are not expired.
     */
    protected function queryActiveCafs(DateFactory $date): Builder
    {
        return SiiCaf::query()
            ->where(static function ($query) use ($date): void {
                $query->whereNull('expires_on')->orWhere('expires_on', '>', $date->now());
            });
    }

    /**
     * Check if a CAF is near folio depletion and dispatch an event if so.
     */
    protected static function checkDepletion(SiiCaf $caf, float $threshold, Dispatcher $event): void
    {
        $total = $caf->folio_to - $caf->folio_from + 1;
        $remaining = $caf->folios->remaining();
        $percentage = ($remaining / $total) * 100;

        if ($percentage <= $threshold) {
            $event->dispatch(new CafNearDepleted($caf, $remaining, $percentage));
        }
    }

    /**
     * Check if a CAF is expiring within 7 days and dispatch an event if so.
     */
    protected static function checkExpiration(SiiCaf $caf, DateFactory $date, Dispatcher $event): void
    {
        if ($caf->expires_on) {
            $daysLeft = (int) $date->now()->startOfDay()->diffInDays($caf->expires_on->startOfDay());

            if ($daysLeft >= 0 && $daysLeft <= 7) {
                $event->dispatch(new CafExpiring($caf, $daysLeft));
            }
        }
    }
}
