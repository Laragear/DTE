<?php

namespace Tests\Unit\Validation;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Laragear\Dte\Validation\DocumentValidator;
use Tests\TestCase;

use function str_repeat;

class DocumentValidatorTest extends TestCase
{
    public function test_valid_payload_passes(): void
    {
        (new DocumentValidator)->validate($this->payload());

        static::assertTrue(true);
    }

    public function test_zero_quantity_item_passes(): void
    {
        $payload = $this->payload();
        $payload['items'][0]['quantity'] = 0.0;

        (new DocumentValidator)->validate($payload);

        static::assertTrue(true);
    }

    public function test_optional_section_rules_only_apply_when_present(): void
    {
        $payload = $this->payload();

        unset($payload['payment'], $payload['transport']);

        (new DocumentValidator)->validate($payload);

        static::assertArrayNotHasKey('payment.condition', (new DocumentValidator)->rules($payload));
        static::assertArrayNotHasKey('transport.vehicle_plate', (new DocumentValidator)->rules($payload));
    }

    public function test_document_failures(): void
    {
        $this->expectErrorKey('issuer', ['unset' => ['issuer']]);
        $this->expectErrorKey('document_type', ['set' => ['document_type' => 1]]);
        $this->expectErrorKey('issued_on', ['set' => ['issued_on' => '1999-12-31']]);
        $this->expectErrorKey('totals.net', ['set' => ['totals.net' => -1]]);
        $this->expectErrorKey('ind_traslado', ['set' => ['ind_traslado' => 99]]);
    }

    public function test_issuer_failures(): void
    {
        $this->expectErrorKey('issuer.rut', ['set' => ['issuer.rut' => '76.123.456-7']]);
        $this->expectErrorKey('issuer.name', ['set' => ['issuer.name' => str_repeat('A', 101)]]);
        $this->expectErrorKey('issuer.commune', ['set' => ['issuer.commune' => str_repeat('A', 21)]]);
        $this->expectErrorKey('issuer.resolution_number', ['set' => ['issuer.resolution_number' => 1234567]]);
    }

    public function test_receiver_failures(): void
    {
        $this->expectErrorKey('receiver.rut', ['set' => ['receiver.rut' => '76.987.654-3']]);
    }

    public function test_item_failures(): void
    {
        $this->expectErrorKey('items.0.name', ['unset' => ['items.0.name']]);
        $this->expectErrorKey('items.0.quantity', ['set' => ['items.0.quantity' => -1.0]]);
    }

    public function test_reference_and_modifier_failures(): void
    {
        $this->expectErrorKey('references.0.folio', ['set' => ['references' => [['document_type' => 33]]]]);
        $this->expectErrorKey('global_modifiers.0.type', [
            'set' => ['global_modifiers' => [['type' => 'X', 'value_type' => '%', 'value' => 10]]],
        ]);
    }

    public function test_optional_section_failures(): void
    {
        $this->expectErrorKey('payment.condition', ['set' => ['payment.condition' => 'Credit']]);
        $this->expectErrorKey('transport.vehicle_plate', ['set' => ['transport.vehicle_plate' => 'TOOLONGPL']]);
        $this->expectErrorKey('transport.carrier_rut', ['set' => ['transport.carrier_rut' => '76.123.456-7']]);
    }

    public function test_rules_prefix_every_section_with_its_payload_path(): void
    {
        $rules = (new DocumentValidator)->rules($this->payload());

        static::assertArrayHasKey('issuer.rut', $rules);
        static::assertArrayHasKey('receiver.name', $rules);
        static::assertArrayHasKey('items.*.name', $rules);
        static::assertArrayHasKey('references.*.folio', $rules);
        static::assertArrayHasKey('global_modifiers.*.value', $rules);
        static::assertArrayHasKey('totals.total', $rules);
        static::assertArrayHasKey('payment.condition', $rules);
        static::assertArrayHasKey('transport.vehicle_plate', $rules);
    }

    /**
     * @param  array{set?: array<string, mixed>, unset?: list<string>}  $mutation
     */
    private function expectErrorKey(string $key, array $mutation): void
    {
        $payload = $this->payload();

        foreach ($mutation['set'] ?? [] as $path => $value) {
            Arr::set($payload, $path, $value);
        }

        foreach ($mutation['unset'] ?? [] as $path) {
            Arr::forget($payload, $path);
        }

        try {
            (new DocumentValidator)->validate($payload);
            static::fail('Expected validation to fail on: '.$key);
        } catch (ValidationException $e) {
            static::assertArrayHasKey($key, $e->errors(), 'Missing error key: '.$key);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return [
            'document_type' => 33,
            'issued_on' => '2026-08-15',
            'issuer' => [
                'rut' => '761234560',
                'name' => 'Test Company',
                'activity' => 'Software',
                'activity_code' => ['620200'],
                'address' => 'Main St',
                'commune' => 'Santiago',
                'resolution_date' => '2025-01-01',
                'resolution_number' => 1,
            ],
            'receiver' => [
                'rut' => '769876545',
                'name' => 'Receiver Corp',
            ],
            'items' => [
                ['name' => 'Item', 'unit_price' => 1000.0, 'quantity' => 1.0, 'exempt' => false],
            ],
            'references' => [],
            'global_modifiers' => [],
            'totals' => ['net' => 1000, 'exempt' => 0, 'tax' => 190, 'total' => 1190],
            'payment' => ['condition' => '2', 'expiration_date' => '2026-09-13'],
            'transport' => ['vehicle_plate' => 'AB1234'],
        ];
    }
}
