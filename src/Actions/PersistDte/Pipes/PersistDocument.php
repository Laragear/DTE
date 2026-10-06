<?php

namespace Laragear\Dte\Actions\PersistDte\Pipes;

use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Laragear\Dte\Actions\PersistDte\DteData;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\ReferenceType;
use Laragear\Dte\Models\SiiDte;
use Laragear\Rut\Rut;
use LogicException;
use Psr\Log\LoggerInterface;
use Throwable;

use function array_unique;

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

        $payload = $dte->payload()->create($data->payloadBlocks);

        $dte->setRelation('payload', $payload);

        $this->persistReferences($dte, $data->payloadBlocks['references']['items'] ?? []);

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
            'failure' => null,
            'acknowledged_at' => null,
            'accepted_at' => null,
            'rejected_at' => null,
        ]))->save();

        $payload = $dte->payload()->updateOrCreate([], $data->payloadBlocks + [
            'xml' => null,
            'sii_response' => null,
        ]);

        $dte->setRelation('payload', $payload);

        $dte->references()->delete();

        $this->persistReferences($dte, Arr::get($data->payloadBlocks, 'references.items', []));

        return $dte;
    }

    /**
     * Persist reference records for the DTE.
     *
     * @param  list<array<string, mixed>>  $references
     */
    protected function persistReferences(SiiDte $dte, array $references): void
    {
        // The target documents are resolved in one query instead of one per
        // reference, so the persist transaction doesn't grow with each item.
        $targets = $this->targetDteIds($dte->issuer_rut, $references);

        $rows = [];

        foreach ($references as $reference) {
            $documentType = DteType::tryFrom((int) $reference['document_type'])
                ?? ReferenceType::tryFrom((string) $reference['document_type']);

            $rows[] = [
                'target_dte_id' => $this->targetDteId($documentType, $reference, $targets),
                'document_type' => $documentType instanceof BackedEnum
                    ? (string) $documentType->value
                    : $reference['document_type'],
                'folio' => $reference['folio'],
                'date' => $reference['date'],
                'reason' => $reference['reason'],
                'reference_code' => $reference['reference_code'],
            ];
        }

        $dte->references()->createMany($rows);
    }

    /**
     * Resolve the local documents the references point to, keyed by type and folio.
     *
     * @param  list<array<string, mixed>>  $references
     * @return array<string, int>
     */
    protected function targetDteIds(Rut $issuerRut, array $references): array
    {
        $folios = [];

        foreach ($references as $reference) {
            if (DteType::tryFrom((int) $reference['document_type']) === null || $reference['folio'] === null) {
                continue;
            }

            $folios[] = (int) $reference['folio'];
        }

        if ($folios === []) {
            return [];
        }

        $targets = [];

        foreach ($this->retrieveDtes($issuerRut, $folios) as $document) {
            $targets[static::targetKey($document->document_type->value, $document->folio)] = $document->getKey();
        }

        return $targets;
    }

    /**
     * Retrieve the documents based on the issuer and folios.
     *
     * @return EloquentCollection<int, SiiDte>
     */
    protected function retrieveDtes(Rut $issuer, array $folios): EloquentCollection
    {
        return SiiDte::query()
            ->where('issuer_num', $issuer->num)
            ->whereIn('folio', array_unique($folios))
            ->get(['id', 'document_type', 'folio']);
    }

    /**
     * Target document id for the reference, or null when unresolvable.
     *
     * @param  array<string, mixed>  $reference
     * @param  array<string, int>  $targets
     */
    protected function targetDteId(DteType|ReferenceType|null $documentType, array $reference, array $targets): ?int
    {
        if (! $documentType instanceof DteType || $reference['folio'] === null) {
            return null;
        }

        return $targets[static::targetKey((int) $documentType->value, (int) $reference['folio'])] ?? null;
    }

    /**
     * Map key joining the target document type and folio.
     */
    protected static function targetKey(int $documentType, int $folio): string
    {
        return "{$documentType}|{$folio}";
    }
}
