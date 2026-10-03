<?php

namespace Laragear\Dte\Actions\PersistDte\Pipes;

use BackedEnum;
use Closure;
use Laragear\Dte\Actions\PersistDte\DteData;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\ReferenceType;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteReference;
use LogicException;
use Psr\Log\LoggerInterface;
use Throwable;

class PersistDocument
{
    /**
     * Create a new Persist Document instance.
     */
    public function __construct(public LoggerInterface $logger)
    {
        //
    }

    /**
     * Handle the incoming DTE Persistence.
     *
     * @param  Closure(DteData):DteData  $next
     *
     * @throws Throwable
     */
    public function handle(DteData $data, Closure $next): DteData
    {
        $dte = $data->builder->dte();

        $connection = $data->isUpdate && $dte instanceof SiiDte
            ? $dte->getConnection()
            : SiiDte::query()->getConnection();

        // Single transaction of the "persist" flow. Compilation is dispatched
        // afterwards by the lifecycle service, never nested in this logic.
        try {
            $data->dte = $connection->transaction(function () use ($data): SiiDte {
                return $data->isUpdate ? $this->persistUpdate($data) : $this->persistCreate($data);
            });
        } catch (Throwable $e) {
            $this->logger->error('DTE persistence failed, rolling back to the pre-persist state.', [
                'flow' => 'dte-persist',
                'operation' => $data->isUpdate ? 'update' : 'create',
                'dte_id' => $data->builder->dte()?->getKey(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $next($data);
    }

    /**
     * Creates a new model on the database.
     */
    protected function persistCreate(DteData $data): SiiDte
    {
        $dte = SiiDte::create($data->attributes);

        $payload = $dte->payload()->create(['data' => $data->payloadData]);

        $dte->setRelation('payload', $payload);

        $this->persistReferences($dte, $data->payloadData['references'] ?? []);

        return $dte;
    }

    /**
     * Updates an existing model in the database,
     */
    protected function persistUpdate(DteData $data): SiiDte
    {
        $dte = $data->builder->dte();

        if (! $dte instanceof SiiDte) {
            throw new LogicException('Cannot update a document that has not been hydrated.');
        }

        $dte->forceFill(array_merge($data->attributes, [
            'status' => $dte->status === DteStatus::Draft ? DteStatus::Draft : DteStatus::Pending,
            'repairs' => null,
            'acknowledged_at' => null,
            'accepted_at' => null,
            'rejected_at' => null,
        ]))->save();

        $payload = $dte->payload()->updateOrCreate([], [
            'data' => $data->payloadData,
            'xml' => null,
            'sii_response' => null,
        ]);

        $dte->setRelation('payload', $payload);

        $dte->references()->delete();

        $this->persistReferences($dte, $data->payloadData['references'] ?? []);

        return $dte;
    }

    /**
     * Persist reference records for the DTE.
     *
     * @param  list<array<string, mixed>>  $references
     */
    protected function persistReferences(SiiDte $dte, array $references): void
    {
        $issuerRut = $dte->issuer_rut;

        foreach ($references as $reference) {
            $documentType = DteType::tryFrom((int) $reference['document_type'])
                ?? ReferenceType::tryFrom((string) $reference['document_type']);

            $targetDteId = null;

            if ($documentType instanceof DteType && $reference['folio'] !== null) {
                $targetDteId = SiiDte::where('issuer_num', $issuerRut->num)
                    ->where('issuer_vd', $issuerRut->vd)
                    ->where('document_type', $documentType)
                    ->where('folio', $reference['folio'])
                    ->value('id');
            }

            SiiDteReference::create([
                'sii_dte_id' => $dte->getKey(),
                'target_dte_id' => $targetDteId,
                'document_type' => $documentType instanceof BackedEnum
                    ? (string) $documentType->value
                    : $reference['document_type'],
                'folio' => $reference['folio'],
                'date' => $reference['date'],
                'reason' => $reference['reason'],
                'reference_code' => $reference['reference_code'],
            ]);
        }
    }
}
