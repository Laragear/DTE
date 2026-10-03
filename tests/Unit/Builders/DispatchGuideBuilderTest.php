<?php

namespace Tests\Unit\Builders;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laragear\Dte\Builders\DispatchGuideBuilder;
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Data\CompanyData;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Data\ReceiverData;
use Laragear\Dte\Enums\DteType;
use Laragear\Rut\Rut;
use Override;
use Tests\DatabaseTestCase;
use Tests\Unit\Builders\Fixtures\BuilderFixture;

class DispatchGuideBuilderTest extends DatabaseTestCase
{
    use RefreshDatabase;

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

    public function test_additional_data_includes_transport_destination_fields(): void
    {
        $queue = Queue::fake();

        // Line 42: includes transport destination fields in additionalData
        $builder = $this->app->make(DispatchGuideBuilder::class);

        $builder->issuedBy(IssuerData::make(
            rut: Rut::parse('11111111-1'),
            name: 'Test Company',
            activity: 'Test',
            activityCode: '620100',
            address: 'Dir',
            commune: 'Com',
            resolutionDate: '2025-01-01',
            resolutionNumber: 80,
        ));

        $builder->receivedBy(ReceiverData::make(
            rut: Rut::parse('22222222-2'),
            name: 'Client',
            activity: 'Activity',
            address: 'Address',
            commune: 'Commune',
        ));

        $builder->addItem(Item::make(
            name: 'Product',
            unitPrice: 10000,
            quantity: 1,
        ));

        $builder
            ->withVehicle('ABCD12', 'EFGH34')
            ->withCarrier(Rut::parse('33333333-3'))
            ->withDriver(Rut::parse('44444444-4'), 'Juan Driver')
            ->toDestination('Av. Siempre Viva 123', 'Santiago', 'Santiago')
            ->withTransportSchedule(
                Carbon::parse('2024-01-15 08:00:00')->toDateTimeImmutable(),
                Carbon::parse('2024-01-15 10:00:00')->toDateTimeImmutable(),
            );

        $dte = $builder->build();
        $data = $dte->payload->data;

        static::assertArrayHasKey('transport', $data);
        static::assertEquals('ABCD12', $data['transport']['vehicle_plate']);
        static::assertEquals('EFGH34', $data['transport']['trailer_plate']);
        static::assertEquals('333333333', $data['transport']['carrier_rut']);
        static::assertEquals('444444444', $data['transport']['driver_rut']);
        static::assertEquals('Juan Driver', $data['transport']['driver_name']);
        static::assertEquals('Av. Siempre Viva 123', $data['transport']['destination_address']);
        static::assertEquals('Santiago', $data['transport']['destination_commune']);
        static::assertEquals('Santiago', $data['transport']['destination_city']);
        static::assertEquals('2024-01-15 08:00:00', $data['transport']['departure_at']);
        static::assertEquals('2024-01-15 10:00:00', $data['transport']['arrival_at']);
    }

    public function test_additional_data_with_partial_transport(): void
    {
        $builder = $this->app->make(DispatchGuideBuilder::class);

        $builder->issuedBy(IssuerData::make(
            rut: Rut::parse('11111111-1'),
            name: 'Test Company',
            activity: 'Test',
            activityCode: '620100',
            address: 'Dir',
            commune: 'Com',
            resolutionDate: '2025-01-01',
            resolutionNumber: 80,
        ));

        $builder->receivedBy(ReceiverData::make(
            rut: Rut::parse('22222222-2'),
            name: 'Client',
            activity: 'Activity',
            address: 'Address',
            commune: 'Commune',
        ));

        $builder->addItem(Item::make(
            name: 'Product',
            unitPrice: 10000,
            quantity: 1,
        ));

        $builder->withVehicle('ABCD12');
        $builder->toDestination('Av. Siempre Viva 123', '');

        $dte = $builder->build();
        $data = $dte->payload->data;

        static::assertArrayHasKey('transport', $data);
        static::assertEquals('ABCD12', $data['transport']['vehicle_plate']);
        static::assertNull($data['transport']['trailer_plate']);
        static::assertNull($data['transport']['carrier_rut']);
        static::assertNull($data['transport']['driver_rut']);
        static::assertNull($data['transport']['driver_name']);
        static::assertEquals('Av. Siempre Viva 123', $data['transport']['destination_address']);
        static::assertEquals('', $data['transport']['destination_commune']);
        static::assertNull($data['transport']['destination_city']);
        static::assertNull($data['transport']['departure_at']);
        static::assertNull($data['transport']['arrival_at']);
    }

    public function test_document_type_is_dispatch_guide(): void
    {
        $builder = $this->app->make(DispatchGuideBuilder::class);
        static::assertEquals(DteType::DispatchGuide, $builder->documentType());
    }

    public function test_sets_transfer_motive_and_dispatch_type(): void
    {
        Queue::fake();

        $builder = $this->app->make(DispatchGuideBuilder::class);
        $builder->issuedBy(BuilderFixture::issuer());
        $builder->receivedBy(BuilderFixture::receiver());
        $builder->addItem(BuilderFixture::item());
        $builder->transferMotive(1);
        $builder->dispatchType(2);

        $dte = $builder->build();
        static::assertSame(1, $dte->payload->data['ind_traslado']);
        static::assertSame(2, $dte->payload->data['tipo_despacho']);
    }
}
