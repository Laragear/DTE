<?php

namespace Laragear\Dte\Builders;

use DateTimeImmutable;
use Laragear\Dte\Builders\Concerns\HasItems;
use Laragear\Dte\Builders\Concerns\HasReferences;
use Laragear\Dte\Builders\Concerns\HasTransport;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\SiiDtePayload;
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
     */
    protected function hydrateAdditional(SiiDtePayload $payload): void
    {
        $this->transport = [];

        $idDoc = $payload->header_id_doc;
        $transport = $payload->header_transport;

        if (! $transport->isEmpty()) {
            $this->transport = [
                'vehicle_plate' => $transport->vehicle_plate,
                'trailer_plate' => $transport->trailer_plate,
                'carrier' => isset($transport->carrier_rut)
                    ? Rut::parse($transport->carrier_rut)
                    : null,
                'driver' => isset($transport->driver_rut)
                    ? Rut::parse($transport->driver_rut)
                    : null,
                'driver_name' => $transport->driver_name,
                'destination_address' => $transport->destination_address,
                'destination_commune' => $transport->destination_commune,
                'destination_city' => $transport->destination_city,
                'departure_at' => isset($transport->departure_at)
                    ? new DateTimeImmutable($transport->departure_at)
                    : null,
                'arrival_at' => isset($transport['arrival_at'])
                    ? new DateTimeImmutable($transport['arrival_at'])
                    : null,
            ];
        }

        $this->transport['ind_traslado'] = $idDoc['ind_traslado'] ?? null;
        $this->transport['tipo_despacho'] = $idDoc['tipo_despacho'] ?? null;
    }

    /**
     * Validate document-specific input.
     */
    protected function validateSpecific(): void
    {
        $this->validateB2bReceiver();
    }
}
