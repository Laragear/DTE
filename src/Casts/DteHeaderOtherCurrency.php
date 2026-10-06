<?php

namespace Laragear\Dte\Casts;

use Laragear\Dte\Validation\DteRules;
use XMLWriter;

/**
 * The Encabezado > OtraMoneda block: document totals in another currency.
 *
 * @property string $currency The TpoMoneda customs currency code.
 * @property string|null $exchange_rate The TpoCambio central-bank exchange rate.
 * @property string|null $net The net amount in the other currency.
 * @property string|null $exempt The exempt amount in the other currency.
 * @property string|null $livestock_base The meat-processing base amount in the other currency.
 * @property string|null $commercial_margin The commercialization margin in the other currency.
 * @property string|null $tax The IVA amount in the other currency.
 * @property string|null $unretained_tax The unretained IVA in the other currency.
 * @property string $total The total amount in the other currency.
 * @property list<array{type: int, rate: string|null, amount: string}>|null $withheld_taxes The ImpRetOtrMnda withheld-taxes table.
 */
class DteHeaderOtherCurrency extends DteBlock
{
    /**
     * Validation rules for the block attributes.
     *
     * @var array<string, string>
     */
    public const array RULES = DteRules::HEADER_OTHER_CURRENCY;

    /**
     * Append the OtraMoneda element, skipping it when the block is empty.
     */
    public function toXml(XMLWriter $writer): void
    {
        if ($this->isEmpty()) {
            return;
        }

        $writer->startElement('OtraMoneda');

        $writer->writeElement('TpoMoneda', (string) $this['currency']);
        $this->optionalElement($writer, 'TpoCambio', $this['exchange_rate'] ?? null);
        $this->optionalElement($writer, 'MntNetoOtrMnda', $this['net'] ?? null);
        $this->optionalElement($writer, 'MntExeOtrMnda', $this['exempt'] ?? null);
        $this->optionalElement($writer, 'MntFaeCarneOtrMnda', $this['livestock_base'] ?? null);
        $this->optionalElement($writer, 'MntMargComOtrMnda', $this['commercial_margin'] ?? null);
        $this->optionalElement($writer, 'IVAOtrMnda', $this['tax'] ?? null);

        foreach ($this['withheld_taxes'] ?? [] as $row) {
            $writer->startElement('ImpRetOtrMnda');
            $writer->writeElement('TipoImpOtrMnda', (string) $row['type']);
            $this->optionalElement($writer, 'TasaImpOtrMnda', $row['rate'] ?? null);
            $writer->writeElement('VlrImpOtrMnda', (string) $row['amount']);
            $writer->endElement(); // ImpRetOtrMnda
        }

        $this->optionalElement($writer, 'IVANoRetOtrMnda', $this['unretained_tax'] ?? null);
        $writer->writeElement('MntTotOtrMnda', (string) $this['total']);

        $writer->endElement(); // OtraMoneda
    }
}
