<?php

namespace Laragear\Dte\Data;

use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use Laragear\Dte\Enums\DteType;
use LogicException;
use Throwable;

use function array_is_list;
use function collect;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;

/**
 * Typed view of the raw input that created the DTE XML.
 *
 * Each member is a scalar, a single DTO, a Collection of scalars, or a Collection of DTOs.
 *
 * @property-read DteType|null $document_type
 * @property-read DateTimeImmutable|null $issued_on
 * @property-read IssuerData|null $issuer
 * @property-read ReceiverData|null $receiver
 * @property-read Collection<int, Item> $items
 * @property-read Collection<int, ReferenceData> $references
 * @property-read Collection<int, GlobalModifierData> $global_modifiers
 * @property-read Collection<int, int> $taxes
 * @property-read DocumentTotalsData $totals
 * @property-read PaymentTermData|null $payment
 * @property-read TransportData|null $transport
 * @property-read int|null $ind_mnt_neto
 * @property-read bool $tax_exempt
 * @property-read int|null $exempt_amount_override
 * @property-read int|null $ind_traslado
 * @property-read int|null $tipo_despacho
 */
class DocumentPayload extends Fluent
{
    /**
     * Create a new instance from an array.
     */
    public static function fromArray(array $array): static
    {
        return new static($array);
    }

    /**
     * Create a new instance from a JSON string.
     */
    public static function fromJson(string $json): static
    {
        $data = json_decode($json, true, 10);

        return new static(is_array($data) ? $data : []);
    }

    /**
     * Return the typed value for the given key.
     */
    public function get($key, $default = null): mixed
    {
        $raw = $this->attributes[$key] ?? null;

        return match ($key) {
            'document_type' => $this->parseDocumentType($raw),
            'issued_on' => $this->parseDate($raw),
            'issuer' => is_array($raw) ? IssuerData::fromArray($raw) : null,
            'receiver' => is_array($raw) ? ReceiverData::fromArray($raw) : null,
            'items' => Collection::make($raw ?? [])->map(static fn (array $item): Item => Item::fromArray($item)),
            'references' => Collection::make($raw ?? [])->map(static fn (array $reference
            ): ReferenceData => ReferenceData::fromArray($reference)),
            'global_modifiers' => Collection::make($raw ?? [])->map(static fn (array $modifier
            ): GlobalModifierData => GlobalModifierData::fromArray($modifier)),
            'taxes' => Collection::make($raw ?? [])->mapWithKeys(static fn (
                mixed $amount,
                mixed $code
            ): array => [(int) $code => (int) $amount]),
            'totals' => DocumentTotalsData::fromArray(is_array($raw) ? $raw : []),
            'payment' => is_array($raw) ? PaymentTermData::fromArray($raw) : null,
            'transport' => is_array($raw) ? TransportData::fromArray($raw) : null,
            'tax_exempt' => (bool) ($raw ?? false),
            default => $this->fallback($raw, $default),
        };
    }

    /**
     * Dynamically access the typed values.
     */
    public function __get($key): mixed
    {
        return $this->get($key);
    }

    /**
     * Determine if the given key is set.
     */
    public function __isset($key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Handle dynamic calls into the payload.
     */
    public function __set($key, $value): void
    {
        throw new LogicException('DocumentPayload is read-only.');
    }

    /**
     * Handle dynamic unset from the payload.
     */
    public function __unset($key): void
    {
        throw new LogicException('DocumentPayload is read-only.');
    }

    /**
     * Return the raw stored value for array access (BC).
     */
    public function offsetGet(mixed $offset): mixed
    {
        // Array access stays raw so existing `$data['items'][0]['name']`
        // code keeps working; use property access for typed values.
        return $this->attributes[$offset] ?? null;
    }

    /**
     * Determine if the raw key exists for array access (BC).
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('DocumentPayload is read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('DocumentPayload is read-only.');
    }

    /**
     * Return unknown keys raw, wrapping lists in collections.
     */
    protected function fallback(mixed $raw, mixed $default): mixed
    {
        if ($raw !== null) {
            return is_array($raw) && array_is_list($raw) ? Collection::make($raw) : $raw;
        }

        return $default;
    }

    /**
     * Parse the document type or null.
     */
    protected function parseDocumentType(mixed $value): ?DteType
    {
        if ($value instanceof DteType) {
            return $value;
        }

        if (is_int($value)) {
            return DteType::tryFrom($value);
        }

        if (is_string($value) && $value !== '') {
            return DteType::tryFrom((int) $value);
        }

        return null;
    }

    /**
     * Parse a date value or null.
     */
    protected function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value instanceof DateTimeImmutable) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }
}
