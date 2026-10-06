<?php

namespace Laragear\Dte\Builders;

use Closure;
use DateTimeImmutable;
use Illuminate\Support\DateFactory;
use Illuminate\Support\Fluent;
use InvalidArgumentException;
use Laragear\Dte\Builders\Concerns\HasGlobalModifiers;
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Contracts\Issuable;
use Laragear\Dte\Contracts\Receivable;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Data\ReceiverData;
use Laragear\Dte\Data\ReferenceData;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Models\SiiDtePayload;
use Laragear\Dte\Services\DteLifecycleService;
use Laragear\Dte\Validation\DocumentValidator;
use Laragear\Rut\Rut;
use LogicException;

use function array_map;
use function min;

abstract class DocumentBuilder
{
    use Concerns\PayloadSerializer;
    use Concerns\TotalsCalculator;
    use HasGlobalModifiers;

    /**
     * The issuer business.
     */
    protected ?IssuerData $issuer = null;

    /**
     * The receiver business or consumer.
     */
    protected ?ReceiverData $receiver = null;

    /**
     * The existing document being hydrated for retry.
     */
    protected ?SiiDte $dte = null;

    /**
     * Whether the document should be persisted as an editable draft.
     */
    protected bool $asDraft = false;

    /**
     * The document detail lines.
     *
     * @var list<Item>
     */
    protected array $items = [];

    /**
     * The document references.
     *
     * @var list<ReferenceData>
     */
    protected array $references = [];

    /**
     * When the document was created.
     */
    protected DateTimeImmutable $issueDate;

    /**
     * Non-billable amount (MontoNoFacturable).
     */
    protected int $nonBillableAmount = 0;

    /**
     * Custom metadata attached to the DTE record.
     */
    protected ?Fluent $metadata = null;

    /**
     * IndMntNeto indicator for boletas (types 39/41).
     *
     * Values:
     * - `null`: omitted (SII defaults to gross pricing with IVA included)
     * - 0: Prices are gross (with IVA included) - same as omitting
     * - 1: Prices are net (without IVA)
     * - 2: Prices are gross, but net amount is explicitly stated
     */
    protected ?int $indMntNeto = null;

    /**
     * Whether the purchase IVA is subject to the Proportional IVA (IVA Uso Común).
     *
     * A per-document fact, unlike the proportional factor itself: the factor is
     * a whole-period property supplied when the Libro de Compras is assembled,
     * because only the period totals can tell taxable sales from total sales.
     */
    protected bool $commonUseIva = false;

    /**
     * Create a Document Builder instance.
     */
    public function __construct(
        protected DateFactory $date,
        protected ConfigurationManager $configurationManager,
        protected DteLifecycleService $dteLifecycle,
        protected DocumentValidator $validator,
    ) {
        $this->issueDate = $date->today('America/Santiago')->toDateTimeImmutable();
    }

    /**
     * Return the numeric SII document type.
     */
    abstract public function documentType(): DteType;

    /**
     * Set custom metadata for this document.
     */
    public function withMetadata(Fluent|array $metadata): static
    {
        $this->metadata = $metadata instanceof Fluent
            ? $metadata
            : new Fluent($metadata);

        return $this;
    }

    /**
     * Set the IndMntNeto indicator for boletas (types 39/41).
     */
    public function withNetAmountIndicator(int $indicator): static
    {
        // 0=gross (IVA included), 1=net (without IVA), 2=gross with explicit
        // net
        if (! in_array($indicator, [0, 1, 2], true)) {
            throw new InvalidArgumentException('IndMntNeto must be 0, 1, or 2.');
        }

        $this->indMntNeto = $indicator;

        return $this;
    }

    /**
     * Return the current IndMntNeto indicator value.
     */
    public function netAmountIndicator(): ?int
    {
        return $this->indMntNeto;
    }

    /**
     * Set the non-billable amount (MontoNoFacturable).
     */
    public function nonBillableAmount(int $amount): static
    {
        $this->nonBillableAmount = max(0, $amount);

        return $this;
    }

