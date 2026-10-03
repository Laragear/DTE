<?php

namespace Tests\Unit\Data;

use DateTimeImmutable;
use Illuminate\Support\Collection;
use Laragear\Dte\Data\DocumentPayload;
use Laragear\Dte\Data\DocumentTotalsData;
use Laragear\Dte\Data\GlobalModifierData;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Data\PaymentTermData;
use Laragear\Dte\Data\ReceiverData;
use Laragear\Dte\Data\ReferenceData;
use Laragear\Dte\Data\TransportData;
use Laragear\Dte\Enums\DteType;
use LogicException;
use PHPUnit\Framework\TestCase;

class DocumentPayloadTest extends TestCase
{
    public function test_from_array_maps_typed_members(): void
    {
        $payload = DocumentPayload::fromArray($this->raw());

        static::assertSame(DteType::Invoice, $payload->document_type);
        static::assertInstanceOf(DateTimeImmutable::class, $payload->issued_on);
        static::assertSame('2026-08-13', $payload->issued_on->format('Y-m-d'));
        static::assertInstanceOf(IssuerData::class, $payload->issuer);
        static::assertInstanceOf(ReceiverData::class, $payload->receiver);
        static::assertInstanceOf(DocumentTotalsData::class, $payload->totals);
        static::assertInstanceOf(PaymentTermData::class, $payload->payment);
        static::assertNull($payload->transport);
        static::assertTrue($payload->tax_exempt === false);
        static::assertNull($payload->exempt_amount_override);
        static::assertNull($payload->ind_mnt_neto);
        static::assertNull($payload->ind_traslado);
        static::assertNull($payload->tipo_despacho);
    }

    public function test_items_references_modifiers_taxes_are_collections(): void
    {
        $payload = DocumentPayload::fromArray($this->raw());

        static::assertInstanceOf(Collection::class, $payload->items);
        static::assertContainsOnlyInstancesOf(Item::class, $payload->items);
        static::assertInstanceOf(Collection::class, $payload->references);
        static::assertContainsOnlyInstancesOf(ReferenceData::class, $payload->references);
        static::assertInstanceOf(Collection::class, $payload->global_modifiers);
        static::assertContainsOnlyInstancesOf(GlobalModifierData::class, $payload->global_modifiers);
        static::assertInstanceOf(Collection::class, $payload->taxes);
        static::assertSame(19, $payload->taxes->keys()->first());
        static::assertSame(1900, $payload->taxes->first());
    }

    public function test_array_access_stays_raw_for_bc(): void
    {
        $payload = DocumentPayload::fromArray($this->raw());

        static::assertSame('Consulting service', $payload['items'][0]['name']);
        static::assertSame(33, $payload['document_type']);
        static::assertSame('2026-08-13', $payload['issued_on']);
        static::assertTrue(isset($payload['totals']));
        static::assertFalse(isset($payload['missing']));
    }

    public function test_is_read_only(): void
    {
        $payload = DocumentPayload::fromArray($this->raw());

        $this->expectException(LogicException::class);

        $payload['items'] = [];
    }

    public function test_offset_unset_is_read_only(): void
    {
        $payload = DocumentPayload::fromArray($this->raw());

        $this->expectException(LogicException::class);

        unset($payload['items']);
    }

    public function test_missing_keys_use_safe_defaults(): void
    {
        $payload = DocumentPayload::fromArray(['resolution_date' => '2026-01', 'items' => []]);

        static::assertNull($payload->document_type);
        static::assertNull($payload->issued_on);
        static::assertNull($payload->issuer);
        static::assertNull($payload->receiver);
        static::assertTrue($payload->items->isEmpty());
        static::assertTrue($payload->references->isEmpty());
        static::assertTrue($payload->global_modifiers->isEmpty());
        static::assertTrue($payload->taxes->isEmpty());
        static::assertSame(0, $payload->totals->total);
        static::assertNull($payload->payment);
        static::assertNull($payload->transport);
    }

    public function test_malformed_dates_return_null(): void
    {
        $payload = DocumentPayload::fromArray(['issued_on' => 'not-a-date']);

        static::assertNull($payload->issued_on);
    }

    public function test_from_json_round_trip(): void
    {
        $raw = $this->raw();

        $payload = DocumentPayload::fromJson(json_encode($raw));

        static::assertSame($raw, $payload->toArray());
        static::assertSame(json_encode($raw), $payload->toJson());
    }

