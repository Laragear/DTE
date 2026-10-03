<?php

namespace Laragear\Dte\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\DateFactory;
use Laragear\Dte\Enums\InboundDteStatus;
use Laragear\Dte\Gateways\ReclamoWebserviceGateway;
use Laragear\Dte\Models\SiiInboundDocument;
use Psr\Log\LoggerInterface;
use Throwable;

class RejectExpiringPhantomInvoicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dte:reject-phantom-invoices
                            {--days=6 : Days threshold before rejecting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reject PhantomPending invoices nearing the automatic acceptance deadline';

    /**
     * Execute the console command.
     */
    public function handle(LoggerInterface $log, DateFactory $date, ReclamoWebserviceGateway $gateway): int
    {
        $documents = $this->queryExpiringPhantomInvoices($this->getThreshold($date));

        if ($documents->isEmpty()) {
            $this->info('No expiring phantom invoices found.');

            return self::SUCCESS;
        }

        [$rejected, $failed] = $this->rejectDocuments($documents, $gateway, $log);

        $this->info("Rejected {$rejected} phantom invoices. Failed: {$failed}.");

        return self::SUCCESS;
    }

    /**
     * Resolve the date threshold for expiring phantom invoices.
     */
    protected function getThreshold(DateFactory $date): Carbon
    {
        return $date->now()->subDays((int) $this->option('days'));
    }

    /**
     * Query PhantomPending invoices older than the threshold.
     *
     * @return Collection<int, SiiInboundDocument>
     */
    protected function queryExpiringPhantomInvoices(Carbon $threshold): Collection
    {
        return SiiInboundDocument::query()
            ->where('status', InboundDteStatus::PhantomPending)
            ->where('created_at', '<=', $threshold)
            ->get();
    }

    /**
     * Attempt to reject all documents, returning [rejected, failed] counts.
     *
     * @param  Collection<int, SiiInboundDocument>  $documents
     * @return array{int, int}
     */
    protected function rejectDocuments(
        Collection $documents,
        ReclamoWebserviceGateway $gateway,
        LoggerInterface $log,
    ): array {
        $rejected = 0;
        $failed = 0;

        foreach ($documents as $document) {
            if ($this->rejectDocument($document, $gateway, $log)) {
                $rejected++;
            } else {
                $failed++;
            }
        }

        return [$rejected, $failed];
    }

    /**
     * Attempt to reject a single phantom invoice via the SII webservice.
     */
    protected function rejectDocument(
        SiiInboundDocument $document,
        ReclamoWebserviceGateway $gateway,
        LoggerInterface $log,
    ): bool {
        // Claims the document with a single conditional UPDATE before touching
        // the gateway: a manual claim racing this command loses here and aborts
        // instead of hitting the SII webservice twice.
        try {
            $claimed = SiiInboundDocument::query()
                ->whereKey($document->getKey())
                ->where('status', InboundDteStatus::PhantomPending)
                ->update(['updated_at' => $document->freshTimestamp()]);

            if ($claimed < 1) {
                $log->warning('Skipping phantom invoice rejection: document already claimed by a concurrent operation.',
                    [
                        'flow' => 'phantom-reject',
                        'document_id' => $document->id,
                    ]);

                return false;
            }

            $gateway->reject($document, 'Rechazo automático de factura fantasma (Sin recepción).');

            $document->status = InboundDteStatus::CommercialRejected;
            $document->save();

            return true;
        } catch (Throwable $e) {
            $log->error('Failed to reject phantom invoice.', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTrace(),
            ]);

            return false;
        }
    }
}
