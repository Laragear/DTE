<?php

namespace Laragear\Dte\Models\Concerns;

use Closure;
use Laragear\Dte\Builders\CreditNoteBuilder;
use Laragear\Dte\Builders\DebitNoteBuilder;
use Laragear\Dte\Builders\NoteBuilder;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Data\ReceiverData;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\SiiDte;
use LogicException;
use function app;
use function with;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasCorrections
{
    /**
     * Default reason codes mapped to their text descriptions.
     *
     * @var array<int, string>
     */
    protected const array REASON_DESCRIPTIONS = [
        1 => 'Anula documento',
        2 => 'Corrige texto',
        3 => 'Corrige montos',
    ];

    /**
     * Hydrate a correction-note builder from this document payload.
     *
     * @param  list<Item|array<string, mixed>>|null  $items
     */
    protected function buildNoteFrom(
        NoteBuilder $builder,
        ?array $items,
        ?string $reason,
        int $referenceCode
    ): NoteBuilder {
        $this->loadPayloadForCorrection();

        $this->hydrateIssuerAndReceiver($builder);
        $this->hydrateItems($builder, $items);
        $this->applyReferenceCode($builder, $referenceCode, $reason);

        return $builder;
    }

    /**
     * Load the payload relationship for correction inference.
     */
    protected function loadPayloadForCorrection(): void
    {
        $this->loadMissing('payload');

        if ($this->payload === null) {
            throw new LogicException("The DTE [{$this->getKey()}] has no payload data to infer from.");
        }
    }

    /**
     * Hydrate issuer and receiver onto the builder from stored payload.
     */
    protected function hydrateIssuerAndReceiver(NoteBuilder $builder): void
    {
        // Issuer comes from the stored payload since config may have changed.
        if (! $this->payload->header_issuer->isEmpty()) {
            $builder->issuedBy(IssuerData::fromArray($this->payload->header_issuer->toArray()));
        }

        if (! $this->payload->header_receiver->isEmpty()) {
            $builder->receivedBy(ReceiverData::fromArray($this->payload->header_receiver->toArray()));
        }
    }

    /**
     * Map items from provided array or fall back to payload items.
     *
     * @param  list<Item|array<string, mixed>>|null  $items
     */
    protected function hydrateItems(NoteBuilder $builder, ?array $items): void
    {
        $fallback = array_map(Item::fromArray(...), $this->payload->detail_items['items'] ?? []);

        foreach ($items ?? $fallback as $item) {
            $builder->addItem($item instanceof Item ? $item : Item::fromArray($item));
        }
    }

    /**
     * Apply the reference code mapping with default reason fallback.
     */
    protected function applyReferenceCode(NoteBuilder $builder, int $referenceCode, ?string $reason): void
    {
        $description = $reason ?? self::REASON_DESCRIPTIONS[$referenceCode];

        match ($referenceCode) {
            1 => $builder->annul($this, reason: $description),
            2 => $builder->amend($this, reason: $description),
            3 => $builder->modify($this, reason: $description),
        };
    }

    /**
     * Annul this document through an inverted correction note.
     */
    public function annul(?string $reason = null): SiiDte
    {
        // Credit notes annul through debit notes, and vice versa.
        $builder = app(
            $this->document_type === DteType::CreditNote
                ? DebitNoteBuilder::class
                : CreditNoteBuilder::class
        );

        return $this->buildNoteFrom($builder, null, $reason, 1)->build();
    }

    /**
     * Correct this document text through a credit note.
     *
     * @param  (Closure(CreditNoteBuilder): mixed)|null  $callback
     */
    public function amend(?Closure $callback = null, ?string $reason = null): SiiDte
    {
        // Only Facturas and Liquidación de Factura admit text correction.
        $this->ensureAmendableDocumentType();

        $builder = app(CreditNoteBuilder::class);

        $this->buildNoteFrom($builder, null, $reason, 2);

        with($builder, $callback);

        return $builder->build();
    }

    /**
     * Ensure the document type admits text amendment.
     */
    protected function ensureAmendableDocumentType(): void
    {
        if ($this->document_type->isNotAmendable()) {
            throw new LogicException(
                "The document type [{$this->document_type->label()}] cannot be amended. ".
                'Text correction only applies to Facturas and Liquidación de Factura. '.
                'Annul the document and reissue instead.'
            );
        }
    }

    /**
     * Reduce this document amount through a credit note.
     *
     * @param  list<Item|array<string, mixed>>  $items
     */
    public function discount(array $items, ?string $reason = null): SiiDte
    {
        $this->ensureDiscountableDocumentType();

        return $this->buildNoteFrom(app(CreditNoteBuilder::class), $items, $reason, 3)->build();
    }

    /**
     * Ensure the document type admits a discount credit note.
     */
    protected function ensureDiscountableDocumentType(): void
    {
        if ($this->document_type === DteType::CreditNote) {
            throw new LogicException(
                'Cannot apply a discount to a Credit Note. Use surcharge() to add amounts.'
            );
        }

        $this->ensureNoteModifiableDocumentType();
    }

    /**
     * Increase this document amount through a debit note.
     *
     * @param  list<Item|array<string, mixed>>  $items
     */
    public function surcharge(array $items, ?string $reason = null): SiiDte
    {
        $this->ensureSurchargeableDocumentType();

        return $this->buildNoteFrom(app(DebitNoteBuilder::class), $items, $reason, 3)->build();
    }

    /**
     * Ensure the document type admits a surcharge debit note.
     */
    protected function ensureSurchargeableDocumentType(): void
    {
        if ($this->document_type === DteType::DebitNote) {
            throw new LogicException(
                'Cannot apply a surcharge to a Debit Note. Use discount() to reduce amounts.'
            );
        }

        $this->ensureNoteModifiableDocumentType();
    }

    /**
     * Ensure dispatch guides are never corrected with notes.
     */
    protected function ensureNoteModifiableDocumentType(): void
    {
        if ($this->document_type === DteType::DispatchGuide) {
            throw new LogicException(
                'Dispatch Guides cannot be modified with credit/debit notes. '.
                'Annul the associated invoice and reissue.'
            );
        }
    }
}
