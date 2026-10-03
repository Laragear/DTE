<?php

namespace Laragear\Dte\Testing;

use Illuminate\Foundation\Console\Kernel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Facade;
use Laragear\Dte\Builders\DocumentBuilder;
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Data\CompanyData;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Data\ReceiverData;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\SiiRut;
use Laragear\Dte\Facades\SiiCreditNote;
use Laragear\Dte\Facades\SiiDebitNote;
use Laragear\Dte\Facades\SiiDispatchGuide;
use Laragear\Dte\Facades\SiiInvoice;
use Laragear\Dte\Facades\SiiInvoiceLiquidation;
use Laragear\Dte\Facades\SiiPurchaseInvoice;
use Laragear\Dte\Facades\SiiReceipt;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Pdf\Ted\Pdf417Encoder;
use Laragear\Dte\Testing\Fakes\FakeCreditNoteBuilder;
use Laragear\Dte\Testing\Fakes\FakeDebitNoteBuilder;
use Laragear\Dte\Testing\Fakes\FakeDispatchGuideBuilder;
use Laragear\Dte\Testing\Fakes\FakeInvoiceBuilder;
use Laragear\Dte\Testing\Fakes\FakeInvoiceLiquidationBuilder;
use Laragear\Dte\Testing\Fakes\FakePurchaseInvoiceBuilder;
use Laragear\Dte\Testing\Fakes\FakeReceiptBuilder;
use Laragear\Rut\Facades\Generator as RutGenerator;
use Laragear\Rut\Rut;
use LogicException;
use Mockery\MockInterface;

trait InteractsWithSiiDte
{
    use InteractsWithDigitalCertificates;

    protected ?DteFake $dteFake = null;

    /**
     * All builder facades managed by this trait.
     *
     * @var class-string<Facade>[]
     */
    protected const array FACADE_CLASSES = [
        SiiInvoice::class,
        SiiReceipt::class,
        SiiCreditNote::class,
        SiiDebitNote::class,
        SiiDispatchGuide::class,
        SiiPurchaseInvoice::class,
        SiiInvoiceLiquidation::class,
    ];

    /**
     * Set up the DTE testing environment.
     */
    protected function setUpInteractsWithSiiDte(): void
    {
        $this->configureDteIssuer(
            RutGenerator::asCompanies()->makeOne(),
            'Test Company',
        );

        $this->fakeDte();
    }

    /**
     * Clean up DTE fakes.
     */
    protected function tearDownInteractsWithSiiDte(): void
    {
        foreach (static::FACADE_CLASSES as $facade) {
            $facade::restore();
        }

        // Delete persisted documents before flushing.
        if ($this->dteFake !== null) {
            foreach ($this->dteFake->created() as $dte) {
                if ($dte->exists) {
                    $dte->forceDelete();
                }
            }
        }

        DteFake::flushAll();

        $this->dteFake = null;
    }

    /**
     * Returns a list of CAF that should be created, by RUT and Types.
     *
     * @return array<string, DteType[]|DteType>
     */
    protected function setUpCafs(): array
    {
        return [
            SiiRut::DEFAULT->value => [DteType::Invoice],
        ];
    }

    /**
     * Create fake CAF entries for the configured RUTs and document types.
     */
    protected function createFakeCafs(): void
    {
        foreach ($this->setUpCafs() as $rut => $types) {
            foreach (Arr::wrap($types) as $type) {
                $this->app->make(Kernel::class)->call('dte:make-fake-caf', [
                    '--db' => true,
                    '--type' => $type,
                    '--rut' => $rut,
                ]);
            }
        }
    }

    /**
     * Configure the global issuer for tests.
     */
    protected function configureDteIssuer(
        Rut|string $rut,
        string $name,
        string $activity = 'Software services',
        string|array $acteco = '620200',
        string $address = 'Main Street 123',
        string $commune = 'Santiago',
        string $resolutionDate = '2025-01-01',
        int $activityCode = 80,
    ): void {
        $rut = Rut::parse($rut);

        ConfigurationManager::setCompany(static fn() => CompanyData::make(
            IssuerData::make($rut, $name, $activity, $acteco, $address, $commune, $resolutionDate, $activityCode),
            $rut,
        ));
    }

    /**
     * Fake all DTE facades. Returns DteFake for cross-type assertions.
     */
    protected function fakeDte(): DteFake
    {
        foreach (static::FACADE_CLASSES as $facade) {
            $facade::fake();
        }

        return $this->dteFake = new DteFake;
    }

