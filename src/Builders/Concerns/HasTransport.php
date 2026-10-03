<?php

namespace Laragear\Dte\Builders\Concerns;

use DateTimeImmutable;
use Laragear\Rut\Rut;

trait HasTransport
{
    /**
     * @var array{
     *    vehicle_plate?: string,
     *    trailer_plate?: string,
     *    carrier?: Rut,
     *    driver?: Rut,
     *    driver_name?: string,
     *    destination_address?: string,
     *    destination_commune?: string,
     *    destination_city?: string,
     *    departure_at?: DateTimeImmutable,
     *    arrival_at?: DateTimeImmutable,
     *    ind_traslado?: int,
     *    tipo_despacho?: int
     *  }
     */
    protected array $transport = [];

    /**
     * Set the vehicle and optional trailer plates.
     */
    public function withVehicle(string $vehiclePlate, ?string $trailerPlate = null): static
    {
        $this->transport['vehicle_plate'] = $vehiclePlate;

        if ($trailerPlate !== null) {
            $this->transport['trailer_plate'] = $trailerPlate;
        }

        return $this;
    }

    /**
     * Set the transfer motive (IndTraslado).
     */
    public function transferMotive(int $indTraslado): static
    {
        // SII values: 1 = Constitye venta
        // 2 = Ventas por efectuar
        // 3 = Consignaciones
        // 4 = Entrega gratuita
        // 5 = Traslados internos
        // 6 = Otros traslados no venta
        // 7 = Guía devolución
        // 8 = Traslado exportación (no venta)
        // 9 = Venta exportación
        $this->transport['ind_traslado'] = $indTraslado;

        return $this;
    }

    /**
     * Set the dispatch type (TipoDespacho).
     */
    public function dispatchType(int $tipoDespacho): static
    {
        // SII values: 1 = Despacho por receptor
        // 2 = Despacho emisor a instalaciones cliente
        // 3 = Despacho emisor a otras instalaciones
        $this->transport['tipo_despacho'] = $tipoDespacho;

        return $this;
    }

    /**
     * Set the taxpayer transporting the goods.
     */
    public function withCarrier(Rut $carrier): static
    {
        $this->transport['carrier'] = $carrier;

        return $this;
    }

    /**
     * Set the driver transporting the goods.
     */
    public function withDriver(Rut $driver, string $name): static
    {
        $this->transport['driver'] = $driver;
        $this->transport['driver_name'] = $name;

        return $this;
    }

    /**
     * Set the destination for the transported goods.
     */
    public function toDestination(string $address, string $commune, ?string $city = null): static
    {
        $this->transport['destination_address'] = $address;
        $this->transport['destination_commune'] = $commune;

        if ($city !== null) {
            $this->transport['destination_city'] = $city;
        }

        return $this;
    }

    /**
     * Set the departure and optional arrival timestamps.
     */
    public function withTransportSchedule(DateTimeImmutable $departure, ?DateTimeImmutable $arrival = null): static
    {
        $this->transport['departure_at'] = $departure;

        if ($arrival !== null) {
            $this->transport['arrival_at'] = $arrival;
        }

        return $this;
    }

    /**
     * Return the configured transport data.
     *
     * @return array{
     *    vehicle_plate?: string,
     *    trailer_plate?: string,
     *    carrier?: Rut,
     *    driver?: Rut,
     *    driver_name?: string,
     *    destination_address?: string,
     *    destination_commune?: string,
     *    destination_city?: string,
     *    departure_at?: DateTimeImmutable,
     *    arrival_at?: DateTimeImmutable
     * }
     */
    public function transport(): array
    {
        return $this->transport;
    }
}
