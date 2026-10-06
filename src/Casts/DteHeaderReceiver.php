<?php

namespace Laragear\Dte\Casts;

use Laragear\Dte\Validation\DteRules;
use Laragear\Rut\Rut;
use XMLWriter;

/**
 * The Encabezado > Receptor block: data of the receiver.
 *
 * @property string $rut The RUTRecep raw RUT.
 * @property string $name The RznSocRecep legal name.
 * @property string|null $activity The GiroRecep business activity.
 * @property string|null $email The CorreoRecep contact e-mail.
 * @property string|null $address The DirRecep reception address.
 * @property string|null $commune The CmnaRecep reception commune.
 * @property string|null $city The CiudadRecep reception city.
 */
class DteHeaderReceiver extends DteBlock
{
    /**
     * Validation rules for the block attributes.
     *
     * @var array<string, string>
     */
    public const array RULES = DteRules::RECEIVER;

    /**
     * Append the Receptor element, skipping it when the block is empty.
     */
    public function toXml(XMLWriter $writer): void
    {
        if ($this->isEmpty()) {
            return;
        }

        $writer->startElement('Receptor');

        $writer->writeElement('RUTRecep', Rut::parse($this['rut'])->formatBasic());
        $writer->writeElement('RznSocRecep', (string) $this['name']);

        $this->optionalElements($writer, $this->toArray(), [
            'activity' => 'GiroRecep',
            'email' => 'CorreoRecep',
            'address' => 'DirRecep',
            'commune' => 'CmnaRecep',
            'city' => 'CiudadRecep',
        ]);

        $writer->endElement(); // Receptor
    }
}
