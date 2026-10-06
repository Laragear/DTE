<?php

namespace Laragear\Dte\Casts;

use Laragear\Dte\Validation\DteRules;
use Laragear\Rut\Rut;
use XMLWriter;

/**
 * The Encabezado > Emisor block: data of the issuer.
 *
 * @property string $rut The RUTEmisor raw RUT (e.g. 76111222-3 style without formatting).
 * @property string $name The RznSoc legal name.
 * @property string $activity The GiroEmis business activity relevant for the DTE.
 * @property list<string>|string $activity_code The Acteco economic activity codes (up to 4).
 * @property string $address The DirOrigen origin address.
 * @property string $commune The CmnaOrigen origin commune.
 * @property string|null $city The CiudadOrigen origin city.
 * @property string|null $telephone The Telefono contact telephone.
 * @property string|null $email The CorreoEmisor contact e-mail.
 * @property string|null $branch The CdgSIISucur SII branch code.
 * @property string $resolution_date The SII authorization resolution date (envelope-level, not emitted here).
 * @property int $resolution_number The SII authorization resolution number (envelope-level, not emitted here).
 */
class DteHeaderIssuer extends DteBlock
{
    /**
     * Validation rules for the block attributes.
     *
     * @var array<string, string>
     */
    public const array RULES = DteRules::ISSUER;

    /**
     * Append the Emisor element.
     */
    public function toXml(XMLWriter $writer): void
    {
        $writer->startElement('Emisor');

        $writer->writeElement('RUTEmisor', Rut::parse($this['rut'])->formatBasic());
        $writer->writeElement('RznSoc', (string) $this['name']);
        $writer->writeElement('GiroEmis', (string) $this['activity']);

        foreach ((array) $this['activity_code'] as $acteco) {
            $writer->writeElement('Acteco', (string) $acteco);
        }

        $this->optionalElements($writer, $this->toArray(), [
            'telephone' => 'Telefono',
            'address' => 'DirOrigen',
            'commune' => 'CmnaOrigen',
            'city' => 'CiudadOrigen',
            'branch' => 'CdgSIISucur',
        ]);

        $writer->endElement(); // Emisor
    }
}
