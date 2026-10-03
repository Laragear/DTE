<?php

namespace Tests\Unit\Models;

use Illuminate\Support\Collection;
use Laragear\Dte\Casts\AsDocumentPayload;
use Laragear\Dte\Data\DocumentPayload;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Data\ReferenceData;
use Laragear\Dte\Models\SiiDtePayload;
use Tests\DatabaseTestCase;

class SiiDtePayloadCastTest extends DatabaseTestCase
{
    public function test_data_casts_to_document_payload(): void
    {
        $payload = SiiDtePayload::factory()->create([
            'data' => ['items' => [['name' => 'Service', 'unit_price' => 1000, 'quantity' => 1]]],
        ]);

        $payload->refresh();

        static::assertInstanceOf(DocumentPayload::class, $payload->data);
        static::assertInstanceOf(Collection::class, $payload->data->references);
        static::assertContainsOnlyInstancesOf(ReferenceData::class, $payload->data->references);
        static::assertSame('Service', $payload->data['items'][0]['name']);
        static::assertSame('Service', $payload->data->items->first()->name);
        static::assertInstanceOf(Item::class, $payload->data->items->first());
    }

    public function test_data_accepts_document_payload_on_write(): void
    {
        $payload = SiiDtePayload::factory()->create([
            'data' => DocumentPayload::fromArray(['items' => []]),
        ]);

        $payload->refresh();

        static::assertInstanceOf(DocumentPayload::class, $payload->data);
        static::assertSame(['items' => []], $payload->data->toArray());
    }

    public function test_get_returns_instance_and_handles_invalid_values(): void
    {
        $cast = new AsDocumentPayload;
        $model = new SiiDtePayload;

        $instance = DocumentPayload::fromArray(['items' => []]);

        static::assertSame($instance, $cast->get($model, 'data', $instance, []));
        static::assertSame([], $cast->get($model, 'data', 'not-json', [])->toArray());
        static::assertSame([], $cast->get($model, 'data', 123, [])->toArray());
        static::assertSame(['a' => 1], $cast->get($model, 'data', ['a' => 1], [])->toArray());
    }

    public function test_set_handles_scalars(): void
    {
        $cast = new AsDocumentPayload;
        $model = new SiiDtePayload;

        static::assertSame('[]', $cast->set($model, 'data', 'scalar', []));
        static::assertSame('{"a":1}', $cast->set($model, 'data', ['a' => 1], []));
    }
}
