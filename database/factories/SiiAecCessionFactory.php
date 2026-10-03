<?php

namespace Laragear\Dte\Database\Factories;

use Laragear\Dte\Enums\AecStatus;
use Laragear\Dte\Models\SiiAecCession;
use Laragear\Dte\Models\SiiDte;

/** @extends DteFactory<SiiAecCession> */
class SiiAecCessionFactory extends DteFactory
{
    protected $model = SiiAecCession::class;

    /**
     * Return the default AEC cession attributes.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sii_dte_id' => SiiDte::factory(),
            'cession_number' => 1,
            'rut' => $this->companyRut(),
            'amount_total' => $this->faker->numberBetween(1000, 1000000),
            'last_due_on' => $this->faker->dateTimeBetween('+1 month', '+1 year'),
            'terms' => null,
            'xml' => null,
            'track_id' => null,
            'status' => AecStatus::DEFAULT,
        ];
    }
}
