<?php

namespace Laragear\Dte\Data;

use DateTimeImmutable;
use Laragear\Rut\Rut;

readonly class CessionData
{
    /**
     * Create a new Cession Data instance.
     */
    public function __construct(
        public Rut $assigneeRut,
        public string $assigneeName,
        public string $assigneeAddress,
        public string $assigneeEmail,
        public int $amount,
        public DateTimeImmutable $lastDueDate,
        public ?string $terms = null,
    ) {
        //
    }

    /**
     * Creae a new instance fluently.
     */
    public static function make(
        Rut|string $assigneeRut,
        string $assigneeName,
        string $assigneeAddress,
        string $assigneeEmail,
        int $amount,
        DateTimeImmutable $lastDueDate,
        ?string $terms = null,
    ): static {
        return new static(
            is_string($assigneeRut) ? Rut::parse($assigneeRut) : $assigneeRut,
            $assigneeName,
            $assigneeAddress,
            $assigneeEmail,
            $amount,
            $lastDueDate,
            $terms,
        );
    }
}
