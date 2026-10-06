<?php

namespace Laragear\Dte\Casts;

use Laragear\Dte\Validation\DteRules;
use XMLWriter;

/**
 * The Documento > SubTotInfo block: informative subtotals.
 *
 * @property list<array<string, mixed>> $items The subtotal rows:
 *     description, order, net, tax, additional_tax, exempt, total, detail_lines.
 */
class DteSubtotals extends DteBlock
{
    /**
     * Validation rules for the block attributes.
     *
     * @var array<string, string>
     */
    public const array RULES = DteRules::SUBTOTALS;

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
     * Append every SubTotInfo row.
     */
    public function toXml(XMLWriter $writer): void
    {
        foreach ($this['items'] as $item) {
            $writer->startElement('SubTotInfo');

            $writer->writeElement('GlosaSTI', (string) $item['description']);
            $this->optionalElement($writer, 'OrdenSTI', $item['order'] ?? null);
            $this->optionalElement($writer, 'SubTotNetoSTI', $item['net'] ?? null);
            $this->optionalElement($writer, 'SubTotIVASTI', $item['tax'] ?? null);
            $this->optionalElement($writer, 'SubTotAdicSTI', $item['additional_tax'] ?? null);
            $this->optionalElement($writer, 'SubTotExeSTI', $item['exempt'] ?? null);
            $this->optionalElement($writer, 'ValSubtotSTI', $item['total'] ?? null);

            foreach ($item['detail_lines'] ?? [] as $line) {
                $writer->writeElement('LineasDeta', (string) $line);
            }

            $writer->endElement(); // SubTotInfo
        }
    }
}
