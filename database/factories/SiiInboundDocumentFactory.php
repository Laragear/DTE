<?php

namespace Laragear\Dte\Database\Factories;

use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\InboundDteStatus;
use Laragear\Dte\Models\SiiInboundDocument;

/** @extends DteFactory<SiiInboundDocument> */
class SiiInboundDocumentFactory extends DteFactory
{
    protected $model = SiiInboundDocument::class;

    /**
     * Return the default inbound DTE attributes.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'issuer_rut' => $this->companyRut(),
            'receiver_rut' => $this->companyRut(),
            'document_type' => DteType::DEFAULT,
            'folio' => $this->faker->unique()->numberBetween(1, 999999999),
            'issued_on' => $this->faker->dateTimeBetween('-1 month'),
            'amount_total' => $this->faker->numberBetween(1000, 1000000),
            'status' => InboundDteStatus::DEFAULT,
            'claim_status' => null,
        ];
    }
}
