<?php

namespace Laragear\Dte\Actions\Cuadratura\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\DateFactory;
use Laragear\Dte\Actions\Cuadratura\CuadraturaContext;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\InboundDteStatus;
use Laragear\Dte\Enums\RcvType;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiInboundDocument;

class DowngradeOrphanedDocuments
{
    /**
     * Create a new Downgrade Orphaned Documents instance.
     */
    public function __construct(
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
        if ($context->parsingContext->type === RcvType::Purchases) {
            $this->downgradeInbounds($context);
        } else {
            $this->downgradeOutbounds($context);
        }

        return $next($context);
    }

    /**
     * Reconciles structural Orphans against mapped Inbounds securely safely.
     */
    protected function downgradeInbounds(CuadraturaContext $context): void
    {
        $threshold = $this->date->now()->subHours(48);

        $query = SiiInboundDocument::query()
            ->where('receiver_num', $context->parsingContext->companyRut->num)
            ->where('receiver_vd', $context->parsingContext->companyRut->vd)
            ->whereNotIn('id', $context->matchedLocalIds)
            ->where('created_at', '<', $threshold)
            ->where('status', '!=', InboundDteStatus::Forged->value);

        $this->downgradeQuery($query, $context, InboundDteStatus::Forged->value);
    }

    /**
     * Downgrades missing Outbound records to rejected forms.
     */
    protected function downgradeOutbounds(CuadraturaContext $context): void
    {
        $threshold = $this->date->now()->subHours(48);

        $query = SiiDte::query()
            ->where('issuer_num', $context->parsingContext->companyRut->num)
            ->where('issuer_vd', $context->parsingContext->companyRut->vd)
            ->whereNotIn('id', $context->matchedLocalIds)
            ->where('created_at', '<', $threshold)
            ->whereIn('status', DteStatus::orphanDowngradableValues());

        $this->downgradeQuery($query, $context, DteStatus::Rejected->value);
    }

    /**
     * Count orphaned records and bulk-update them.
     */
    protected function downgradeQuery(Builder $query, CuadraturaContext $context, string|int $targetStatus): void
    {
        $context->metrics['orphans'] += $query->clone()->count();

        $query->update(['status' => $targetStatus]);
    }
}
