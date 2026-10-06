<?php

namespace Laragear\Dte\Database\Factories;

use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDtePayload;

/** @extends DteFactory<SiiDtePayload> */
class SiiDtePayloadFactory extends DteFactory
{
    protected $model = SiiDtePayload::class;

    /**
     * Return the default DTE payload attributes.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sii_dte_id' => SiiDte::factory(),
            'detail_items' => ['items' => []],
            'xml' => '<DTE/>',
        ];
    }
}
