<?php

namespace Laragear\Dte\Facades;

use DateTimeImmutable;
use Illuminate\Support\Facades\Facade;
use Laragear\Dte\Builders\InvoiceBuilder;
use Laragear\Dte\Contracts\Issuable;
use Laragear\Dte\Contracts\Itemable;
use Laragear\Dte\Contracts\Receivable;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Data\PaymentTermData;
use Laragear\Dte\Data\ReceiverData;
use Laragear\Dte\Data\ReferenceData;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\ModifierTarget;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Testing\Fakes\FakeInvoiceBuilder;
use Laragear\Dte\Testing\Fakes\FakesBuilder;
use Laragear\Rut\Rut;

/**
 * @see InvoiceBuilder
 *
 * @method static InvoiceBuilder issuedBy(Issuable|IssuerData $issuer)
 * @method static InvoiceBuilder receivedBy(Receivable|ReceiverData|Rut|string $receiver, ?string $name = null)
 * @method static InvoiceBuilder issuedOn(DateTimeImmutable $date)
 * @method static InvoiceBuilder addItem(Itemable|Item|string $item, int|array|null $total = null, ?bool $isExempt = null)
 * @method static InvoiceBuilder addReference(ReferenceData $reference)
 * @method static InvoiceBuilder forTestCase(string $case)
 * @method static InvoiceBuilder clearReferences()
 * @method static InvoiceBuilder addPaymentTerm(PaymentTermData $paymentTerm)
 * @method static InvoiceBuilder asExempt(?int $amount = null)
 * @method static InvoiceBuilder markAsTaxExempt(?int $amount = null)
 * @method static InvoiceBuilder withNetAmountIndicator(int $indicator)
 * @method static InvoiceBuilder nonBillableAmount(int $amount)
 * @method static InvoiceBuilder globalDiscount(float|int $value, bool $isPercent = false, ModifierTarget $target = ModifierTarget::DEFAULT, ?string $description = null)
 * @method static InvoiceBuilder globalSurcharge(float|int $value, bool $isPercent = false, ModifierTarget $target = ModifierTarget::DEFAULT, ?string $description = null)
 * @method static InvoiceBuilder hydrate(SiiDte $dte)
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
 * @method static bool isTaxExempt()
 * @method static ?int exemptAmountOverride()
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
 * @method FakeInvoiceBuilder fake()
 */
class SiiInvoice extends Facade
{
    use FakesBuilder;

    protected static string $fakeClass = FakeInvoiceBuilder::class;

    /**
     * @inheritDoc
     */
    protected static function getFacadeAccessor(): string
    {
        return InvoiceBuilder::class;
    }

    /**
     * Assert the expected number of invoices were created.
     */
    public static function assertCreated(?int $times = null): void
    {
        FakeInvoiceBuilder::assertCreated($times);
    }

    /**
     * Assert no invoices were created.
     */
    public static function assertNotCreated(): void
    {
        FakeInvoiceBuilder::assertNotCreated();
    }

    /**
     * Return all created invoices.
     *
     * @return list<SiiDte>
     */
    public static function created(): array
    {
        return FakeInvoiceBuilder::created();
    }

    /**
     * Return the last created invoice, or null.
     */
    public static function lastCreated(): ?SiiDte
    {
        return FakeInvoiceBuilder::lastCreated();
    }
}
