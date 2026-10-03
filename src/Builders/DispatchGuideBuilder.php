<?php

namespace Laragear\Dte\Builders;

use DateTimeImmutable;
use Laragear\Dte\Builders\Concerns\HasItems;
use Laragear\Dte\Builders\Concerns\HasReferences;
use Laragear\Dte\Builders\Concerns\HasTransport;
use Laragear\Dte\Enums\DteType;
use Laragear\Rut\Rut;

class DispatchGuideBuilder extends DocumentBuilder
{
    use HasItems;
    use HasReferences;
    use HasTransport;

    /**
     * Return electronic dispatch guide type 52.
     */
    public function documentType(): DteType
    {
        return DteType::DispatchGuide;
    }

    /**
     * Return transport input for payload persistence.
     *
     * @return array<string, mixed>
     */
    protected function additionalData(): array
    {
        $transport = $this->transport();

        return [
            'ind_traslado' => $transport['ind_traslado'] ?? null,
            'tipo_despacho' => $transport['tipo_despacho'] ?? null,
            'transport' => [
                'vehicle_plate' => $transport['vehicle_plate'] ?? null,
                'trailer_plate' => $transport['trailer_plate'] ?? null,
                'carrier_rut' => isset($transport['carrier']) ? $transport['carrier']->formatRaw() : null,
                'driver_rut' => isset($transport['driver']) ? $transport['driver']->formatRaw() : null,
                'driver_name' => $transport['driver_name'] ?? null,
                'destination_address' => $transport['destination_address'] ?? null,
                'destination_commune' => $transport['destination_commune'] ?? null,
                'destination_city' => $transport['destination_city'] ?? null,
                'departure_at' => isset($transport['departure_at']) ? $transport['departure_at']->format('Y-m-d H:i:s') : null,
                'arrival_at' => isset($transport['arrival_at']) ? $transport['arrival_at']->format('Y-m-d H:i:s') : null,
            ],
        ];
    }

    /**
     * Restore the transport state from the persisted payload.
     *
     * @param  array<string, mixed>  $data
     */
    protected function hydrateAdditional(array $data): void
    {
        $this->transport = [];

        if ($data['transport'] ?? null) {
            $this->transport = [
                'vehicle_plate' => $data['transport']['vehicle_plate'] ?? null,
                'trailer_plate' => $data['transport']['trailer_plate'] ?? null,
                'carrier' => isset($data['transport']['carrier_rut'])
                    ? Rut::parse($data['transport']['carrier_rut'])
                    : null,
                'driver' => isset($data['transport']['driver_rut'])
                    ? Rut::parse($data['transport']['driver_rut'])
                    : null,
                'driver_name' => $data['transport']['driver_name'] ?? null,
                'destination_address' => $data['transport']['destination_address'] ?? null,
                'destination_commune' => $data['transport']['destination_commune'] ?? null,
                'destination_city' => $data['transport']['destination_city'] ?? null,
                'departure_at' => isset($data['transport']['departure_at'])
                    ? new DateTimeImmutable($data['transport']['departure_at'])
                    : null,
                'arrival_at' => isset($data['transport']['arrival_at'])
                    ? new DateTimeImmutable($data['transport']['arrival_at'])
                    : null,
            ];
        }

        $this->transport['ind_traslado'] = $data['ind_traslado'] ?? null;
        $this->transport['tipo_despacho'] = $data['tipo_despacho'] ?? null;
    }

    /**
     * Validate document-specific input.
     */
    protected function validateSpecific(): void
    {
        $this->validateB2bReceiver();
    }
}
