<?php

namespace Laragear\Dte\Casts;

use Laragear\Dte\Validation\DteRules;
use XMLWriter;

/**
 * The Documento > GeoRefEmision block: geolocation of the emission.
 *
 * @property string $latitude The emission latitude.
 * @property string $longitude The emission longitude.
 * @property int $reference_system The coordinate reference system code.
 */
class DteEmissionGeoref extends DteBlock
{
    /**
     * Validation rules for the block attributes.
     *
     * @var array<string, string>
     */
    public const array RULES = DteRules::EMISSION_GEOREF;

    /**
     * Append the GeoRefEmision element, skipping it when the block is empty.
     */
    public function toXml(XMLWriter $writer): void
    {
        if ($this->isEmpty()) {
            return;
        }

        $writer->startElement('GeoRefEmision');

        $writer->writeElement('LatitudEmision', (string) $this['latitude']);
        $writer->writeElement('LongitudEmision', (string) $this['longitude']);
        $writer->writeElement('SistemaReferencia', (string) $this['reference_system']);

        $writer->endElement(); // GeoRefEmision
    }
}
