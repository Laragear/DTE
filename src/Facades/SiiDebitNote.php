<?php

namespace Laragear\Dte\Facades;

use DateTimeImmutable;
use Illuminate\Support\Facades\Facade;
use Laragear\Dte\Builders\DebitNoteBuilder;
use Laragear\Dte\Contracts\Issuable;
use Laragear\Dte\Contracts\Itemable;
use Laragear\Dte\Contracts\Receivable;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Data\ReceiverData;
use Laragear\Dte\Data\ReferenceData;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\ModifierTarget;
use Laragear\Dte\Enums\ReferenceType;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Testing\Fakes\FakeDebitNoteBuilder;
use Laragear\Dte\Testing\Fakes\FakesBuilder;
use Laragear\Rut\Rut;

/**
 * @see DebitNoteBuilder
 *
 * @method static DebitNoteBuilder issuedBy(Issuable|IssuerData $issuer)
 * @method static DebitNoteBuilder receivedBy(Receivable|ReceiverData|Rut|string $receiver, ?string $name = null)
 * @method static DebitNoteBuilder issuedOn(DateTimeImmutable $date)
 * @method static DebitNoteBuilder addItem(Itemable|Item|string $item, int|array|null $total = null, ?bool $isExempt = null)
 * @method static DebitNoteBuilder addReference(ReferenceData $reference)
 * @method static DebitNoteBuilder forTestCase(string $case)
 * @method static DebitNoteBuilder clearReferences()
 * @method static DebitNoteBuilder withNetAmountIndicator(int $indicator)
 * @method static DebitNoteBuilder nonBillableAmount(int $amount)
 * @method static DebitNoteBuilder globalDiscount(float|int $value, bool $isPercent = false, ModifierTarget $target = ModifierTarget::DEFAULT, ?string $description = null)
 * @method static DebitNoteBuilder globalSurcharge(float|int $value, bool $isPercent = false, ModifierTarget $target = ModifierTarget::DEFAULT, ?string $description = null)
 * @method static DebitNoteBuilder hydrate(SiiDte $dte)
 * @method static DebitNoteBuilder annul(DteType|ReferenceType|SiiDte|string|int $documentType, ?string $folio = null, ?DateTimeImmutable $date = null, string $reason = 'Anula documento')
 * @method static DebitNoteBuilder amend(DteType|ReferenceType|SiiDte|string|int $documentType, ?string $folio = null, ?DateTimeImmutable $date = null, string $reason = 'Corrige texto')
 * @method static DteType documentType()
 * @method static IssuerData issuer()
 * @method static ?ReceiverData receiver()
 * @method static ?SiiDte dte()
 * @method static list<Item> items()
 * @method static int itemAmount(Item $item)
 * @method static int netAmount()
 * @method static int exemptAmount()
 * @method static int taxAmount()
 * @method static int totalAmount()
 * @method static array totals()
 * @method static array references()
 * @method static ?int netAmountIndicator()
 * @method static int getNonBillableAmount()
 * @method static array globalModifiers()
 * @method static array attributes()
 * @method static array payloadData()
 * @method static SiiDte draft()
 * @method static SiiDte build(bool $sync = false)
 * @method static SiiDte buildSync()
 * @method static SiiDteEnvelope send(mixed $sync = false)
 * @method static SiiDteEnvelope sendSync()
 * @method static void validate()
 * @method FakeDebitNoteBuilder fake()
 */
class SiiDebitNote extends Facade
{
    use FakesBuilder;

    protected static string $fakeClass = FakeDebitNoteBuilder::class;

    /**
     * @inheritDoc
     */
    protected static function getFacadeAccessor(): string
    {
        return DebitNoteBuilder::class;
    }

    /**
     * Assert the expected number of debit notes were created.
     */
    public static function assertCreated(?int $times = null): void
    {
        FakeDebitNoteBuilder::assertCreated($times);
    }

    /**
     * Assert no debit notes were created.
     */
    public static function assertNotCreated(): void
    {
        FakeDebitNoteBuilder::assertNotCreated();
    }

    /**
     * Return all created debit notes.
     *
     * @return list<SiiDte>
     */
    public static function created(): array
    {
        return FakeDebitNoteBuilder::created();
    }

    /**
     * Return the last created debit note, or null.
     */
    public static function lastCreated(): ?SiiDte
    {
        return FakeDebitNoteBuilder::lastCreated();
    }
}
