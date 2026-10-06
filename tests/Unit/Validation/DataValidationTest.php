<?php

namespace Tests\Unit\Validation;

use Illuminate\Validation\ValidationException;
use Laragear\Dte\Casts\DteHeaderTotals;
use Laragear\Dte\Data\GlobalModifierData;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Data\PaymentTermData;
use Laragear\Dte\Data\ReceiverData;
use Laragear\Dte\Data\ReferenceData;
use Laragear\Dte\Data\TransportData;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Validation\DteRules;
use Tests\TestCase;

use function str_repeat;

class DataValidationTest extends TestCase
{
    public function test_rules_are_taken_from_the_generated_const(): void
    {
        static::assertSame(DteRules::RECEIVER, ReceiverData::rules());
        static::assertSame(DteRules::ITEM, Item::rules());
        static::assertSame(DteRules::REFERENCE, ReferenceData::rules());
        static::assertSame(DteRules::GLOBAL_MODIFIER, GlobalModifierData::rules());
        static::assertSame(DteRules::PAYMENT_TERM, PaymentTermData::rules());
        static::assertSame(DteRules::TRANSPORT, TransportData::rules());
        static::assertSame(DteRules::TOTALS, DteHeaderTotals::RULES);

        static::assertSame(DteRules::ISSUER['rut'], IssuerData::rules()['rut']);
        static::assertIsCallable(IssuerData::rules()['activity_code']);
    }

    public function test_valid_data_passes(): void
    {
        $this->issuer('620200')->validate();
        $this->issuer(['620100', '620200'])->validate();

        static::assertTrue(true);
    }

    public function test_valid_receiver_passes(): void
    {
        ReceiverData::make('76.987.654-5', 'Receiver Corp')->validate();

        static::assertTrue(true);
    }

    public function test_valid_item_passes(): void
    {
        (new Item(name: 'Item', unitPrice: 1000.0, quantity: 2.0))->validate();

        static::assertTrue(true);
    }

    public function test_valid_reference_passes(): void
    {
        ReferenceData::make(DteType::Invoice, '100', '2026-08-15', 'Anula documento', 1)->validate();

        static::assertTrue(true);
    }

    public function test_valid_global_modifier_passes(): void
    {
        GlobalModifierData::make('D', '%', 10, 0, 'Descuento 10%')->validate();

        static::assertTrue(true);
    }

    public function test_valid_payment_term_passes(): void
    {
        PaymentTermData::make('2', '2026-09-13')->validate();

        static::assertTrue(true);
    }

    public function test_valid_transport_passes(): void
    {
        TransportData::make('AB1234', null, '76.123.456-0', '76.987.654-5', 'Driver')->validate();

        static::assertTrue(true);
    }

    public function test_valid_totals_pass(): void
    {
        DteHeaderTotals::make(['net' => 1000, 'exempt' => 0, 'tax' => 190, 'total' => 1190])->validate();

        static::assertTrue(true);
    }

    public function test_zero_quantity_tax_only_item_passes(): void
    {
        (new Item(name: 'Retention', unitPrice: 100.0, quantity: 0.0, taxes: [15 => 1710]))->validate();

        static::assertTrue(true);
    }

    public function test_issuer_failures(): void
    {
        $this->expectErrorKey('name', fn () => $this->issuer('620200', str_repeat('A', 101))->validate());
        $this->expectErrorKey('rut', fn () => IssuerData::make(
            '76.123.456-7', 'Test Corp', 'Software', '620200', 'Main St', 'Santiago', '2024-01-01', 1,
        )->validate());
        $this->expectErrorKey('resolution_date', fn () => IssuerData::make(
            '76.123.456-0', 'Test Corp', 'Software', '620200', 'Main St', 'Santiago', '1999-01-01', 1,
        )->validate());
        $this->expectErrorKey('activity_code', fn () => $this->issuer([
            '620100', '620200', '620300', '620400', '620500',
        ])->validate());
        $this->expectErrorKey('activity_code', fn () => $this->issuer('abc')->validate());
    }

    public function test_item_failures(): void
    {
        $this->expectErrorKey('name', fn () => (new Item(str_repeat('A', 81), 100.0))->validate());
        $this->expectErrorKey('quantity', fn () => (new Item('Item', 100.0, -1.0))->validate());
    }

    public function test_reference_failures(): void
    {
        $this->expectErrorKey('folio', fn () => ReferenceData::make(DteType::Invoice, str_repeat('F', 19))->validate());
        $this->expectErrorKey('reference_code', fn () => ReferenceData::make(
            DteType::Invoice, '100', referenceCode: 4,
        )->validate());
    }

    public function test_global_modifier_failures(): void
    {
        $this->expectErrorKey('type', fn () => GlobalModifierData::make('X', '%', 10)->validate());
        $this->expectErrorKey('value_type', fn () => GlobalModifierData::make('D', '#', 10)->validate());
        $this->expectErrorKey('value', fn () => GlobalModifierData::make('D', '%', 0)->validate());
        $this->expectErrorKey('target', fn () => GlobalModifierData::make('D', '%', 10, 9)->validate());
    }

    public function test_payment_term_failure(): void
    {
        $this->expectErrorKey('condition', fn () => PaymentTermData::make('Credit', '2026-09-13')->validate());
    }

    public function test_transport_failures(): void
    {
        $this->expectErrorKey('vehicle_plate', fn () => TransportData::make('TOOLONGPL')->validate());
        $this->expectErrorKey('carrier_rut', fn () => TransportData::make(carrierRut: '76.123.456-7')->validate());
    }

    public function test_totals_failures(): void
    {
        $this->expectErrorKey('total', fn () => DteHeaderTotals::make(['net' => 1000, 'exempt' => 0, 'tax' => 190, 'total' => -1])->validate());
        $this->expectErrorKey('net', fn () => DteHeaderTotals::make(['net' => -5, 'exempt' => 0, 'tax' => 190, 'total' => 1190])->validate());
    }

    public function test_receiver_failure(): void
    {
        $this->expectErrorKey('name', fn () => ReceiverData::make('76.987.654-5', str_repeat('A', 101))->validate());
    }

    private function expectErrorKey(string $key, callable $callback): void
    {
        try {
            $callback();
            static::fail('Expected validation to fail on: '.$key);
        } catch (ValidationException $e) {
            static::assertArrayHasKey($key, $e->errors(), 'Missing error key: '.$key);
        }
    }

    protected function issuer(string|array $activityCode = '620200', ?string $name = null): IssuerData
    {
        return IssuerData::make(
            '76.123.456-0', $name ?? 'Test Corp', 'Software', $activityCode,
            'Main St', 'Santiago', '2024-01-01', 1,
        );
    }
}