    /**
     * Return the non-billable amount.
     */
    public function getNonBillableAmount(): int
    {
        return $this->nonBillableAmount;
    }

    /**
     * Flag the purchase as subject to the Proportional IVA (IVA Uso Común).
     *
     * Marks this purchase as destined in part to exempt sales, such as
     * electricity, rent or cleaning. The credit is proportional: the book
     * builder needs the period factor to split it between fiscal credit and
     * cost, so pass IecvProperty::CommonIvaFactor when assembling the
     * Libro de Compras.
     */
    public function withCommonUseIva(bool $commonUse = true): static
    {
        $this->commonUseIva = $commonUse;

        return $this;
    }

    /**
     * Check whether the purchase is subject to the Proportional IVA.
     */
    public function hasCommonUseIva(): bool
    {
        return $this->commonUseIva;
    }

    /**
     * Add a detail line to the document.
     */
    abstract public function addItem(Item $item): static;

    /**
     * Return all calculated document totals.
     *
     * @return array{net: int, exempt: int, tax: int, total: int}
     */
    abstract public function totals(): array;

    /**
     * Set the taxpayer issuing the document.
     */
    public function issuedBy(Issuable|IssuerData $issuer): static
    {
        if ($issuer instanceof Issuable) {
            $issuer = $issuer->toIssuer();
        }

        $this->issuer = $issuer;

        return $this;
    }

    /**
     * Set the taxpayer receiving the document.
     */
    public function receivedBy(Receivable|ReceiverData|Rut|string $receiver, ?string $name = null): static
    {
        $this->receiver = match (true) {
            $receiver instanceof Receivable => $receiver->toReceiver(),
            $receiver instanceof ReceiverData => $receiver,
            default => ReceiverData::make($receiver, $name),
        };

        return $this;
    }

    /**
     * Set the accounting issue date.
     */
    public function issuedOn(DateTimeImmutable $date): static
    {
        $this->issueDate = $date;

        return $this;
    }

    /**
     * Return the taxpayer issuing the document.
     */
    public function issuer(): IssuerData
    {
        return $this->issuer
            ?? $this->configurationManager->getIssuer()
            ?? throw new LogicException('The DTE issuer has not been configured.');
    }

    /**
     * Return the document receiver when configured.
     */
    public function receiver(): ?ReceiverData
    {
        return $this->receiver;
    }

    /**
     * Return the existing document model instance being retried.
     */
    public function dte(): ?SiiDte
    {
        return $this->dte;
    }

    /**
     * Return an empty reference list for documents without references.
     *
     * @return list<ReferenceData>
     */
    public function references(): array
    {
        return [];
    }

    /**
     * Restore the builder state from an existing document payload.
     */
    public function hydrate(SiiDte $dte): static
    {
        $this->dte = $dte;
        $dte->loadMissing('payload');

        $payload = $dte->payload;
        $idDoc = $payload->header_id_doc;

        $issuedOn = $idDoc['issued_on'];

        if ($issuedOn !== null) {
            $this->issueDate = DateTimeImmutable::createFromFormat('Y-m-d', $issuedOn) ?: $this->issueDate;
        }

        if (! $payload->header_issuer->isEmpty()) {
            $this->issuedBy(IssuerData::fromArray($payload->header_issuer->toArray()));
        }

        if (! $payload->header_receiver->isEmpty()) {
            $this->receivedBy(ReceiverData::fromArray($payload->header_receiver->toArray()));
        }

        $this->items = array_map(Item::fromArray(...), $payload->detail_items['items'] ?? []);

        $this->references = array_map(ReferenceData::fromArray(...), $payload->references['items'] ?? []);

        $this->globalModifiers = $payload->global_modifiers['items'] ?? [];

        $this->nonBillableAmount = $payload->header_totals['non_billable'] ?? 0;

        // The flag lives on the model column rather than the payload, so it is
        // restored from the document itself to survive a rehydrate-and-rebuild.
        // Coerced because an unhydrated or partial model may expose no value.
        $this->commonUseIva = (bool) $dte->iva_common_use;

        $this->hydrateAdditional($payload);

        return $this;
    }

