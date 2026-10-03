<?php

namespace Laragear\Dte\Database\Factories;

use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\SiiCaf;

/** @extends DteFactory<SiiCaf> */
class SiiCafFactory extends DteFactory
{
    protected $model = SiiCaf::class;

    /**
     * Return the default CAF attributes.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $folio = $this->faker->numberBetween(1, 900000);

        return [
            'rut' => $this->companyRut(),
            'document_type' => DteType::DEFAULT,
            'folio_from' => $folio,
            'folio_to' => $folio + 99,
            'folio_current' => $folio,
            'authorized_on' => $this->faker->dateTimeBetween('-1 year'),
            'expires_on' => $this->faker->dateTimeBetween('+1 month', '+1 year'),
            'xml' => '<AUTORIZACION/>',
        ];
    }

    /**
     * Create a "depleted" CAF.
     */
    public function depleted(): static
    {
        return $this->state(function (array $attributes): array {
            return [
                'folio_current' => $attributes['folio_to'],
                'depleted_at' => $this->faker->dateTime(),
            ];
        });
    }
}
