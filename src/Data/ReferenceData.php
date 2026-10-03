<?php

namespace Laragear\Dte\Data;

use ArrayAccess;
use DateTimeImmutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\ReferenceType;
use Laragear\Dte\Validation\DteRules;
use LogicException;
use Throwable;

use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function validator;

/**
 * @implements ArrayAccess<string, mixed>
 */
readonly class ReferenceData implements Arrayable, ArrayAccess, Jsonable, JsonSerializable
{
    /**
     * Create a new Reference Data instance.
     */
    public function __construct(
        public DteType|ReferenceType $documentType,
        public ?string $folio,
        public ?DateTimeImmutable $date,
        public ?string $reason = null,
        public ?int $referenceCode = null,
    ) {
        //
    }

    /**
     * Create a new instance fluently
     */
    public static function make(
        DteType|ReferenceType|string|int $documentType,
        ?string $folio,
        DateTimeImmutable|string|null $date = null,
        ?string $reason = null,
        ?int $referenceCode = null,
    ): static {
        return new static(static::normalizeDocumentType($documentType), $folio, static::parseDate($date), $reason,
            $referenceCode);
    }

    /**
     * Normalizes the Document Type value.
     */
    protected static function normalizeDocumentType(DteType|ReferenceType|string|int $type): DteType|ReferenceType
    {
        if ($type instanceof DteType || $type instanceof ReferenceType) {
            return $type;
        }

        if (is_int($type)) {
            return DteType::from($type);
        }

        if (ReferenceType::tryFrom($type) !== null) {
            return ReferenceType::from($type);
        }

        return DteType::from((int) $type);
    }

    /**
     * Create a new instance from an array.
     */
    public static function fromArray(array $array): static
    {
        return static::make(
            $array['document_type'],
            $array['folio'] ?? null,
            $array['date'] ?? null,
            $array['reason'] ?? null,
            $array['reference_code'] ?? null,
        );
    }

    /**
     * Create an instance from a JSON string.
     */
    public static function fromJson(string $json): static
    {
        return static::fromArray(json_decode($json, true, 5));
    }

    /**
     * Return the validation rules for the reference data.
     *
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return DteRules::REFERENCE;
    }

    /**
     * Validate the reference data against the SII XSD rules.
     */
    public function validate(): void
    {
        validator($this->toArray(), static::rules())->validate();
    }

    /**
     * Convert the instance to an array.
     *
     * @return array{document_type: int|string, folio: string|null, date: string|null, reason: string|null, reference_code: int|null}
     */
    public function toArray(): array
    {
        return [
            'document_type' => $this->documentType->value,
            'folio' => $this->folio,
            'date' => $this->date?->format('Y-m-d'),
            'reason' => $this->reason,
            'reference_code' => $this->referenceCode,
        ];
    }

    /**
     * Convert the instance to JSON.
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{document_type: int|string, folio: string|null, date: string|null, reason: string|null, reference_code: int|null}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->toArray()[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->toArray()[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('ReferenceData is read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('ReferenceData is read-only.');
    }

    /**
     * Parse a date value or null.
     */
    protected static function parseDate(DateTimeImmutable|string|null $value): ?DateTimeImmutable
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
