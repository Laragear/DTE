<?php

namespace Laragear\Dte\Casts;

use Laragear\Dte\Validation\DteRules;
use XMLWriter;

/**
 * The Documento > Comisiones block: commissions and other charges.
 *
 * @property list<array<string, mixed>> $items The commission rows:
 *     type (C|O), description, rate, net, exempt, tax.
 */
class DteCommissions extends DteBlock
{
    /**
     * Validation rules for the block attributes.
     *
     * @var array<string, string>
     */
    public const array RULES = DteRules::COMMISSIONS;

    /**
     * The default attributes applied when a key is missing.
     *
     * @return array<string, mixed>
     */
    protected static function defaults(): array
    {
        return ['items' => []];
    }

    /**
     * Append every Comisiones row.
     */
    public function toXml(XMLWriter $writer): void
    {
        foreach ($this['items'] as $index => $commission) {
            $writer->startElement('Comisiones');

            $writer->writeElement('NroLinCom', (string) ($index + 1));
            $writer->writeElement('TipoMovim', (string) $commission['type']);
            $writer->writeElement('Glosa', (string) $commission['description']);
            $this->optionalElement($writer, 'TasaComision', $commission['rate'] ?? null);
            $writer->writeElement('ValComNeto', (string) $commission['net']);
            $writer->writeElement('ValComExe', (string) $commission['exempt']);
            $this->optionalElement($writer, 'ValComIVA', $commission['tax'] ?? null);

            $writer->endElement(); // Comisiones
        }
    }
}
