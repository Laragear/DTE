<?php

namespace Laragear\Dte\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\DateFactory;
use Laragear\Dte\Enums\InboundDteStatus;
use Laragear\Dte\Models\SiiInboundDocument;
use Laragear\Dte\Services\DteClaimService;
use Psr\Log\LoggerInterface;
use Throwable;

class RejectExpiringPhantomInvoicesCommand extends Command
{
    /**
     * Days the SII allows registering claims since reception.
     *
     * After 8 days the Reclamo Webservice rejects any event, so a phantom past
     * the ceiling is skipped instead of provoking a guaranteed rejection.
     */
    protected const int LEGAL_CLAIM_DAYS = 8;

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
    public function handle(
        LoggerInterface $log,
        DateFactory $date,
        DteClaimService $claims,
    ): int {
        $documents = $this->queryExpiringPhantomInvoices($this->getThreshold($date));

        if ($documents->isEmpty()) {
            $this->info('No expiring phantom invoices found.');

            return self::SUCCESS;
        }

        [$rejected, $failed] = $this->rejectDocuments($documents, $claims, $log, $date);

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
            // The clock anchors on the SII reception date when known, falling back to
            // the phantom creation date.
            ->whereRaw('coalesce(received_at, created_at) <= ?', [$threshold])
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
        DteClaimService $claims,
        LoggerInterface $log,
        DateFactory $date,
    ): array {
        $rejected = 0;
        $failed = 0;

        foreach ($documents as $document) {
            if ($this->isPastLegalClaimCeiling($document, $date)) {
                $log->warning('Skipping phantom invoice rejection: past the 8-day legal claim window.', [
                    'flow' => 'phantom-reject',
                    'document_id' => $document->id,
                ]);

                continue;
            }

            if ($this->rejectDocument($document, $claims, $log)) {
                $rejected++;
            } else {
                $failed++;
            }
        }

        return [$rejected, $failed];
    }

    /**
     * Check if the document reception is past the SII claim registration window.
     */
    protected function isPastLegalClaimCeiling(SiiInboundDocument $document, DateFactory $date): bool
    {
        $reception = $document->received_at ?? $document->created_at;

        return $date->now()->greaterThan($reception->copy()->addDays(self::LEGAL_CLAIM_DAYS));
    }

    /**
     * Attempt to reject a single phantom invoice through the claim service.
     */
    protected function rejectDocument(
        SiiInboundDocument $document,
        DteClaimService $claims,
        LoggerInterface $log,
    ): bool {
        // The claim service races-guards with a conditional update before
        // touching the SII webservice: a manual claim wins and aborts this one.
        try {
            $claims->reject($document, 'Rechazo automático de factura fantasma (Sin recepción).');

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
