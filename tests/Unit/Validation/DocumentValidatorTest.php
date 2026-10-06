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
        $payload['detail_items']['items'][0]['quantity'] = 0.0;

        (new DocumentValidator)->validate($payload);

        static::assertTrue(true);
    }

    public function test_block_rules_only_apply_when_present(): void
    {
        $payload = $this->payload();

        unset($payload['header_id_doc']['payment'], $payload['header_transport']);

        (new DocumentValidator)->validate($payload);

        $rules = (new DocumentValidator)->rules($payload);

        static::assertArrayNotHasKey('header_transport.vehicle_plate', $rules);
        static::assertArrayNotHasKey('header_other_currency.currency', $rules);
    }

    public function test_document_failures(): void
    {
        $this->expectErrorKey('header_issuer', ['unset' => ['header_issuer']]);
        $this->expectErrorKey('header_id_doc.document_type', ['set' => ['header_id_doc.document_type' => 1]]);
        $this->expectErrorKey('header_id_doc.issued_on', ['set' => ['header_id_doc.issued_on' => '1999-12-31']]);
        $this->expectErrorKey('header_totals.net', ['set' => ['header_totals.net' => -1]]);
        $this->expectErrorKey('header_id_doc.ind_traslado', ['set' => ['header_id_doc.ind_traslado' => 99]]);
    }

    public function test_issuer_failures(): void
    {
        $this->expectErrorKey('header_issuer.rut', ['set' => ['header_issuer.rut' => '76.123.456-7']]);
        $this->expectErrorKey('header_issuer.name', ['set' => ['header_issuer.name' => str_repeat('A', 101)]]);
        $this->expectErrorKey('header_issuer.commune', ['set' => ['header_issuer.commune' => str_repeat('A', 21)]]);
        $this->expectErrorKey(
            'header_issuer.resolution_number',
            ['set' => ['header_issuer.resolution_number' => 1234567]],
        );
    }

    public function test_receiver_failures(): void
    {
        $this->expectErrorKey('header_receiver.rut', ['set' => ['header_receiver.rut' => '76.987.654-3']]);
    }

    public function test_item_failures(): void
    {
        $this->expectErrorKey('detail_items.items.0.name', ['unset' => ['detail_items.items.0.name']]);
        $this->expectErrorKey('detail_items.items.0.quantity', ['set' => ['detail_items.items.0.quantity' => -1.0]]);
    }

    public function test_reference_and_modifier_failures(): void
    {
        $this->expectErrorKey(
            'references.items.0.folio',
            ['set' => ['references' => ['items' => [['document_type' => 33]]]]],
        );
        $this->expectErrorKey('global_modifiers.items.0.type', [
            'set' => ['global_modifiers' => ['items' => [['type' => 'X', 'value_type' => '%', 'value' => 10]]]],
        ]);
    }

    public function test_optional_block_failures(): void
    {
        $this->expectErrorKey(
            'header_id_doc.payment.condition',
            ['set' => ['header_id_doc.payment.condition' => 'Credit']],
        );
        $this->expectErrorKey(
            'header_transport.vehicle_plate',
            ['set' => ['header_transport.vehicle_plate' => 'TOOLONGPL']],
        );
        $this->expectErrorKey(
            'header_transport.carrier_rut',
            ['set' => ['header_transport.carrier_rut' => '76.123.456-7']],
        );
    }

    public function test_rules_prefix_every_block_with_its_column(): void
    {
        $rules = (new DocumentValidator)->rules($this->payload());

        static::assertArrayHasKey('header_issuer.rut', $rules);
        static::assertArrayHasKey('header_receiver.name', $rules);
        static::assertArrayHasKey('detail_items.items.*.name', $rules);
        static::assertArrayHasKey('references.items.*.folio', $rules);
        static::assertArrayHasKey('global_modifiers.items.*.value', $rules);
        static::assertArrayHasKey('header_totals.total', $rules);
        static::assertArrayHasKey('header_id_doc.payment.condition', $rules);
        static::assertArrayHasKey('header_transport.vehicle_plate', $rules);
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
     * @return array<string, array<string, mixed>|null>
     */
    protected function payload(): array
    {
        return [
            'header_id_doc' => [
                'document_type' => 33,
                'issued_on' => '2026-08-15',
                'payment' => ['condition' => '2', 'expiration_date' => '2026-09-13'],
            ],
            'header_issuer' => [
                'rut' => '761234560',
                'name' => 'Test Company',
                'activity' => 'Software',
                'activity_code' => ['620200'],
                'address' => 'Main St',
                'commune' => 'Santiago',
                'resolution_date' => '2025-01-01',
                'resolution_number' => 1,
            ],
            'header_receiver' => [
                'rut' => '769876545',
                'name' => 'Receiver Corp',
            ],
            'header_transport' => ['vehicle_plate' => 'AB1234'],
            'header_totals' => ['taxes' => [], 'net' => 1000, 'exempt' => 0, 'tax' => 190, 'total' => 1190],
            'detail_items' => [
                'items' => [
                    ['name' => 'Item', 'unit_price' => 1000.0, 'quantity' => 1.0, 'exempt' => false],
                ],
            ],
            'references' => ['items' => []],
            'global_modifiers' => ['items' => []],
        ];
    }
}
