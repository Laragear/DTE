<?php

namespace Laragear\Dte\Actions\CreateEnvelope\Pipes;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Laragear\Dte\Actions\CreateEnvelope\Assembly;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\DteType;
use Laragear\Rut\Rut;
use LogicException;
use UnexpectedValueException;
use function is_int;

class ValidateEnvelopeDocuments
{
    /**
     * Create a new Validate Envelope Documents instance.
     */
    public function __construct(
        protected Repository $config,
    ) {
        //
    }

    /**
     * Handle the incoming DTE Envelope Assembly.
     *
     * @param  Closure(Assembly): Assembly  $next
     */
    public function handle(Assembly $assembly, Closure $next): Assembly
    {
        $this->validateDocuments($assembly);

        return $next($assembly);
    }

    /**
     * Ensure the envelope holds a valid batch of documents.
     */
    protected function validateDocuments(Assembly $assembly): void
    {
        $assembly->expectedDocuments = $assembly->envelope->dtes()
            ->when($assembly->targetReceiverRut, static function (EloquentBuilder $query, Rut $rut): void {
                $query->where('receiver_num', $rut->num);
            })
            ->count();

        if ($assembly->expectedDocuments < 1) {
            throw new LogicException('The DTE envelope must contain at least one signed document.');
        }

        if ($assembly->expectedDocuments > $this->maximumDocuments($assembly)) {
            throw new LogicException('The DTE envelope exceeds the configured document limit.');
        }

        if ($this->hasInvalidDocuments($assembly)) {
            throw new LogicException('The DTE envelope documents must share its issuer, compiled state, and receipt type.');
        }
    }

    /**
     * Whether any document breaks the envelope homogeneity.
     */
    protected function hasInvalidDocuments(Assembly $assembly): bool
    {
        $envelope = $assembly->envelope;

        return $envelope
            ->dtes()
            ->when($assembly->targetReceiverRut, function (EloquentBuilder $query, Rut $rut) {
                return $query->where('receiver_num', $rut->num);
            })
            ->where(static function (EloquentBuilder $query) use ($envelope): void {
                $query->where(static function (EloquentBuilder $q) use ($envelope): void {
                    $q->where('issuer_num', '!=', $envelope->issuer_rut->num)
                        ->orWhere('issuer_vd', '!=', $envelope->issuer_rut->vd)
                        ->orWhereNotIn('status', DteStatus::compiledValues());
                });

                if ($envelope->isReceipt()) {
                    $query->orWhereNotIn('document_type', [
                        DteType::Receipt->value,
                        DteType::ExemptReceipt->value,
                    ]);
                } else {
                    $query->orWhereIn('document_type', [
                        DteType::Receipt->value,
                        DteType::ExemptReceipt->value,
                    ]);
                }
            })
            ->exists();
    }

    /**
     * Resolve the document limit for the envelope family.
     */
    protected function maximumDocuments(Assembly $assembly): int
    {
        $configKey = $assembly->envelope->isReceipt()
            ? 'dte.envelopes.max.receipts'
            : 'dte.envelopes.max.documents';

        $maximum = $this->config->get($configKey);

        if (!is_int($maximum) || $maximum < 1) {
            throw new UnexpectedValueException('The envelope document limit must be a positive integer.');
        }

        return $maximum;
    }
}