    /**
     * Configure a builder with issuer, receiver, and items.
     *
     * @template TBuilder of DocumentBuilder
     *
     * @param  TBuilder  $builder
     * @return TBuilder
     */
    protected function configureBuilder(
        DocumentBuilder $builder,
        ?IssuerData $issuer = null,
        ReceiverData|string|null $receiver = null,
        array $items = [],
    ): DocumentBuilder {
        if ($issuer) {
            $builder->issuedBy($issuer);
        }

        if ($receiver) {
            $builder->receivedBy(
                $receiver instanceof ReceiverData
                    ? $receiver
                    : ReceiverData::make($receiver, 'Customer')
            );
        }

        foreach ($items as $item) {
            $builder->addItem(is_array($item) ? Item::make(...$item) : $item);
        }

        return $builder;
    }

    /**
     * Create a pre-configured fake invoice builder.
     */
    protected function newInvoice(
        ?IssuerData $issuer = null,
        ReceiverData|string|null $receiver = null,
        array $items = [],
    ): FakeInvoiceBuilder {
        return $this->configureBuilder(SiiInvoice::fake(), $issuer, $receiver, $items);
    }

    /**
     * Create a pre-configured fake receipt builder.
     */
    protected function newReceipt(
        ?IssuerData $issuer = null,
        ReceiverData|string|null $receiver = null,
        array $items = [],
    ): FakeReceiptBuilder {
        return $this->configureBuilder(SiiReceipt::fake(), $issuer, $receiver, $items);
    }

    /**
     * Create a pre-configured fake credit note builder.
     */
    protected function newCreditNote(
        ?IssuerData $issuer = null,
        ReceiverData|string|null $receiver = null,
        array $items = [],
    ): FakeCreditNoteBuilder {
        return $this->configureBuilder(SiiCreditNote::fake(), $issuer, $receiver, $items);
    }

    /**
     * Create a pre-configured fake debit note builder.
     */
    protected function newDebitNote(
        ?IssuerData $issuer = null,
        ReceiverData|string|null $receiver = null,
        array $items = [],
    ): FakeDebitNoteBuilder {
        return $this->configureBuilder(SiiDebitNote::fake(), $issuer, $receiver, $items);
    }

    /**
     * Create a pre-configured fake dispatch guide builder.
     */
    protected function newDispatchGuide(
        ?IssuerData $issuer = null,
        ReceiverData|string|null $receiver = null,
        array $items = [],
    ): FakeDispatchGuideBuilder {
        return $this->configureBuilder(SiiDispatchGuide::fake(), $issuer, $receiver, $items);
    }

    /**
     * Create a pre-configured fake purchase invoice builder.
     */
    protected function newPurchaseInvoice(
        ?IssuerData $issuer = null,
        ReceiverData|string|null $receiver = null,
        array $items = [],
    ): FakePurchaseInvoiceBuilder {
        return $this->configureBuilder(SiiPurchaseInvoice::fake(), $issuer, $receiver, $items);
    }

    /**
     * Create a pre-configured fake invoice liquidation builder.
     */
    protected function newInvoiceLiquidation(
        ?IssuerData $issuer = null,
        ReceiverData|string|null $receiver = null,
        array $items = [],
    ): FakeInvoiceLiquidationBuilder {
        return $this->configureBuilder(SiiInvoiceLiquidation::fake(), $issuer, $receiver, $items);
    }

    /**
     * Set up PDF test stubs with a minimal TED stub.
     */
    protected function withPdfStubs(SiiDte $dte, string $stubXml = 'stub_ted.xml'): void
    {
        // This allows testing the Blade view without running the full compilation pipeline.
        $this->mock(Pdf417Encoder::class, static function (MockInterface $mock): void {
            $mock->shouldReceive('generate')->andReturn('data:image/png;base64,iVBORw0KGgoAAAANSUhEUg=');
        });

        // The DTE must already have been created (with sync: true) or have its payload set.
        if ($dte->payload && empty($dte->payload->xml)) {
            $dte->payload->xml = static::getStub($stubXml);
        }
    }

    /**
     * Assert the expected number of DTE documents were created.
     */
    protected function assertDteCreated(int|DteType|null $type = null, ?int $times = null): void
    {
        if ($this->dteFake === null) {
            throw new LogicException('Call fakeDte() before asserting.');
        }

        if ($type !== null) {
            $this->dteFake->assertCreatedFor($type, $times);

            return;
        }

        $this->dteFake->assertCreated($times);
    }

    /**
     * Assert the DTE payload contains the expected key-value pairs.
     *
     * @param  array<string, mixed>  $expected
     */
    protected function assertDtePayload(SiiDte $dte, array $expected): void
    {
        foreach ($expected as $key => $value) {
            $this->assertSame($value, data_get($dte->payload->data->toArray(), $key));
        }
    }
}