    /**
     * Restore subclass-specific input from the persisted payload blocks.
     */
    protected function hydrateAdditional(SiiDtePayload $payload): void
    {
        //
    }

    /**
     * Persist the draft document and its raw payload atomically.
     */
    public function draft(): SiiDte
    {
        // Builders stay stateless across persists: every draft() writes a new
        // row, unless hydrated, when the existing row is updated instead.
        $this->asDraft = true;

        try {
            return $this->dteLifecycle->persist($this, $this->dte !== null);
        } finally {
            $this->asDraft = false;
        }
    }

    /**
     * Whether the builder input would persist as a draft.
     */
    public function isDrafting(): bool
    {
        return $this->asDraft;
    }

    /**
     * Persist the document and dispatch its compilation.
     */
    public function build(bool $sync = false): SiiDte
    {
        // Builders stay stateless across persists: every build() writes a new
        // row, unless hydrated, when the existing row is rebuilt instead.
        $dte = $this->dteLifecycle->persist($this, $this->dte !== null);

        return $this->dteLifecycle->compile($dte, $sync);
    }

    /**
     * Persist the document and compile its XML immediately.
     */
    public function buildSync(): SiiDte
    {
        return $this->build(sync: true);
    }

    /**
     * Build the document and send it in an exclusive envelope.
     *
     * @param  (Closure(SiiDte $dte, SiiDteEnvelope $envelope): mixed)|mixed  $sync
     */
    public function send(mixed $sync = false): SiiDteEnvelope
    {
        return $this->buildSync()->send($sync);
    }

    /**
     * Build the document and send it synchronously.
     */
    public function sendSync(): SiiDteEnvelope
    {
        return $this->send(sync: true);
    }

    /**
     * Validate the common document input.
     */
    public function validate(): void
    {
        $this->issuer();
        $this->receiverRut();

        if ($this->items() === []) {
            throw new LogicException('The DTE must contain at least one item.');
        }

        if (min($this->calculatedTotals()) < 0) {
            throw new LogicException('The DTE totals cannot be negative.');
        }

        $this->validateSpecific();

        // The XSD rules catch SII rejections before the folio is burned.
        $this->validator->validate($this->payloadBlocks());
    }

    /**
     * Validate document-specific input.
     */
    protected function validateSpecific(): void
    {
        //
    }

    /**
     * Ensure the receiver contains mandatory B2B fields.
     */
    protected function validateB2bReceiver(): void
    {
        // B2B DTEs (e.g. invoices, debit/credit notes) strictly require the receiver's
        // business activity, address, and commune. By validating these, we avoid the
        // Document from being processed and then rejected by SII, burning its folio.
        if (
            ! $this->receiver
            || $this->receiver->activity === null
            || $this->receiver->address === null
            || $this->receiver->commune === null
        ) {
            throw new LogicException(
                'B2B documents require a receiver with a business activity, address, and commune.',
            );
        }
    }

    /**
     * Return initial document model attributes.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        $totals = $this->calculatedTotals();

        return [
            'issuer_rut' => $this->issuer()->rut,
            'receiver_rut' => $this->receiverRut(),
            'document_type' => $this->documentType(),
            'metadata' => $this->metadata?->toArray(),
            'issued_on' => $this->issueDate,
            'amount_net' => $totals['net'],
            'amount_exempt' => $totals['exempt'],
            'amount_taxes' => $totals['tax'],
            'taxes' => empty($taxes = $this->aggregateTaxes()) ? null : $taxes,
            'amount_total' => $totals['total'],
            'iva_common_use' => $this->commonUseIva,
            'status' => $this->asDraft ? DteStatus::Draft : DteStatus::Pending,
        ];
    }

    /**
     * Return the receiver RUT stored on the initial model.
     */
    protected function receiverRut(): Rut
    {
        return $this->receiver?->rut ?? throw new LogicException('The DTE receiver has not been configured.');
    }
}
