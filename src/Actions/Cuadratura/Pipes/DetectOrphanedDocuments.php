<?php

namespace Laragear\Dte\Actions\Cuadratura\Pipes;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Carbon;
use Illuminate\Support\DateFactory;
use Laragear\Dte\Actions\Cuadratura\CuadraturaContext;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\InboundDteStatus;
use Laragear\Dte\Enums\RcvType;
use Laragear\Dte\Events\DteOrphaned;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiInboundDocument;

/**
 * Detects locally tracked documents absent from the synced RCV period export.
 *
 * Read-only: a document absent from an export is a discrepancy to investigate,
 * never proof of a rejection or forgery, so no statuses are written here.
 */
class DetectOrphanedDocuments
{
    /**
     * Create a new Detect Orphaned Documents instance.
     */
    public function __construct(
        protected Dispatcher $event,
        protected DateFactory $date,
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
        $this->detectOrphans($context);

        return $next($context);
    }

    /**
     * Reports unmatched documents inside the synced period.
     */
    protected function detectOrphans(CuadraturaContext $context): void
    {
        $period = $context->period;

        // Without a period there is no scope: a partial or empty export must
        // never orphan anything.
        if ($period === null) {
            return;
        }

        $bounds = $this->periodBounds($period);

        // The SII may incorporate a document into the RCV until day 10 of the
        // following tax period; before that, absence proves nothing.
        if ($this->date->now()->lessThan($bounds['gate'])) {
            return;
        }

        if ($context->parsingContext->type === RcvType::Purchases) {
            $this->detectInboundOrphans($context, $bounds);

            return;
        }

        $this->detectOutboundOrphans($context, $bounds);
    }

    /**
     * Reports sent sales documents missing from the RCV period.
     *
     * @param  array{start: Carbon, end: Carbon, gate: Carbon}  $bounds
     */
    protected function detectOutboundOrphans(CuadraturaContext $context, array $bounds): void
    {
        $documents = SiiDte::query()
            ->where('issuer_num', $context->parsingContext->companyRut->num)
            ->where('issuer_vd', $context->parsingContext->companyRut->vd)
            ->where('status', DteStatus::Sent->value)
            ->whereBetween('issued_on', [$bounds['start'], $bounds['end']])
            ->whereNotIn('id', $context->matchedLocalIds)
            ->get();

        foreach ($documents as $document) {
            $this->event->dispatch(new DteOrphaned($document));

            $context->metrics['orphans']++;
        }
    }

    /**
     * Reports received purchase documents missing from the RCV period.
     *
     * @param  array{start: Carbon, end: Carbon, gate: Carbon}  $bounds
     */
    protected function detectInboundOrphans(CuadraturaContext $context, array $bounds): void
    {
        // ponytail: COALESCE anchor pushes month-edge documents (issued 31st,
        // received next month) into the next period's sync, which is correct.
        $documents = SiiInboundDocument::query()
            ->where('receiver_num', $context->parsingContext->companyRut->num)
            ->where('receiver_vd', $context->parsingContext->companyRut->vd)
            ->whereNotIn('status', [InboundDteStatus::PhantomPending->value, InboundDteStatus::Forged->value])
            ->whereBetween(new Expression('coalesce(received_at, issued_on)'), [$bounds['start'], $bounds['end']])
            ->whereNotIn('id', $context->matchedLocalIds)
            ->get();

        foreach ($documents as $document) {
            $this->event->dispatch(new DteOrphaned($document));

            $context->metrics['orphans']++;
        }
    }

    /**
     * Returns the period month bounds and the day-10 detection gate.
     *
     * @return array{start: Carbon, end: Carbon, gate: Carbon}
     */
    protected function periodBounds(string $period): array
    {
        // Anchored on day 1: "Y-m" parses carry the current day-of-month.
        $start = Carbon::parse("{$period}-01")->startOfMonth();

        return [
            'start' => $start,
            'end' => $start->clone()->endOfMonth(),
            'gate' => $start->clone()->addMonthNoOverflow()->day(10)->endOfDay(),
        ];
    }
}
