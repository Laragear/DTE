<?php

namespace Laragear\Dte\Facades;

use DateTimeImmutable;
use Illuminate\Support\Facades\Facade;
use Laragear\Dte\Builders\PurchaseInvoiceBuilder;
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
use Laragear\Dte\Testing\Fakes\FakePurchaseInvoiceBuilder;
use Laragear\Dte\Testing\Fakes\FakesBuilder;
use Laragear\Rut\Rut;

/**
 * @see PurchaseInvoiceBuilder
 *
 * @method static PurchaseInvoiceBuilder issuedBy(Issuable|IssuerData $issuer)
 * @method static PurchaseInvoiceBuilder receivedBy(Receivable|ReceiverData|Rut|string $receiver, ?string $name = null)
 * @method static PurchaseInvoiceBuilder issuedOn(DateTimeImmutable $date)
 * @method static PurchaseInvoiceBuilder addItem(Itemable|Item|string $item, int|array|null $total = null, ?bool $isExempt = null)
 * @method static PurchaseInvoiceBuilder addReference(ReferenceData $reference)
 * @method static PurchaseInvoiceBuilder forTestCase(string $case)
 * @method static PurchaseInvoiceBuilder clearReferences()
 * @method static PurchaseInvoiceBuilder withNetAmountIndicator(int $indicator)
 * @method static PurchaseInvoiceBuilder nonBillableAmount(int $amount)
 * @method static PurchaseInvoiceBuilder globalDiscount(float|int $value, bool $isPercent = false, ModifierTarget $target = ModifierTarget::DEFAULT, ?string $description = null)
 * @method static PurchaseInvoiceBuilder globalSurcharge(float|int $value, bool $isPercent = false, ModifierTarget $target = ModifierTarget::DEFAULT, ?string $description = null)
 * @method static PurchaseInvoiceBuilder hydrate(SiiDte $dte)
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
 * @method FakePurchaseInvoiceBuilder fake()
 */
class SiiPurchaseInvoice extends Facade
{
    use FakesBuilder;

    protected static string $fakeClass = FakePurchaseInvoiceBuilder::class;

    /**
     * @inheritDoc
     */
    protected static function getFacadeAccessor(): string
    {
        return PurchaseInvoiceBuilder::class;
    }

    /**
     * Assert the expected number of purchase invoices were created.
     */
    public static function assertCreated(?int $times = null): void
    {
        FakePurchaseInvoiceBuilder::assertCreated($times);
    }

    /**
     * Assert no purchase invoices were created.
     */
    public static function assertNotCreated(): void
    {
        FakePurchaseInvoiceBuilder::assertNotCreated();
    }

    /**
     * Return all created purchase invoices.
     *
     * @return list<SiiDte>
     */
    public static function created(): array
    {
        return FakePurchaseInvoiceBuilder::created();
    }

    /**
     * Return the last created purchase invoice, or null.
     */
    public static function lastCreated(): ?SiiDte
    {
        return FakePurchaseInvoiceBuilder::lastCreated();
    }
}
