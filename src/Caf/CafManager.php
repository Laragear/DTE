<?php

namespace Laragear\Dte\Caf;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\DateFactory;
use InvalidArgumentException;
use Laragear\Dte\Caf\Exceptions\CafNotFoundException;
use Laragear\Dte\Caf\Exceptions\DepletionException;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Events\CafDepleted;
use Laragear\Dte\Events\CafLoaded;
use Laragear\Dte\Models\SiiCaf;
use Laragear\Rut\Rut;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SplFileInfo;
use Throwable;
use function implode;
use function is_numeric;

class CafManager
{
    /**
     * Maximum number of CAFs to try in a single allocation before giving up.
     */
    public const int MAX_ALLOCATE_ATTEMPTS = 5;

    /**
     * Create a Caf Manager instance.
     */
    public function __construct(
        protected Dispatcher $event,
        protected DateFactory $date,
        protected CafParser $parser,
        protected Filesystem $files,
        protected LoggerInterface $log,
    ) {
        //
    }

    /**
     * Parse and persist an SII CAF authorization.
     */
    public function store(string $xml): SiiCaf
    {
        $data = $this->parser->parse($xml);

        $caf = SiiCaf::create([
            'rut' => $data['issuer_rut'],
            ...Arr::only($data, [
                'document_type',
                'folio_from',
                'folio_to',
                'folio_current',
                'authorized_on',
                'xml',
            ]),
        ]);

        $this->event->dispatch(new CafLoaded($caf));

        return $caf;
    }

    /**
     * Parse and persist an SII CAF authorization from a file path or Uploaded File.
     */
    public function storeFile(string|SplFileInfo $file): SiiCaf
    {
        $path = $file instanceof SplFileInfo ? $file->getRealPath() : $file;

        try {
            $contents = $this->files->get($path);
        } catch (FileNotFoundException $e) {
            throw new RuntimeException("Unable to read the CAF file at [$path].", previous: $e);
        }

        return $this->store($contents);
    }

    /**
     * Allocate a folio and execute work inside the allocation transaction.
     *
     * @template TReturn
     *
     * @param  Closure(SiiCaf, int): TReturn  $callback
     * @return TReturn
     */
    public function allocate(Rut $issuer, DteType $documentType, Closure $callback): mixed
    {
        // This is the sole transaction of the folio-allocation flow. The Compile
        // pipeline calls this same entry, so allocation is never nested.
        return SiiCaf::query()
            ->getConnection()
            // Each iteration consumes all remaining folios of one CAF, so more than these
            // many consecutive depleted CAFs indicates a data integrity problem. We need
            // to use a transaction to retry, because "save()" can skip that model save.
            ->transaction(fn(): mixed => $this->attemptAllocate($issuer, $documentType, $callback),
                static::MAX_ALLOCATE_ATTEMPTS);
    }

    /**
     * Attempt the allocation once, logging the failure point before rollback.
     *
     * @template TReturn
     *
     * @param  Closure(SiiCaf, int): TReturn  $callback
     * @return TReturn
     */
    protected function attemptAllocate(Rut $issuer, DteType $documentType, Closure $callback): mixed
    {
        try {
            return $this->doAllocate($issuer, $documentType, $callback);
        } catch (Throwable $e) {
            $this->log->error('CAF folio allocation failed, rolling back to the pre-allocation state.', [
                'flow' => 'folio-allocation',
                'issuer' => $issuer->formatRaw(),
                'document_type' => $documentType->value,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Allocate a folio without opening a transaction.
     *
     * @template TReturn
     *
     * @param  Closure(SiiCaf, int): TReturn  $callback
     * @return TReturn
     */
    protected function doAllocate(Rut $issuer, DteType $documentType, Closure $callback): mixed
    {
        $caf = $this->availableCaf($issuer, $documentType);

        $folio = $caf->folios->next();

        // If a valid folio was successfully pulled, execute the callback.
        // Otherwise, the loop continues to look for the next valid CAF.
        if ($folio !== null) {
            $caf->save();

            return $callback($caf, $folio);
        }

        $caf->markAsDepleted();

        throw new DepletionException(
            "Unable to allocate a folio issuer [$issuer] and document type [$documentType->value]."
        );
    }

    /**
     * Find and lock the next CAF with an available folio.
     */
    protected function availableCaf(Rut $issuer, DteType $documentType): SiiCaf
    {
        return SiiCaf::query()
            ->whereRut($issuer)
            ->whereDocumentType($documentType)
            ->whereColumn('folio_current', '<=', 'folio_to')
            ->where(function (Builder $query): void {
                $query->whereNull('expires_on')
                    ->orWhereDate('expires_on', '>=', $this->date->today('America/Santiago'));
            })
            ->whereNotDepleted()
            ->orderBy('folio_from')
            ->lockForUpdate()
            ->firstOr(function () use ($issuer, $documentType): never {
                $this->event->dispatch(new CafDepleted($issuer, $documentType));

                throw new DepletionException(
                    "No CAF folios available for the issuer [$issuer] and document type [$documentType->value].",
                );
            });
    }

    /**
     * Find the CAF covering the given folios and annul them.
     */
    public function annulFolios(Rut|string $issuer, DteType|int $documentType, string $reason, array $folios): SiiCaf
    {
        if (is_numeric($documentType)) {
            $documentType = DteType::from($documentType);
        }

        $issuer = Rut::parse($issuer);
        $folios = Folio::normalize($folios);

        if ($folios === []) {
            throw new InvalidArgumentException('No folios given to annul.');
        }

        return SiiCaf::query()
            ->whereRut($issuer)
            ->whereDocumentType($documentType)
            ->where('folio_from', '<=', min($folios))
            ->where('folio_to', '>=', max($folios))
            ->firstOr(static function () use ($issuer, $documentType, $folios): never {
                throw new CafNotFoundException(
                    "No CAF covers the issuer [$issuer] and document type [$documentType->value] for the folios [".
                    implode(', ', $folios)
                    .'].',
                );
            })
            ->annulFolios($folios, $reason);
    }
}
