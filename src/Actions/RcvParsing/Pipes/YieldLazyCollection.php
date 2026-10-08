<?php

namespace Laragear\Dte\Actions\RcvParsing\Pipes;

use Closure;
use Generator;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;
use Laragear\Dte\Actions\RcvParsing\ParsingContext;
use Laragear\Dte\Data\RcvRecord;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\RcvType;
use Laragear\Dte\Proxies\StreamProxy;
use Laragear\Rut\Rut;

class YieldLazyCollection
{
    /**
     * Create a new Yield Lazy Collection instance.
     */
    public function __construct(
        protected StreamProxy $stream,
    ) {
        //
    }

    /**
     * Handle the incoming RCV Parsing.
     *
     * @param  Closure(ParsingContext):ParsingContext  $next
     */
    public function handle(ParsingContext $context, Closure $next): ParsingContext
    {
        if (! $this->extractHeaders($context)) {
            $context->records = LazyCollection::empty();

            return $next($context);
        }

        $context->records = $this->buildLazyCollection($context);

        return $next($context);
    }

    /**
     * Extract and normalize headers from the stream cleanly.
     */
    protected function extractHeaders(ParsingContext $context): bool
    {
        $headerLine = $this->stream->fgetcsv($context->stream, null, ';');

        if ($headerLine === false || $headerLine === null) {
            return false;
        }

        $context->headerMap = array_map(static function (string $val): string {
            return trim($val, "\xEF\xBB\xBF\xC3\xAF\xC2 \t\n\r\0\x0B");
        }, $headerLine);

        return true;
    }

    /**
     * Architect the main standard parsing LazyCollection bounds.
     */
    protected function buildLazyCollection(ParsingContext $context): LazyCollection
    {
        $stream = $context->stream;
        $proxy = $this->stream;

        return LazyCollection::make(function () use ($stream, $proxy): Generator {
            yield from $this->yieldCsvRows($stream, $proxy);
        })
            ->map(function (array $row) use ($context): array {
                return $this->mapRowToHeaders($row, $context->headerMap);
            })
            ->filter(function (array $mapped): bool {
                return $this->isValidRow($mapped);
            })
            ->map(function (array $mapped) use ($context): RcvRecord {
                return $this->mapToRcvRecord($mapped, $context);
            });
    }

    /**
     * Loop and yield stream strings safely discarding truncated eof elements.
     */
    protected function yieldCsvRows(mixed $stream, StreamProxy $proxy): Generator
    {
        try {
            while (($row = $proxy->fgetcsv($stream, null, ';')) !== false) {
                if ($row === null || count($row) < 5) {
                    continue;
                }
                yield $row;
            }
        } finally {
            $proxy->fclose($stream);
        }
    }

    /**
     * Tie the CSV column parameters uniquely to standard String keys natively.
     */
    protected function mapRowToHeaders(array $row, array $headerMap): array
    {
        $mapped = [];

        foreach ($row as $index => $value) {
            $header = $headerMap[$index] ?? null;

            if ($header) {
                $mapped[$header] = trim($value);
            }
        }

        return $mapped;
    }

    /**
     * Filter row mappings lacking crucial identification elements or unmapped document types.
     */
    protected function isValidRow(array $mapped): bool
    {
        return isset($mapped['Tipo Doc'])
            && is_numeric($mapped['Tipo Doc'])
            && DteType::tryFrom((int) $mapped['Tipo Doc']) !== null;
    }

    /**
     * Generate the Data Transfer Object.
     */
    protected function mapToRcvRecord(array $mapped, ParsingContext $context): RcvRecord
    {
        return new RcvRecord(
            $this->resolveIssuer($mapped, $context),
            $this->resolveReceiver($mapped, $context),
            DteType::from((int) $mapped['Tipo Doc']),
            (int) ($mapped['Folio'] ?? 0),
            $this->parseAmount($mapped),
            $mapped['Tipo Compra'] ?? $mapped['Tipo Venta'] ?? 'Del Giro',
            $this->parseDate($mapped, 'Fecha Docto'),
            $this->parseDate($mapped, 'Fecha Recepcion'),
            $this->parseDate($mapped, 'Fecha Acuse'),
        );
    }

    /**
     * Resolves the Issuer Rut structurally.
     */
    protected function resolveIssuer(array $mapped, ParsingContext $context): Rut
    {
        $raw = $context->type === RcvType::Purchases
            ? $mapped['RUT Proveedor'] ?? ''
            : $context->companyRut->formatRaw();

        return Rut::parse($raw);
    }

    /**
     * Resolves the Receiver Rut structurally.
     */
    protected function resolveReceiver(array $mapped, ParsingContext $context): Rut
    {
        $raw = $context->type === RcvType::Purchases ? $context->companyRut->formatRaw() : $mapped['Rut cliente'] ?? '';

        return Rut::parse($raw);
    }

    /**
     * Extracts date boundaries carefully evaluating formats.
     */
    protected function parseDate(array $mapped, string $key): ?Carbon
    {
        // The SII portal may emit acuse management letters (A, C, P, G) inside date
        // columns instead of dates; anything not matching the export format is null.
        if (isset($mapped[$key]) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $mapped[$key]) === 1) {
            return Carbon::createFromFormat('d-m-Y', $mapped[$key])->startOfDay();
        }

        return null;
    }

    /**
     * Resolves float mismatches stripping native comma strings.
     */
    protected function parseAmount(array $mapped): int
    {
        $rawAmount = $mapped['Monto Total'] ?? $mapped['Monto total'] ?? '0';
        $amountClean = str_replace(',', '.', $rawAmount);

        return (int) round((float) $amountClean);
    }
}
