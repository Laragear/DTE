<?php

namespace Tests\Unit\Builders\Concerns;

use DateTimeImmutable;
use InvalidArgumentException;
use Laragear\Dte\Contracts\Itemable;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Data\ReferenceData;
use Laragear\Dte\Enums\DteType;
use Laragear\Rut\Facades\Generator;
use Laragear\Rut\Rut;
use Mockery;
use Mockery\MockInterface;
use OverflowException;
use Tests\TestCase;
use Tests\Unit\Builders\Concerns\Fixtures\DummyBuilder;

class BuilderConcernsTest extends TestCase
{
    /*
     |--------------------------------------------------------------------------
     | Happy Paths
     |--------------------------------------------------------------------------
     */

    /**
     * Return the expected configured transport data.
     *
     * @return array<string, mixed>
     */
    protected function transportData(
        Rut $carrier,
        Rut $driver,
        DateTimeImmutable $departure,
        DateTimeImmutable $arrival,
    ): array {
        return [
            'vehicle_plate' => 'ABCD12',
            'trailer_plate' => 'WXYZ34',
            'carrier' => $carrier,
            'driver' => $driver,
            'driver_name' => 'John Driver',
            'destination_address' => 'Main Street 123',
            'destination_commune' => 'Santiago',
            'destination_city' => 'Santiago',
            'departure_at' => $departure,
            'arrival_at' => $arrival,
        ];
    }

    public function test_items_are_fluent_and_calculate_net_iva_exempt_and_total_amounts(): void
    {
        $builder = new DummyBuilder;
        $taxable = Item::make('Taxable service', 1000, quantity: 2, discountPercentage: 10);
        $exempt = Item::make('Exempt service', 500, quantity: 3, exempt: true);

        static::assertSame($builder, $builder->addItem($taxable));
        static::assertSame($builder, $builder->addItem($exempt));
        static::assertSame([$taxable, $exempt], $builder->items());
        static::assertSame(1800, $builder->itemAmount($taxable));
        static::assertSame(['net' => 1800, 'exempt' => 1500, 'tax' => 342, 'total' => 3642], $builder->totals());
    }

    public function test_monetary_results_are_rounded_to_chilean_pesos(): void
    {
        $builder = (new DummyBuilder)->addItem(Item::make('Rounded service', 100.5));

        static::assertSame(101, $builder->netAmount());
        static::assertSame(19, $builder->taxAmount());
        static::assertSame(120, $builder->totalAmount());
    }

    public function test_tax_amount_uses_configured_iva_rate(): void
    {
        $this->config('dte.taxes.iva_rate', 21);

        $builder = (new DummyBuilder)->addItem(Item::make('Rounded service', 100.5));

        static::assertSame(101, $builder->netAmount());
        static::assertSame(21, $builder->taxAmount());
    }

    public function test_references_are_added_fluently_in_order(): void
    {
        $builder = new DummyBuilder;
        $first = ReferenceData::make(DteType::Invoice, '100', new DateTimeImmutable('2026-08-01'));
        $second = ReferenceData::make(DteType::DispatchGuide, '200', new DateTimeImmutable('2026-08-02'));

        static::assertSame($builder, $builder->addReference($first));
        static::assertSame($builder, $builder->addReference($second));
        static::assertSame([$first, $second], $builder->references());
    }

    public function test_transport_parameters_are_configured_fluently(): void
    {
        $builder = new DummyBuilder;
        $carrier = Generator::asCompanies()->makeOne();
        $driver = Generator::asPeople()->makeOne();
        $departure = new DateTimeImmutable('2026-08-13 09:30:00');
        $arrival = new DateTimeImmutable('2026-08-13 11:00:00');

        static::assertSame($builder, $builder->withVehicle('ABCD12', 'WXYZ34'));
        static::assertSame($builder, $builder->withCarrier($carrier));
        static::assertSame($builder, $builder->withDriver($driver, 'John Driver'));
        static::assertSame($builder, $builder->toDestination('Main Street 123', 'Santiago', 'Santiago'));
        static::assertSame($builder, $builder->withTransportSchedule($departure, $arrival));
        static::assertSame($this->transportData($carrier, $driver, $departure, $arrival), $builder->transport());
    }

    public function test_global_tax_exemption_and_override_are_fluent(): void
    {
        $builder = new DummyBuilder;

        static::assertFalse($builder->isTaxExempt());
        static::assertSame($builder, $builder->markAsTaxExempt(5000));
        static::assertTrue($builder->isTaxExempt());
        static::assertSame(5000, $builder->exemptAmountOverride());
    }

    public function test_receives_itemable_as_item(): void
    {
        $fixture = Item::make('Taxable service', 1000, quantity: 2, discountPercentage: 10);

        $itemable = Mockery::mock(Itemable::class, static function (MockInterface $mock) use ($fixture): void {
            $mock->expects('toItem')->andReturn($fixture);
        });

        $builder = new DummyBuilder;
        $builder->addItem($itemable);

        static::assertSame($fixture, $builder->items()[0]);
    }

    public function test_a_document_cannot_exceed_sixty_item_lines(): void
    {
        $builder = new DummyBuilder;

        foreach (range(1, 60) as $line) {
            $builder->addItem(Item::make("Item $line", 1000));
        }

        $this->expectException(OverflowException::class);
        $this->expectExceptionMessageIs('A DTE cannot contain more than 60 item lines.');

        $builder->addItem(Item::make('Item 61', 1000));
    }

    public function test_a_document_cannot_exceed_forty_references(): void
    {
        $builder = new DummyBuilder;

        foreach (range(1, 40) as $folio) {
            $builder->addReference(ReferenceData::make(
                DteType::Invoice,
                (string) $folio,
                new DateTimeImmutable('2026-08-01'),
            ));
        }

        $this->expectException(OverflowException::class);
        $this->expectExceptionMessageIs('A DTE cannot contain more than 40 references.');

        $builder->addReference(ReferenceData::make(DteType::Invoice, '41', new DateTimeImmutable('2026-08-01')));
    }

    public function test_item_discounts_must_be_valid_percentages(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The item discount percentage must be between 0 and 100.');

        (new DummyBuilder)->addItem(Item::make('Invalid discount', 1000, discountPercentage: 101));
    }

    public function test_exempt_amount_override_cannot_be_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The exempt amount override cannot be negative.');

        (new DummyBuilder)->markAsTaxExempt(-1);
    }
}
