<?php

namespace Laragear\Dte\Database\Factories;

use Laragear\Dte\Enums\IecvStatus;
use Laragear\Dte\Enums\IecvType;
use Laragear\Dte\Models\SiiIecv;

/** @extends DteFactory<SiiIecv> */
class SiiIecvFactory extends DteFactory
{
    protected $model = SiiIecv::class;

    /**
     * Return the default IECV book attributes.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'issuer_rut' => $this->companyRut(),
            'sender_rut' => $this->companyRut(),
            'type' => IecvType::Sales,
            'period' => now()->subMonth()->format('Y-m'),
            'resolution_date' => now()->subDays(30)->format('Y-m-d'),
            'resolution_number' => 12_345,
            'xml' => '<LibroCompraVenta/>',
            'track_id' => null,
            'status' => IecvStatus::DEFAULT,
        ];
    }

    /**
     * Indicate that the book was uploaded and is awaiting the SII verdict.
     */
    public function uploaded(?string $trackId = '123456789'): static
    {
        return $this->state([
            'track_id' => $trackId,
            'status' => IecvStatus::Uploaded,
            'poll_at' => now(),
            'uploaded_at' => now(),
        ]);
    }
}
