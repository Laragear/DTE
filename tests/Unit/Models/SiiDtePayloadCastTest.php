<?php

namespace Tests\Unit\Models;

use Laragear\Dte\Casts\DteBlockCast;
use Laragear\Dte\Casts\DteDetailItems;
use Laragear\Dte\Casts\DteHeaderIdDoc;
use Laragear\Dte\Casts\DteHeaderIssuer;
use Laragear\Dte\Models\SiiDtePayload;
use Tests\DatabaseTestCase;

class SiiDtePayloadCastTest extends DatabaseTestCase
{
    public function test_blocks_cast_to_block_objects(): void
    {
        $payload = SiiDtePayload::factory()->create([
            'header_id_doc' => ['document_type' => 33, 'issued_on' => '2026-08-15'],
            'detail_items' => ['items' => [['name' => 'Service', 'unit_price' => 1000, 'quantity' => 1]]],
        ]);

        $payload->refresh();

        static::assertInstanceOf(DteHeaderIdDoc::class, $payload->header_id_doc);
        static::assertInstanceOf(DteHeaderIssuer::class, $payload->header_issuer);
        static::assertInstanceOf(DteDetailItems::class, $payload->detail_items);
        static::assertSame('Service', $payload->detail_items->items[0]['name']);
        static::assertSame(33, $payload->header_id_doc->document_type);
    }

    public function test_make_hydrates_block_objects_from_arrays(): void
    {
        $payload = SiiDtePayload::make([
            'header_id_doc' => ['document_type' => 33, 'issued_on' => '2026-08-15'],
            'header_issuer' => ['rut' => '761234560', 'name' => 'Test Company'],
            'detail_items' => ['items' => [['name' => 'Service', 'unit_price' => 1000, 'quantity' => 1]]],
        ]);

        static::assertInstanceOf(DteHeaderIdDoc::class, $payload->header_id_doc);
        static::assertInstanceOf(DteHeaderIssuer::class, $payload->header_issuer);
        static::assertSame('Test Company', $payload->header_issuer->name);
        static::assertSame('Service', $payload->detail_items->items[0]['name']);
    }

    public function test_blocks_accept_block_instances_on_write(): void
    {
        $payload = SiiDtePayload::factory()->create([
            'detail_items' => DteDetailItems::make(['items' => []]),
        ]);

        $payload->refresh();

        static::assertInstanceOf(DteDetailItems::class, $payload->detail_items);
        static::assertSame(['items' => []], $payload->detail_items->toArray());
    }

    public function test_empty_columns_hydrate_default_blocks(): void
    {
        $payload = SiiDtePayload::factory()->create();

        $payload->refresh();

        static::assertInstanceOf(DteDetailItems::class, $payload->detail_items);
        static::assertSame(['items' => []], $payload->detail_items->toArray());
        static::assertTrue($payload->header_transport->isEmpty());
        static::assertSame([], $payload->blocksToArray()['header_id_doc']);
    }

    public function test_cast_get_returns_instance_and_handles_invalid_values(): void
    {
        $cast = new DteBlockCast(DteDetailItems::class);
        $model = new SiiDtePayload;

        $instance = DteDetailItems::make(['items' => []]);

        static::assertSame($instance, $cast->get($model, 'detail_items', $instance, []));
        static::assertSame(['items' => []], $cast->get($model, 'detail_items', 'not-json', [])->toArray());
        static::assertSame(['items' => []], $cast->get($model, 'detail_items', 123, [])->toArray());
        static::assertSame(['items' => []], $cast->get($model, 'detail_items', null, [])->toArray());
    }

    public function test_cast_set_stores_json_and_null(): void
    {
        $cast = new DteBlockCast(DteDetailItems::class);
        $model = new SiiDtePayload;

        static::assertNull($cast->set($model, 'detail_items', null, []));
        static::assertSame('{"items":[]}', $cast->set($model, 'detail_items', ['items' => []], []));
        static::assertSame(
            '{"items":[]}',
            $cast->set($model, 'detail_items', DteDetailItems::make(['items' => []]), []),
        );
    }
}
