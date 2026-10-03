<?php

namespace Laragear\Dte\Database\Factories;

use Laragear\Dte\Models\SiiInterchangeLog;

/** @extends DteFactory<SiiInterchangeLog> */
class SiiInterchangeLogFactory extends DteFactory
{
    protected $model = SiiInterchangeLog::class;

    /**
     * Return the default interchange log attributes.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => $this->faker->unique()->uuid(),
            'direction' => 'inbound',
            'type' => 'email',
            'sender' => $this->faker->safeEmail(),
            'recipient' => $this->faker->safeEmail(),
            'subject' => 'DTE interchange',
            'raw_email' => null,
            'response_xml' => null,
            'data' => [],
            'processed_at' => null,
        ];
    }
}
