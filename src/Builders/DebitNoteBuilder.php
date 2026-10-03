<?php

namespace Laragear\Dte\Builders;

use DateTimeImmutable;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\ReferenceType;
use Laragear\Dte\Models\SiiDte;

class DebitNoteBuilder extends NoteBuilder
{
    /**
     * Return electronic debit note type 56.
     */
    public function documentType(): DteType
    {
        return DteType::DebitNote;
    }

    /**
     * Charge amounts to a previous document.
     */
    public function charge(
        DteType|ReferenceType|SiiDte|string|int $documentType,
        ?string $folio = null,
        ?DateTimeImmutable $date = null,
        string $reason = 'Corrige montos'
    ): static {
        return $this->modify($documentType, $folio, $date, $reason);
    }
}
