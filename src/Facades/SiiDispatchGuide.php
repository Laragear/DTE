<?php

namespace Laragear\Dte\Facades;

use DateTimeImmutable;
use Illuminate\Support\Facades\Facade;
use Laragear\Dte\Builders\DispatchGuideBuilder;
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
use Laragear\Dte\Testing\Fakes\FakeDispatchGuideBuilder;
use Laragear\Dte\Testing\Fakes\FakesBuilder;
use Laragear\Rut\Rut;

/**
 * @see DispatchGuideBuilder
 *
 * @method static DispatchGuideBuilder issuedBy(Issuable|IssuerData $issuer)
 * @method static DispatchGuideBuilder receivedBy(Receivable|ReceiverData|Rut|string $receiver, ?string $name = null)
 * @method static DispatchGuideBuilder issuedOn(DateTimeImmutable $date)
 * @method static DispatchGuideBuilder addItem(Itemable|Item|string $item, int|array|null $total = null, ?bool $isExempt = null)
 * @method static DispatchGuideBuilder addReference(ReferenceData $reference)
 * @method static DispatchGuideBuilder forTestCase(string $case)
 * @method static DispatchGuideBuilder clearReferences()
 * @method static DispatchGuideBuilder withNetAmountIndicator(int $indicator)
 * @method static DispatchGuideBuilder nonBillableAmount(int $amount)
 * @method static DispatchGuideBuilder globalDiscount(float|int $value, bool $isPercent = false, ModifierTarget $target = ModifierTarget::DEFAULT, ?string $description = null)
 * @method static DispatchGuideBuilder globalSurcharge(float|int $value, bool $isPercent = false, ModifierTarget $target = ModifierTarget::DEFAULT, ?string $description = null)
 * @method static DispatchGuideBuilder hydrate(SiiDte $dte)
 * @method static DispatchGuideBuilder withVehicle(string $vehiclePlate, ?string $trailerPlate = null)
 * @method static DispatchGuideBuilder transferMotive(int $indTraslado)
 * @method static DispatchGuideBuilder dispatchType(int $tipoDespacho)
 * @method static DispatchGuideBuilder withCarrier(Rut $carrier)
 * @method static DispatchGuideBuilder withDriver(Rut $driver, string $name)
 * @method static DispatchGuideBuilder toDestination(string $address, string $commune, ?string $city = null)
 * @method static DispatchGuideBuilder withTransportSchedule(DateTimeImmutable $departure, ?DateTimeImmutable $arrival = null)
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
 * @method static array transport()
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
 * @method FakeDispatchGuideBuilder fake()
 */
class SiiDispatchGuide extends Facade
{
    use FakesBuilder;

    protected static string $fakeClass = FakeDispatchGuideBuilder::class;

    /**
     * @inheritDoc
     */
    protected static function getFacadeAccessor(): string
    {
        return DispatchGuideBuilder::class;
    }

    /**
     * Assert the expected number of dispatch guides were created.
     */
    public static function assertCreated(?int $times = null): void
    {
        FakeDispatchGuideBuilder::assertCreated($times);
    }

    /**
     * Assert no dispatch guides were created.
     */
    public static function assertNotCreated(): void
    {
        FakeDispatchGuideBuilder::assertNotCreated();
    }

    /**
     * Return all created dispatch guides.
     *
     * @return list<SiiDte>
     */
    public static function created(): array
    {
        return FakeDispatchGuideBuilder::created();
    }

    /**
     * Return the last created dispatch guide, or null.
     */
    public static function lastCreated(): ?SiiDte
    {
        return FakeDispatchGuideBuilder::lastCreated();
    }
}
