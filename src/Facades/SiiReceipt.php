<?php

namespace Laragear\Dte\Facades;

use DateTimeImmutable;
use Illuminate\Support\Facades\Facade;
use Laragear\Dte\Builders\ReceiptBuilder;
use Laragear\Dte\Contracts\Issuable;
use Laragear\Dte\Contracts\Itemable;
use Laragear\Dte\Contracts\Receivable;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Data\ReceiverData;
use Laragear\Dte\Data\ReferenceData;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\ModifierTarget;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Testing\Fakes\FakeReceiptBuilder;
use Laragear\Dte\Testing\Fakes\FakesBuilder;
use Laragear\Rut\Rut;

/**
 * @see ReceiptBuilder
 *
 * @method static ReceiptBuilder issuedBy(Issuable|IssuerData $issuer)
 * @method static ReceiptBuilder receivedBy(Receivable|ReceiverData|Rut|string $receiver, ?string $name = null)
 * @method static ReceiptBuilder issuedOn(DateTimeImmutable $date)
 * @method static ReceiptBuilder addItem(Itemable|Item|string $item, int|array|null $total = null, ?bool $isExempt = null)
 * @method static ReceiptBuilder addReference(ReferenceData $reference)
 * @method static ReceiptBuilder forTestCase(string $case)
 * @method static ReceiptBuilder clearReferences()
 * @method static ReceiptBuilder withNetAmountIndicator(int $indicator)
 * @method static ReceiptBuilder nonBillableAmount(int $amount)
 * @method static ReceiptBuilder globalDiscount(float|int $value, bool $isPercent = false, ModifierTarget $target = ModifierTarget::DEFAULT, ?string $description = null)
 * @method static ReceiptBuilder globalSurcharge(float|int $value, bool $isPercent = false, ModifierTarget $target = ModifierTarget::DEFAULT, ?string $description = null)
 * @method static ReceiptBuilder hydrate(SiiDte $dte)
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
 * @method static bool hasCommonUseIva()
 * @method static array globalModifiers()
 * @method static array attributes()
 * @method static array payloadBlocks()
 * @method static SiiDte draft()
 * @method static SiiDte build(bool $sync = false)
 * @method static SiiDte buildSync()
 * @method static SiiDteEnvelope send(mixed $sync = false)
 * @method static SiiDteEnvelope sendSync()
 * @method static void validate()
 * @method FakeReceiptBuilder fake()
 */
class SiiReceipt extends Facade
{
    use FakesBuilder;

    protected static string $fakeClass = FakeReceiptBuilder::class;

    /**
     * @inheritDoc
     */
    protected static function getFacadeAccessor(): string
    {
        return ReceiptBuilder::class;
    }

    /**
     * Assert the expected number of receipts were created.
     */
    public static function assertCreated(?int $times = null): void
    {
        FakeReceiptBuilder::assertCreated($times);
    }

    /**
     * Assert no receipts were created.
     */
    public static function assertNotCreated(): void
    {
        FakeReceiptBuilder::assertNotCreated();
    }

    /**
     * Return all created receipts.
     *
     * @return list<SiiDte>
     */
    public static function created(): array
    {
        return FakeReceiptBuilder::created();
    }

    /**
     * Return the last created receipt, or null.
     */
    public static function lastCreated(): ?SiiDte
    {
        return FakeReceiptBuilder::lastCreated();
    }
}
