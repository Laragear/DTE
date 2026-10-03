<?php

namespace Laragear\Dte\Database\Factories;

use Laragear\Dte\Enums\EnvelopeStatus;
use Laragear\Dte\Models\SiiDteEnvelope;

/** @extends DteFactory<SiiDteEnvelope> */
class SiiDteEnvelopeFactory extends DteFactory
{
    protected $model = SiiDteEnvelope::class;

    /**
     * Return the default DTE envelope attributes.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'issuer_rut' => $this->companyRut(),
            'sender_rut' => $this->companyRut(),
            'type' => 'dte',
            'track_id' => null,
            'resolution_date' => now()->subDays(30)->format('Y-m-d'),
            'resolution_number' => 12345,
            'status' => EnvelopeStatus::DEFAULT,
        ];
    }
}
