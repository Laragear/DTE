<?php

namespace Laragear\Dte\Builders;

use DateTimeImmutable;
use Laragear\Dte\Data\ReferenceData;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\ReferenceType;
use Laragear\Dte\Models\SiiDte;
use LogicException;
use function array_find;
use function in_array;

class CreditNoteBuilder extends NoteBuilder
{
    /**
     * Return electronic credit note type 61.
     */
    public function documentType(): DteType
    {
        return DteType::CreditNote;
    }

    /**
     * Discount amounts on a previous document.
     */
    public function discount(
        DteType|ReferenceType|SiiDte|string|int $documentType,
        ?string $folio = null,
        ?DateTimeImmutable $date = null,
        string $reason = 'Corrige montos'
    ): static {
        return $this->modify($documentType, $folio, $date, $reason);
    }

    /**
     * Ensure the note references its document without annulment conflicts.
     */
    protected function validateSpecific(): void
    {
        parent::validateSpecific();

        $reference = $this->findAnnulmentInvoiceReference();

        if ($reference === null) {
            return;
        }

        $rut = $this->issuer()->rut;

        $invoice = SiiDte::where('issuer_num', $rut->num)
            ->where('issuer_vd', $rut->vd)
            ->where('document_type', $reference->documentType)
            ->where('folio', $reference->folio)
            ->first();

        if ($invoice !== null && $invoice->hasConflictingAnnulmentCreditNote() !== null) {
            throw new LogicException(
                'The invoice [Folio '.$reference->folio.'] already has an accepted or pending annulment credit note.'
            );
        }
    }

    /**
     * Find the first annulment reference targeting a DTE Invoice type.
     */
    protected function findAnnulmentInvoiceReference(): ?ReferenceData
    {
        return array_find($this->references(), static function (ReferenceData $reference): bool {
            return $reference->referenceCode === 1
                && $reference->documentType instanceof DteType
                && in_array($reference->documentType, [DteType::Invoice, DteType::InvoiceExempt], true);
        });
    }
}
