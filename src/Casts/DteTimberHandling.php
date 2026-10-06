<?php

namespace Laragear\Dte\Casts;

use Laragear\Dte\Validation\DteRules;
use XMLWriter;

/**
 * The Documento > ManejoMadera block: timber traceability.
 *
 * @property int $origin_commune The ComunaRolOrigen origin land-role commune.
 * @property int $origin_block The MnzRolOrigen origin land-role block.
 * @property int $origin_property The PrdRolOrigen origin land-role property.
 * @property int|null $destination_commune The ComunaRolDestino destination land-role commune.
 * @property int|null $destination_block The MnzRolDestino destination land-role block.
 * @property int|null $destination_property The PrdRolDestino destination land-role property.
 * @property string $conaf_plan_code The CodPlanConaf CONAF management plan code.
 * @property string|null $logging_notice The AvisoEjecucionFaena logging operation notice.
 * @property string $origin_latitude The timber origin latitude.
 * @property string $origin_longitude The timber origin longitude.
 * @property string|null $destination_latitude The timber destination latitude.
 * @property string|null $destination_longitude The timber destination longitude.
 * @property int $reference_system The coordinate reference system code.
 */
class DteTimberHandling extends DteBlock
{
    /**
     * Validation rules for the block attributes.
     *
     * @var array<string, string>
     */
    public const array RULES = DteRules::TIMBER_HANDLING;

    /**
     * Append the ManejoMadera element, skipping it when the block is empty.
     */
    public function toXml(XMLWriter $writer): void
    {
        if ($this->isEmpty()) {
            return;
        }

        $writer->startElement('ManejoMadera');

        $writer->writeElement('ComunaRolOrigen', (string) $this['origin_commune']);
        $writer->writeElement('MnzRolOrigen', (string) $this['origin_block']);
        $writer->writeElement('PrdRolOrigen', (string) $this['origin_property']);
        $this->optionalElement($writer, 'ComunaRolDestino', $this['destination_commune'] ?? null);
        $this->optionalElement($writer, 'MnzRolDestino', $this['destination_block'] ?? null);
        $this->optionalElement($writer, 'PrdRolDestino', $this['destination_property'] ?? null);
        $writer->writeElement('CodPlanConaf', (string) $this['conaf_plan_code']);
        $this->optionalElement($writer, 'AvisoEjecucionFaena', $this['logging_notice'] ?? null);
        $writer->writeElement('LatitudOrigenMadera', (string) $this['origin_latitude']);
        $writer->writeElement('LongitudOrigenMadera', (string) $this['origin_longitude']);
        $this->optionalElement($writer, 'LatitudDestinoMadera', $this['destination_latitude'] ?? null);
        $this->optionalElement($writer, 'LongitudDestinoMadera', $this['destination_longitude'] ?? null);
        $writer->writeElement('SistemareferenciaMadera', (string) $this['reference_system']);

        $writer->endElement(); // ManejoMadera
    }
}
