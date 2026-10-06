<?php

namespace Tests\Unit\Builders;

use DateTimeImmutable;
use Laragear\Dte\Builders\DispatchGuideBuilder;
use Laragear\Dte\Builders\DocumentBuilder;
use Laragear\Dte\Builders\ReceiptBuilder;
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Data\CompanyData;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\SiiRut;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use RuntimeException;
use Tests\DatabaseTestCase;
use Tests\Unit\Builders\Fixtures\BuilderFixture;

class DocumentTypesTest extends DatabaseTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        ConfigurationManager::setCompany(fn() => CompanyData::make(
            IssuerData::make(
                '76.123.456-0',
                'Test Company',
                'Software',
                ['620100'],
                'Test Address 123',
                'Santiago',
                '2025-01-01',
                76000,
                'Santiago',
                '+56212345678',
                'test@example.com',
                'Casa Matriz',
            ),
            '76.123.456-0',
        ));
    }

    /*
     |--------------------------------------------------------------------------
     | Happy Paths
     |--------------------------------------------------------------------------
     */

    /** @param  class-string<DocumentBuilder>  $builderClass */
    #[DataProviderExternal(BuilderFixture::class, 'builders')]
    public function test_creates_each_architecture_document_type(string $builderClass, DteType $documentType): void
    {
        $builder = $this->configuredBuilder($builderClass, $builderClass !== ReceiptBuilder::class);

        if (method_exists($builder, 'addReference')) {
            $builder->addReference(BuilderFixture::reference());
        }

        $dte = $builder->build();

        static::assertSame($documentType, $dte->document_type);
        static::assertSame(DteStatus::Pending, $dte->status);
        static::assertSame($documentType->value, $dte->payload->header_id_doc['document_type']);

        if (method_exists($builder, 'addReference')) {
            static::assertCount(1, $dte->payload->references['items']);
            static::assertSame(DteType::Invoice->value, $dte->payload->references['items'][0]['document_type']);
            static::assertSame('100', $dte->payload->references['items'][0]['folio']);
            static::assertSame('2026-08-01', $dte->payload->references['items'][0]['date']);
        }
    }

    public function test_receipt_uses_gross_prices_and_the_anonymous_consumer_rut(): void
    {
        $dte = $this->configuredBuilder(ReceiptBuilder::class, false)->build();

        static::assertSame(1513, $dte->amount_net);
        static::assertSame(287, $dte->amount_taxes);
        static::assertSame(1800, $dte->amount_total);
        static::assertSame(SiiRut::Consumer->formatRaw(), $dte->receiver_rut->formatRaw());
        static::assertSame(SiiRut::Consumer->formatRaw(), $dte->payload->header_receiver['rut']);
        static::assertSame('Sin nombre', $dte->payload->header_receiver['name']);
    }

    public function test_dispatch_guide_persists_transport_input(): void
    {
        $builder = $this->configuredBuilder(DispatchGuideBuilder::class);

        if (!$builder instanceof DispatchGuideBuilder) {
            throw new RuntimeException('Unable to resolve the dispatch guide builder.');
        }

        $carrier = BuilderFixture::issuer()->rut;
        $driver = BuilderFixture::receiver()->rut;
        $builder
            ->withVehicle('ABCD12', 'WXYZ34')
            ->withCarrier($carrier)
            ->withDriver($driver, 'John Driver')
            ->toDestination('Main Street 123', 'Santiago')
            ->withTransportSchedule(new DateTimeImmutable('2026-08-13 09:30:00'));

        $transport = $builder->build()->payload->header_transport->toArray();

        static::assertSame('ABCD12', $transport['vehicle_plate']);
        static::assertSame($carrier->formatRaw(), $transport['carrier_rut']);
        static::assertSame($driver->formatRaw(), $transport['driver_rut']);
        static::assertSame('2026-08-13 09:30:00', $transport['departure_at']);
    }

    public function test_dispatch_guide_persists_transport_input_without_dates(): void
    {
        $builder = $this->configuredBuilder(DispatchGuideBuilder::class);

        if (!$builder instanceof DispatchGuideBuilder) {
            throw new RuntimeException('Unable to resolve the dispatch guide builder.');
        }

        $builder->withVehicle('ABCD12', 'WXYZ34');

        $transport = $builder->build()->payload->header_transport->toArray();

        static::assertSame('ABCD12', $transport['vehicle_plate']);
        static::assertNull($transport['departure_at']);
        static::assertNull($transport['arrival_at']);
    }

    /*
     |--------------------------------------------------------------------------
     | Sad Paths
     |--------------------------------------------------------------------------
     */

    /** @param  class-string<DocumentBuilder>  $builderClass */
    #[DataProviderExternal(BuilderFixture::class, 'noteBuilders')]
    public function test_notes_throw_when_reference_is_missing(string $builderClass, string $message): void
    {
        $builder = $this->configuredBuilder($builderClass);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs($message);

        $builder->build();
    }

    public function test_receipt_throws_when_containing_exempt_items(): void
    {
        $builder = $this->configuredBuilder(ReceiptBuilder::class, false)
            ->addItem(Item::make('Exempt', 100, exempt: true));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('Electronic receipt type 39 cannot contain exempt items.');

        $builder->build();
    }

    /**
     * Return a builder with common valid inputs.
     *
     * @param  class-string<DocumentBuilder>  $builderClass
     */
    protected function configuredBuilder(string $builderClass, bool $withReceiver = true): DocumentBuilder
    {
        $builder = $this->app
            ->make($builderClass)
            ->issuedBy(BuilderFixture::issuer())
            ->addItem(BuilderFixture::item());

        if ($withReceiver) {
            $builder->receivedBy(BuilderFixture::receiver());
        }

        return $builder;
    }
}
