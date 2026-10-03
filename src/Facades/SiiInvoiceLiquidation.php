<?php

namespace Laragear\Dte\Facades;

use DateTimeImmutable;
use Illuminate\Support\Facades\Facade;
use Laragear\Dte\Builders\InvoiceLiquidationBuilder;
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
use Laragear\Dte\Testing\Fakes\FakeInvoiceLiquidationBuilder;
use Laragear\Dte\Testing\Fakes\FakesBuilder;
use Laragear\Rut\Rut;

/**
 * @see InvoiceLiquidationBuilder
 *
 * @method static InvoiceLiquidationBuilder issuedBy(Issuable|IssuerData $issuer)
 * @method static InvoiceLiquidationBuilder receivedBy(Receivable|ReceiverData|Rut|string $receiver, ?string $name = null)
 * @method static InvoiceLiquidationBuilder issuedOn(DateTimeImmutable $date)
 * @method static InvoiceLiquidationBuilder addItem(Itemable|Item|string $item, int|array|null $total = null, ?bool $isExempt = null)
 * @method static InvoiceLiquidationBuilder addReference(ReferenceData $reference)
 * @method static InvoiceLiquidationBuilder forTestCase(string $case)
 * @method static InvoiceLiquidationBuilder clearReferences()
 * @method static InvoiceLiquidationBuilder withNetAmountIndicator(int $indicator)
 * @method static InvoiceLiquidationBuilder nonBillableAmount(int $amount)
 * @method static InvoiceLiquidationBuilder globalDiscount(float|int $value, bool $isPercent = false, ModifierTarget $target = ModifierTarget::DEFAULT, ?string $description = null)
 * @method static InvoiceLiquidationBuilder globalSurcharge(float|int $value, bool $isPercent = false, ModifierTarget $target = ModifierTarget::DEFAULT, ?string $description = null)
 * @method static InvoiceLiquidationBuilder hydrate(SiiDte $dte)
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
 * @method FakeInvoiceLiquidationBuilder fake()
 */
class SiiInvoiceLiquidation extends Facade
{
    use FakesBuilder;

    protected static string $fakeClass = FakeInvoiceLiquidationBuilder::class;

    /**
     * @inheritDoc
     */
    protected static function getFacadeAccessor(): string
    {
        return InvoiceLiquidationBuilder::class;
    }

    /**
     * Assert the expected number of invoice liquidations were created.
     */
    public static function assertCreated(?int $times = null): void
    {
        FakeInvoiceLiquidationBuilder::assertCreated($times);
    }

    /**
     * Assert no invoice liquidations were created.
     */
    public static function assertNotCreated(): void
    {
        FakeInvoiceLiquidationBuilder::assertNotCreated();
    }

    /**
     * Return all created invoice liquidations.
     *
     * @return list<SiiDte>
     */
    public static function created(): array
    {
        return FakeInvoiceLiquidationBuilder::created();
    }

    /**
     * Return the last created invoice liquidation, or null.
     */
    public static function lastCreated(): ?SiiDte
    {
        return FakeInvoiceLiquidationBuilder::lastCreated();
    }
}
