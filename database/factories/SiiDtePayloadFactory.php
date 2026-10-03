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
            'data' => [
                'resolution_date' => $this->faker->dateTimeBetween('-3 years')->format('Y-m'),
                'resolution_number' => $this->faker->numberBetween(9999, 999999),
                'items' => [],
            ],
            'xml' => '<DTE/>',
        ];
    }
}
