<?php

namespace Laragear\Dte\Actions\Cuadratura\Pipes;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Carbon;
use Laragear\Dte\Actions\Cuadratura\CuadraturaContext;
use Laragear\Dte\Data\RcvRecord;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\InboundDteStatus;
use Laragear\Dte\Enums\RcvType;
use Laragear\Dte\Events\DteAltered;
use Laragear\Dte\Events\DteUnregistered;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiInboundDocument;

class ReconcileRcvStream
{
    /**
     * Create a new Reconcile RCV Stream instance.
     */
    public function __construct(
        protected Dispatcher $event,
    ) {
        //
    }

    /**
     * Hande the incoming Cuadratura.
     *
     * @param  Closure(CuadraturaContext): CuadraturaContext  $next
     */
    public function handle(CuadraturaContext $context, Closure $next): CuadraturaContext
    {
        $type = $context->parsingContext->type;
        $oldestIssuedOn = null;

        foreach ($context->parsingContext->records as $record) {
            $oldestIssuedOn = $this->minIssuedOn($oldestIssuedOn, $record);

            if ($type === RcvType::Purchases) {
                $this->reconcileInbound($record, $context);
            } else {
                $this->reconcileOutbound($record, $context);
            }
        }

        // An explicit period always wins; otherwise the export is the month of
        // its oldest record. With no records there is no period to speak of.
        $context->period ??= $oldestIssuedOn?->format('Y-m');

        return $next($context);
    }

    /**
     * Returns the oldest issued-on date seen so far.
     */
    protected function minIssuedOn(?Carbon $oldest, RcvRecord $record): ?Carbon
    {
        $issuedOn = $record->issuedOn;

        if ($issuedOn === null) {
            return $oldest;
        }

        if ($oldest === null) {
            return $issuedOn;
        }

        return $issuedOn->isBefore($oldest) ? $issuedOn : $oldest;
    }

    /**
     * Reconciles an RCV purchase record against the local inbound documents.
     */
    protected function reconcileInbound(RcvRecord $record, CuadraturaContext $context): void
    {
        $model = SiiInboundDocument::query()
            ->where('issuer_num', $record->issuer->num)
            ->where('receiver_num', $context->parsingContext->companyRut->num)
            ->where('document_type', $record->documentType->value)
            ->where('folio', $record->folio)
            ->first();

        if (! $model) {
            $this->instantiatePhantom($record, $context);

            return;
        }

        // The RCV "Fecha Acuse" is SII-side acuse knowledge, not our commercial
        // acceptance decision, so a match only counts and never writes states.
        if ($model->amount_total !== $record->amountTotal) {
            $this->event->dispatch(new DteAltered($model, $record));
            $context->metrics['discrepancies']++;
        } else {
            $context->matchedLocalIds[] = $model->id;
            $context->metrics['matched']++;
        }
    }

    /**
     * Reconciles an RCV sale record against the locally issued documents.
     */
    protected function reconcileOutbound(RcvRecord $record, CuadraturaContext $context): void
    {
        $model = SiiDte::query()
            ->where('issuer_num', $context->parsingContext->companyRut->num)
            ->where('receiver_num', $record->receiver->num)
            ->where('document_type', $record->documentType->value)
            ->where('folio', $record->folio)
            ->first();

        if (! $model) {
            $this->event->dispatch(new DteUnregistered($record));

            $context->metrics['phantoms']++;

            return;
        }

        if ($model->amount_total !== $record->amountTotal) {
            $this->dispatchAltered($model, $record, $context);

            return;
        }

        match ($model->status) {
            // Only a document already sent to the SII may be confirmed accepted.
            DteStatus::Sent => $this->promoteOutbound($model, $context),
            // Idempotent re-sync of a document confirmed on a previous run.
            DteStatus::Accepted => $this->countMatched($model, $context),
            // A never-sent document appearing on the RCV is a folio collision.
            default => $this->dispatchAltered($model, $record, $context),
        };
    }

    /**
     * Promotes a sent document to accepted and counts it as matched.
     */
    protected function promoteOutbound(SiiDte $model, CuadraturaContext $context): void
    {
        $model->status = DteStatus::Accepted;
        $model->save();

        $this->countMatched($model, $context);
    }

    /**
     * Counts a document as matched against the RCV stream.
     */
    protected function countMatched(SiiDte $model, CuadraturaContext $context): void
    {
        $context->matchedLocalIds[] = $model->id;
        $context->metrics['matched']++;
    }

    /**
     * Dispatches an amount discrepancy event and counts it.
     */
    protected function dispatchAltered(SiiDte|SiiInboundDocument $model, RcvRecord $record, CuadraturaContext $context): void
    {
        $this->event->dispatch(new DteAltered($model, $record));
        $context->metrics['discrepancies']++;
    }

    /**
     * Spawns a Phantom inbound document for an RCV purchase missing locally.
     */
    protected function instantiatePhantom(RcvRecord $record, CuadraturaContext $context): void
    {
        // The Reclamo WS only operates on 33/34/43; a phantom of any other type
        // could never be rejected or answered, so it is only counted.
        if ($record->documentType->isNotClaimable()) {
            $context->metrics['skipped']++;

            return;
        }

        // issued_on is NOT NULL: without any known date the phantom cannot be
        // bounded to a period, so only the metric is recorded.
        $issuedOn = $record->issuedOn ?? $record->receivedOn;

        if ($issuedOn === null) {
            $context->metrics['skipped']++;

            return;
        }

        // Idempotent against unique index: re-syncing the same export, or a
        // document whose XML already arrived, must not duplicate or regress it.
        SiiInboundDocument::query()->firstOrCreate([
            'issuer_rut' => $record->issuer,
            'receiver_rut' => $record->receiver,
            'document_type' => $record->documentType,
            'folio' => $record->folio,
        ], [
            'amount_total' => $record->amountTotal,
            'issued_on' => $issuedOn,
            'received_at' => $record->receivedOn,
            'status' => InboundDteStatus::PhantomPending,
        ]);

        $context->metrics['phantoms']++;
    }
}
