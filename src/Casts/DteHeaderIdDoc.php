<?php

namespace Laragear\Dte\Casts;

use Laragear\Dte\Validation\DteRules;
use XMLWriter;

/**
 * The Encabezado > IdDoc block: identification of the DTE.
 *
 * @property int $document_type The TipoDTE numeric code.
 * @property string $issued_on The FchEmis accounting issue date (Y-m-d).
 * @property int|null $ind_mnt_neto IndMntNeto pricing indicator for boletas (0, 1, 2).
 * @property int|null $ind_traslado IndTraslado transfer indicator for dispatch guides.
 * @property int|null $tipo_despacho TipoDespacho goods delivery mode.
 * @property int|null $non_rebillable IndNoRebaja: credit note without right to discount.
 * @property bool|null $tax_exempt Whether the whole document is tax exempt (builder hint, not emitted).
 * @property int|null $exempt_amount_override Exempt amount override for wholly exempt documents (builder hint, not emitted).
 * @property array{condition: int, expiration_date: string|null}|null $payment The FmaPago/FchVenc payment terms.
 * @property list<array{date: string, amount: int, gloss: string|null}>|null $payments The MntPagos partial-payments table.
 */
class DteHeaderIdDoc extends DteBlock
{
    /**
     * Validation rules for the block attributes.
     *
     * @var array<string, string>
     */
    public const array RULES = DteRules::HEADER_ID_DOC;

    /**
     * Append the IdDoc element. The folio is assigned at compilation time,
     * so it is injected rather than persisted with the block.
     */
    public function toXml(XMLWriter $writer, ?int $folio = null): void
    {
        $writer->startElement('IdDoc');

        $writer->writeElement('TipoDTE', (string) $this['document_type']);
        $this->optionalElement($writer, 'Folio', $folio === null ? null : (string) $folio);
        $writer->writeElement('FchEmis', (string) $this['issued_on']);

        $this->optionalElement($writer, 'IndNoRebaja', $this['non_rebillable'] ?? null);

        // <FmaPago> values: 1=Contado, 2=Crédito, 3=Sin costo
        if (($payment = $this['payment']) !== null) {
            $writer->writeElement('FmaPago', (string) $payment['condition']);
            $this->optionalElement($writer, 'FchVenc', $payment['expiration_date'] ?? null);
        }

        foreach ($this['payments'] ?? [] as $row) {
            $writer->startElement('MntPagos');
            $writer->writeElement('FchPago', (string) $row['date']);
            $writer->writeElement('MntPago', (string) $row['amount']);
            $this->optionalElement($writer, 'GlosaPagos', $row['gloss'] ?? null);
            $writer->endElement(); // MntPagos
        }

        $this->optionalElement($writer, 'IndTraslado', $this['ind_traslado'] ?? null);
        $this->optionalElement($writer, 'TipoDespacho', $this['tipo_despacho'] ?? null);
        $this->optionalElement($writer, 'IndMntNeto', $this['ind_mnt_neto'] ?? null);

        $writer->endElement(); // IdDoc
    }
}