    public function test_transport_maps_to_dto(): void
    {
        $raw = $this->raw();
        $raw['ind_traslado'] = 1;
        $raw['tipo_despacho'] = 2;
        $raw['transport'] = [
            'vehicle_plate' => 'ABCD12',
            'trailer_plate' => null,
            'carrier_rut' => '76192083-9',
            'driver_rut' => null,
            'driver_name' => 'Juan Perez',
            'destination_address' => 'Av Siempre Viva 123',
            'destination_commune' => 'Santiago',
            'destination_city' => 'Santiago',
            'departure_at' => '2026-08-13 10:00:00',
            'arrival_at' => null,
        ];

        $payload = DocumentPayload::fromArray($raw);

        static::assertInstanceOf(TransportData::class, $payload->transport);
        static::assertSame('ABCD12', $payload->transport->vehiclePlate);
        static::assertSame(1, $payload->ind_traslado);
        static::assertSame(2, $payload->tipo_despacho);
    }

    public function test_magic_set_throws(): void
    {
        $payload = DocumentPayload::fromArray($this->raw());

        $this->expectException(LogicException::class);

        $payload->items = [];
    }

    public function test_magic_unset_throws(): void
    {
        $payload = DocumentPayload::fromArray($this->raw());

        $this->expectException(LogicException::class);

        unset($payload->items);
    }

    public function test_magic_isset_checks_typed_value(): void
    {
        $payload = DocumentPayload::fromArray($this->raw());

        static::assertTrue(isset($payload->totals));
        static::assertFalse(isset($payload->missing));
    }

    public function test_from_json_with_invalid_json_gives_empty_payload(): void
    {
        $payload = DocumentPayload::fromJson('not-json');

        static::assertSame([], $payload->toArray());
        static::assertNull($payload->document_type);
    }

    public function test_document_type_parses_from_instances_and_strings(): void
    {
        static::assertSame(
            DteType::Invoice, DocumentPayload::fromArray(['document_type' => DteType::Invoice])->document_type
        );
        static::assertSame(
            DteType::Invoice, DocumentPayload::fromArray(['document_type' => '33'])->document_type
        );
        static::assertNull(DocumentPayload::fromArray(['document_type' => ''])->document_type);
        static::assertNull(DocumentPayload::fromArray(['document_type' => 999])->document_type);
    }

    public function test_date_parses_from_instance_and_empty_string(): void
    {
        $date = new DateTimeImmutable('2026-08-13');

        static::assertSame($date, DocumentPayload::fromArray(['issued_on' => $date])->issued_on);
        static::assertNull(DocumentPayload::fromArray(['issued_on' => ''])->issued_on);
    }

    public function test_fallback_wraps_lists_and_returns_scalars(): void
    {
        $payload = DocumentPayload::fromArray(['custom_list' => [1, 2], 'custom_scalar' => 'x']);

        static::assertSame([1, 2], $payload->custom_list->all());
        static::assertSame('x', $payload->custom_scalar);
        static::assertSame('fallback', $payload->get('unknown_key', 'fallback'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function raw(): array
    {
        return [
            'document_type' => 33,
            'issued_on' => '2026-08-13',
            'issuer' => [
                'rut' => '76192083-9',
                'name' => 'Issuer LLC',
                'activity' => 'Servicios',
                'activity_code' => ['620200'],
                'address' => 'Calle 1',
                'commune' => 'Santiago',
                'resolution_date' => '2020-01-01',
                'resolution_number' => 1,
            ],
            'receiver' => [
                'rut' => '60803000-K',
                'name' => 'Customer Company LLC',
            ],
            'items' => [
                [
                    'name' => 'Consulting service',
                    'unit_price' => 10000,
                    'quantity' => 1,
                ],
            ],
            'references' => [
                [
                    'document_type' => 33,
                    'folio' => '100',
                    'date' => '2026-08-01',
                    'reason' => 'Corrige montos',
                    'reference_code' => 3,
                ],
            ],
            'global_modifiers' => [
                ['type' => 'D', 'value_type' => '%', 'value' => 10, 'target' => 0, 'description' => null],
            ],
            'taxes' => ['19' => 1900],
            'totals' => ['net' => 10000, 'exempt' => 0, 'tax' => 1900, 'total' => 11900, 'non_billable' => 0],
            'ind_mnt_neto' => null,
            'tax_exempt' => false,
            'exempt_amount_override' => null,
            'payment' => ['condition' => 'Credit', 'expiration_date' => '2026-09-13'],
        ];
    }
}
