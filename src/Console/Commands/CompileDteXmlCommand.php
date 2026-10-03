<?php

namespace Laragear\Dte\Console\Commands;

use Illuminate\Console\Command;
use Laragear\Dte\Actions\CompileDte\Compile;
use Laragear\Dte\Models\SiiDte;

class CompileDteXmlCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dte:compile {dte_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compile a DTE XML from its model';

    /**
     * Execute the console command.
     */
    public function handle(Compile $compile): int
    {
        $dte = $this->argument('dte_id');

        // If the DTE was passed as a model, we will keep it, otherwise we will find it.
        if (!$dte instanceof SiiDte) {
            $dte = SiiDte::findOrFail($dte);
        }

        $compile->forDte($dte);

        $this->info("DTE [{$dte->getKey()}] compiled successfully.");

        return self::SUCCESS;
    }
}
