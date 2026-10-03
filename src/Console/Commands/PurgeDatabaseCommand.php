<?php

namespace Laragear\Dte\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Laragear\Dte\Environment\EnvironmentResolver;
use Laragear\Dte\Models\SiiAecCession;
use Laragear\Dte\Models\SiiCaf;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Models\SiiDteEnvelopePayload;
use Laragear\Dte\Models\SiiDtePayload;
use Laragear\Dte\Models\SiiInboundDocument;
use Laragear\Dte\Models\SiiInboundDocumentPayload;
use Laragear\Dte\Models\SiiInterchangeLog;
use Throwable;

class PurgeDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dte:purge
                            {--force : Purge without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Truncate all DTE-related database records (documents, envelopes, interchanges, CAFs)';

    /**
     * Execute the console command.
     *
     * @throws Throwable
     */
    public function handle(EnvironmentResolver $environment, SchemaBuilder $schema): int
    {
        if ($environment->resolve()->isNotAllowedOnCertification()) {
            $this->fail("Purging is not permitted in the [{$environment->resolve()->value}] environment.");
        }

        if ($this->shouldNotContinue()) {
            return self::SUCCESS;
        }

        $schema->disableForeignKeyConstraints();

        try {
            SiiAecCession::truncate();
            SiiInboundDocumentPayload::truncate();
            SiiInboundDocument::truncate();
            SiiInterchangeLog::truncate();
            SiiDteEnvelopePayload::truncate();
            SiiDteEnvelope::truncate();
            SiiDtePayload::truncate();
            SiiDte::truncate();
            SiiCaf::truncate();
        } finally {
            $schema->enableForeignKeyConstraints();
        }

        $this->info('All DTE records purged.');

        return self::SUCCESS;
    }

    /**
     * Check if this should be forced or the user explicitly answers to continue.
     */
    protected function shouldNotContinue(): bool
    {
        return !$this->option('force')
            && !$this->confirm('This wipes all documents, envelopes, interchanges, and CAFs. Continue?');
    }
}
