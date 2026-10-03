<?php

namespace Laragear\Dte\Actions\Cuadratura\Pipes;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
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

        foreach ($context->parsingContext->records as $record) {
            if ($type === RcvType::Purchases) {
                $this->reconcileInbound($record, $context);
            } else {
                $this->reconcileOutbound($record, $context);
            }
        }

        return $next($context);
    }

    /**
     * Evaluates mappings corresponding directly internally to Inbound bounds safely.
     */
    protected function reconcileInbound(RcvRecord $record, CuadraturaContext $context): void
    {
        $model = SiiInboundDocument::query()
            ->where('issuer_num', $record->issuer->num)
            ->where('issuer_vd', $record->issuer->vd)
            ->where('document_type', $record->documentType->value)
            ->where('folio', $record->folio)
            ->first();

        if (!$model) {
            $this->instantiatePhantom($record, $context);

            return;
        }

        if ($model->amount_total !== $record->amountTotal) {
            $this->event->dispatch(new DteAltered($model, $record));
            $context->metrics['discrepancies']++;
        } else {
            $this->updateInboundSafely($model, $record);
            $context->matchedLocalIds[] = $model->id;
            $context->metrics['matched']++;
        }
    }

    /**
     * Evaluates mappings corresponding Outbound constraints natively accurately.
     */
    protected function reconcileOutbound(RcvRecord $record, CuadraturaContext $context): void
    {
        $model = SiiDte::query()
            ->where('receiver_num', $record->receiver->num)
            ->where('receiver_vd', $record->receiver->vd)
            ->where('document_type', $record->documentType->value)
            ->where('folio', $record->folio)
            ->first();

        if (!$model) {
            $this->event->dispatch(new DteUnregistered($record));

            $context->metrics['phantoms']++;

            return;
        }

        if ($model->amount_total !== $record->amountTotal) {
            $this->event->dispatch(new DteAltered($model, $record));
            $context->metrics['discrepancies']++;
        } else {
            $this->updateOutboundSafely($model, $record);
            $context->matchedLocalIds[] = $model->id;
            $context->metrics['matched']++;
        }
    }

    /**
     * Spawns Phantom representations strictly natively cleanly bounded.
     */
    protected function instantiatePhantom(RcvRecord $record, CuadraturaContext $context): void
    {
        SiiInboundDocument::query()->create([
            'issuer_rut' => $record->issuer,
            'receiver_rut' => $record->receiver,
            'document_type' => $record->documentType,
            'folio' => $record->folio,
            'amount_total' => $record->amountTotal,
            'issued_on' => $record->issuedOn,
            'status' => InboundDteStatus::PhantomPending,
        ]);

        $context->metrics['phantoms']++;
    }

    /**
     * Updates the inbound record with the RCV acceptance.
     */
    protected function updateInboundSafely(SiiInboundDocument $model, RcvRecord $record): void
    {
        if ($record->acknowledgedAt !== null) {
            $model->claim_status = InboundDteStatus::CommercialAccepted->value;
            /** @phpstan-ignore-next-line */
            $model->claimed_at = $record->acknowledgedAt;
        }

        $model->save();
    }

    /**
     * Updates mapped SiiDte securely tracking active bounds cleanly.
     */
    protected function updateOutboundSafely(SiiDte $model, RcvRecord $record): void
    {
        if ($model->status->isNotTerminalState()) {
            $model->status = DteStatus::Accepted;
        }

        if ($record->acknowledgedAt !== null) {
            $model->acknowledged_at = $record->acknowledgedAt;
        }

        $model->save();
    }
}
