<?php

namespace Laragear\Dte\Casts;

use Laragear\Dte\Validation\DteRules;
use function filled;
use XMLWriter;

/**
 * The Encabezado > Transporte block: goods transport information.
 *
 * @property string|null $vehicle_plate The Patente transport vehicle plate.
 * @property string|null $trailer_plate The trailer or cart plate.
 * @property string|null $carrier_rut The RUTTrans carrier RUT.
 * @property string|null $driver_rut The transport driver RUT.
 * @property string|null $driver_name The transport driver name.
 * @property string|null $destination_address The DirDest destination address.
 * @property string|null $destination_commune The CmnaDest destination commune.
 * @property string|null $destination_city The CiudadDest destination city.
 * @property string|null $departure_at The departure timestamp (stored, not emitted).
 * @property string|null $arrival_at The arrival timestamp (stored, not emitted).
 */
class DteHeaderTransport extends DteBlock
{
    /**
     * Validation rules for the block attributes.
     *
     * @var array<string, string>
     */
    public const array RULES = DteRules::TRANSPORT;

    /**
     * Append the Transporte element, skipping it when the block is empty.
     */
    public function toXml(XMLWriter $writer): void
    {
        if ($this->isEmpty()) {
            return;
        }

        $writer->startElement('Transporte');

        $this->optionalElement($writer, 'Patente', $this['vehicle_plate'] ?? null);
        $this->optionalElement($writer, 'PatenteVehiculo', $this['trailer_plate'] ?? null);
        $this->optionalElement($writer, 'RUTTrans', $this['carrier_rut'] ?? null);

        if (filled($this['driver_rut'] ?? null)) {
            $writer->startElement('Chofer');
            $writer->writeElement('RUT', (string) $this['driver_rut']);
            $this->optionalElement($writer, 'Nombre', $this['driver_name'] ?? null);
            $writer->endElement(); // Chofer
        }

        $this->optionalElement($writer, 'DirDest', $this['destination_address'] ?? null);
        $this->optionalElement($writer, 'CmnaDest', $this['destination_commune'] ?? null);
        $this->optionalElement($writer, 'CiudadDest', $this['destination_city'] ?? null);

        $writer->endElement(); // Transporte
    }
}
