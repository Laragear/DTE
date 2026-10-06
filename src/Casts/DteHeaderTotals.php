<?php

namespace Laragear\Dte\Casts;

use Laragear\Dte\Enums\SiiTaxes;
use Laragear\Dte\Validation\DteRules;
use XMLWriter;

/**
 * The Encabezado > Totales block: monetary totals of the DTE.
 *
 * @property int|null $net The MntNeto net amount.
 * @property int|null $exempt The MntExe exempt amount.
 * @property int|null $tax The IVA amount.
 * @property int $total The MntTotal total amount.
 * @property int|null $non_billable The MontoNF non-billable amount.
 * @property array<int, int>|null $taxes Additional withheld taxes keyed by ImptoReten code.
 */
class DteHeaderTotals extends DteBlock
{
    /**
     * Validation rules for the block attributes.
     *
     * @var array<string, string>
     */
    public const array RULES = DteRules::TOTALS;

    /**
     * Append the Totales element and the withheld taxes behind it.
     */
    public function toXml(XMLWriter $writer): void
    {
        $writer->startElement('Totales');

        $this->positiveElement($writer, 'MntNeto', (int) ($this['net'] ?? 0));
        $this->positiveElement($writer, 'MntExe', (int) ($this['exempt'] ?? 0));

        if ((int) ($this['tax'] ?? 0) > 0) {
            $writer->writeElement('TasaIVA', (string) SiiTaxes::ivaRate());
            $writer->writeElement('IVA', (string) $this['tax']);
        }

        foreach ($this['taxes'] ?? [] as $taxCode => $amount) {
            $writer->startElement('ImptoReten');
            $writer->writeElement('TipoImp', (string) $taxCode);
            $writer->writeElement('MontoImp', (string) $amount);
            $writer->endElement(); // ImptoReten
        }

        $writer->writeElement('MntTotal', (string) $this['total']);
        $this->positiveElement($writer, 'MontoNoFacturable', (int) ($this['non_billable'] ?? 0));

        $writer->endElement(); // Totales
    }
}
