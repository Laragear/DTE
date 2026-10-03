<?php

namespace Laragear\Dte\Actions\CompileDte\Pipes;

use Closure;
use Illuminate\Support\DateFactory;
use Laragear\Dte\Actions\CompileDte\Compilation;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Enums\SiiTaxes;
use Laragear\Dte\Support\Timestamp;
use Laragear\Dte\Support\XmlDomFactory;
use Laragear\Rut\Rut;
use XMLWriter;
use function number_format;
use function preg_replace;
use function round;
use function rtrim;

class BuildXml
{
    /**
     * Create a Build XML pipe instance.
     */
    public function __construct(
        protected XmlDomFactory $xml,
        protected DateFactory $date,
    ) {
        //
    }

    /**
     * Handle the incoming DTE compilation.
     *
     * @param  Closure(Compilation): Compilation  $next
     */
    public function handle(Compilation $compilation, Closure $next): Compilation
    {
        $writer = $this->xml->createWriter();

        $writer->startElement('DTE');
        $writer->writeAttribute('version', '1.0');

        $this->appendDocument($compilation, $writer);

        $writer->endElement(); // DTE
        $writer->endDocument();

        // Format: add a newline between every closing > and opening < to produce
        // one-element-per-line layout matching the SII reference format.
        $xmlString = preg_replace('/>\s*</', ">\n<", $writer->outputMemory());

        $document = $this->xml->document(encoding: XmlDomFactory::ENCODING);
        $document->loadXML($xmlString, LIBXML_NONET);
        $document->encoding = XmlDomFactory::ENCODING;

        $compilation->document = $document;

        return $next($compilation);
    }

    /**
     * Append the Documento node and all unsigned content.
     */
    protected function appendDocument(Compilation $compilation, XMLWriter $writer): void
    {
        $dte = $compilation->dte;
        $data = $compilation->payload()->data->toArray();

        $writer->startElement('Documento');
        $writer->writeAttribute('ID', "F{$dte->folio}T{$dte->document_type->value}");

        $this->appendHeader($writer, $data, (int) $dte->folio);
        $this->appendItems($writer, $data['items']);

        if (isset($data['global_modifiers']) && $data['global_modifiers'] !== []) {
            $this->appendGlobalModifiers($writer, $data['global_modifiers']);
        }

        $this->appendReferences($writer, $data['references']);

        if (isset($data['transport']) && $data['transport'] !== []) {
            $this->appendTransport($writer, $data['transport']);
        }

        $writer->writeElement('TmstFirma', Timestamp::formatSii($this->date->now('America/Santiago')));

        $writer->endElement(); // Documento
    }

    /**
     * Append the <Transporte> block for Guías de Despacho (Type 52).
     *
     * @param  array<string, mixed>  $transport
     */
    protected function appendTransport(XMLWriter $writer, array $transport): void
    {
        $writer->startElement('Transporte');

        $this->optionalElement($writer, 'Patente', $transport['vehicle_plate'] ?? null);
        $this->optionalElement($writer, 'PatenteVehiculo', $transport['trailer_plate'] ?? null);
        $this->optionalElement($writer, 'RUTTrans', $transport['carrier_rut'] ?? null);

        if (!empty($transport['driver_rut'])) {
            $writer->startElement('Chofer');
            $writer->writeElement('RUT', $transport['driver_rut']);
            $this->optionalElement($writer, 'Nombre', $transport['driver_name'] ?? null);
            $writer->endElement(); // Chofer
        }

        $this->optionalElement($writer, 'DirDest', $transport['destination_address'] ?? null);
        $this->optionalElement($writer, 'CmnaDest', $transport['destination_commune'] ?? null);
        $this->optionalElement($writer, 'CiudadDest', $transport['destination_city'] ?? null);

        $writer->endElement(); // Transporte
    }

    /**
     * Append the document header sections.
     *
     * @param  array<string, mixed>  $data
     */
    protected function appendHeader(XMLWriter $writer, array $data, int $folio): void
    {
        $writer->startElement('Encabezado');

        $this->appendIdentification($writer, $data, $folio);
        $this->appendIssuer($writer, $data['issuer']);
        $this->appendReceiver($writer, $data['receiver']);
        $this->appendTotals($writer, $data['totals'], $data['taxes'] ?? []);

        $writer->endElement(); // Encabezado
    }

    /**
     * Append document identification.
     *
     * @param  array<string, mixed>  $data
     */
    protected function appendIdentification(XMLWriter $writer, array $data, int $folio): void
    {
        $writer->startElement('IdDoc');

        $writer->writeElement('TipoDTE', $data['document_type']);
        $writer->writeElement('Folio', (string) $folio);
        $writer->writeElement('FchEmis', $data['issued_on']);
        $this->appendPaymentTerms($writer, $data['payment'] ?? null);
        $this->optionalElement($writer, 'IndTraslado', $data['ind_traslado'] ?? null);
        $this->optionalElement($writer, 'TipoDespacho', $data['tipo_despacho'] ?? null);

        // IndMntNeto is used for boletas (types 39/41) to indicate pricing format.
        $this->optionalElement($writer, 'IndMntNeto', $data['ind_mnt_neto'] ?? null);

        $writer->endElement(); // IdDoc
    }

    /**
     * Append payment condition and due date when configured.
     *
     * @param  array{condition: int, expiration_date: string}|null  $paymentTerms
     */
    protected function appendPaymentTerms(XMLWriter $writer, ?array $paymentTerms): void
    {
        // <FmaPago> values: 1=Contado, 2=Crédito, 3=Sin costo
        if ($paymentTerms === null) {
            return;
        }

        $writer->writeElement('FmaPago', (string) $paymentTerms['condition']);

        if (!empty($paymentTerms['expiration_date'])) {
            $writer->writeElement('FchVenc', $paymentTerms['expiration_date']);
        }
    }

    /**
     * Append issuer information.
     *
     * @param  array<string, mixed>  $issuer
     */
    protected function appendIssuer(XMLWriter $writer, array $issuer): void
    {
        $writer->startElement('Emisor');

        $writer->writeElement('RUTEmisor', Rut::parse($issuer['rut'])->formatBasic());
        $writer->writeElement('RznSoc', $issuer['name']);
        $writer->writeElement('GiroEmis', $issuer['activity']);

        foreach ((array) $issuer['activity_code'] as $acteco) {
            $writer->writeElement('Acteco', $acteco);
        }

        $this->optionalElements($writer, $issuer, $this->issuerFields());

        $writer->endElement(); // Emisor
    }

    /**
     * Return optional issuer field mappings.
     *
     * @return array<string, string>
     */
    protected function issuerFields(): array
    {
        return [
            'telephone' => 'Telefono',
            'address' => 'DirOrigen',
            'commune' => 'CmnaOrigen',
            'city' => 'CiudadOrigen',
            'branch' => 'CdgSIISucur',
        ];
    }

    /**
     * Append receiver information.
     *
     * @param  array<string, mixed>|null  $receiver
     */
    protected function appendReceiver(XMLWriter $writer, ?array $receiver): void
    {
        if ($receiver === null) {
            return;
        }

        $writer->startElement('Receptor');

        $writer->writeElement('RUTRecep', Rut::parse($receiver['rut'])->formatBasic());
        $writer->writeElement('RznSocRecep', $receiver['name']);
        $this->optionalElements($writer, $receiver, $this->receiverFields());

        $writer->endElement(); // Receptor
    }

    /**
     * Return optional receiver field mappings.
     *
     * @return array<string, string>
     */
    protected function receiverFields(): array
    {
        return [
            'activity' => 'GiroRecep',
            'email' => 'CorreoRecep',
            'address' => 'DirRecep',
            'commune' => 'CmnaRecep',
            'city' => 'CiudadRecep',
        ];
    }

    /**
     * Append monetary totals and the withheld taxes behind them.
     *
     * @param  array{net: int, exempt: int, tax: int, total: int}  $totals
     * @param  array<int, int>  $taxes
     */
    protected function appendTotals(XMLWriter $writer, array $totals, array $taxes = []): void
    {
        $writer->startElement('Totales');

        $this->positiveElement($writer, 'MntNeto', $totals['net']);
        $this->positiveElement($writer, 'MntExe', $totals['exempt']);

        if ($totals['tax'] > 0) {
            $writer->writeElement('TasaIVA', (string) SiiTaxes::ivaRate());
            $writer->writeElement('IVA', (string) $totals['tax']);
        }

        foreach ($taxes as $taxCode => $amount) {
            $writer->startElement('ImptoReten');
            $writer->writeElement('TipoImp', (string) $taxCode);
            $writer->writeElement('MontoImp', (string) $amount);
            $writer->endElement();
        }

        $writer->writeElement('MntTotal', (string) $totals['total']);
        $this->positiveElement($writer, 'MontoNoFacturable', $totals['non_billable'] ?? 0);

        $writer->endElement(); // Totales
    }

    /**
     * Append all detail lines.
     *
     * @param  list<array<string, mixed>>  $items
     */
    protected function appendItems(XMLWriter $writer, array $items): void
    {
        foreach ($items as $index => $item) {
            $writer->startElement('Detalle');

            $writer->writeElement('NroLinDet', (string) ($index + 1));
            $this->appendItemCode($writer, $item);
            $this->positiveElement($writer, 'IndExe', $item['exempt'] ? 1 : 0);
            $this->appendItemValues($writer, $item);

            $writer->endElement(); // Detalle
        }
    }

    /**
     * Append an optional item code.
     *
     * @param  array<string, mixed>  $item
     */
    protected function appendItemCode(XMLWriter $writer, array $item): void
    {
        if (empty($item['code'])) {
            return;
        }

        $writer->startElement('CdgItem');
        $writer->writeElement('TpoCodigo', $item['code_type'] ?? 'INT1');
        $writer->writeElement('VlrCodigo', $item['code']);
        $writer->endElement();
    }

    /**
     * Append item description and monetary values.
     *
     * @param  array<string, mixed>  $item
     */
    protected function appendItemValues(XMLWriter $writer, array $item): void
    {
        $writer->writeElement('NmbItem', $item['name']);
        $this->optionalElement($writer, 'DscItem', $item['description']);

        $quantity = (float) $item['quantity'];

        // A zero quantity marks a tax-only line. The SII rejects a literal zero
        // because Dec12_6Type has minInclusive 0.000001, so it is emitted as 1
        // and the unit price is left out entirely.
        $writer->writeElement('QtyItem', $this->decimal($quantity <= 0 ? 1.0 : $quantity));

        $this->optionalElement($writer, 'UnmdItem', $item['unit']);

        $itemTotal = $this->itemTotal($item);

        if ($itemTotal > 0 && $quantity > 0) {
            $writer->writeElement('PrcItem', $this->decimal($item['unit_price']));
        }

        $this->appendItemDiscount($writer, $item);
        $this->appendItemTaxes($writer, $item);

        $writer->writeElement('MontoItem', (string) $itemTotal);
    }

    /**
     * Append the line discount as a percentage and its resolved amount.
     *
     * @param  array<string, mixed>  $item
     */
    protected function appendItemDiscount(XMLWriter $writer, array $item): void
    {
        $discountAmount = $this->itemDiscountAmount($item);

        if ($discountAmount <= 0) {
            return;
        }

        $percentage = (float) ($item['discount_percentage'] ?? 0);

        if ($percentage > 0) {
            $writer->writeElement('DescuentoPct', (string) $percentage);
        }

        $writer->writeElement('DescuentoMonto', (string) round($discountAmount));
    }

    /**
     * Append every additional tax code applied to the line.
     *
     * @param  array<string, mixed>  $item
     */
    protected function appendItemTaxes(XMLWriter $writer, array $item): void
    {
        if (!empty($item['taxes'])) {
            foreach ($item['taxes'] as $taxCode => $amount) {
                $writer->writeElement('CodImpAdic', (string) $taxCode);
            }
        }
    }

    /**
     * Calculate one persisted detail line total.
     *
     * @param  array<string, mixed>  $item
     */
    protected function itemTotal(array $item): int
    {
        $gross = $item['unit_price'] * $item['quantity'];
        $discount = $this->itemDiscountAmount($item);

        return round($gross - $discount);
    }

    /**
     * Resolve the line discount amount for XML output.
     *
     * @param  array<string, mixed>  $item
     *
     * @see Item::calculateDiscount()
     */
    protected function itemDiscountAmount(array $item): float
    {
        // Priority (SII compliance):
        // 1. discount_percentage > 0 → auto-calculate from percentage, emit both fields
        // 2. discount_amount > 0 (no percentage) → use the fixed amount directly
        // 3. Both absent or non-positive → no discount
        $percentage = (float) ($item['discount_percentage'] ?? 0);

        if ($percentage > 0) {
            return (float) $item['unit_price'] * (float) $item['quantity'] * ($percentage / 100);
        }

        return max(0, (float) ($item['discount_amount'] ?? 0));
    }

    /**
     * Append the global discount and surcharge lines.
     *
     * @param  list<array{type: string, value_type: string, value: float|int, target: int, description: string|null}>  $modifiers
     */
    protected function appendGlobalModifiers(XMLWriter $writer, array $modifiers): void
    {
        foreach ($modifiers as $index => $modifier) {
            $writer->startElement('DscRcgGlobal');

            $writer->writeElement('NroLinDR', (string) ($index + 1));
            $writer->writeElement('TpoMov', $modifier['type']); // 'D' or 'R'

            if (!empty($modifier['description'])) {
                $writer->writeElement('GlosaDR', substr($modifier['description'], 0, 45));
            }

            $writer->writeElement('TpoValor', $modifier['value_type']); // '%' or '$'
            $writer->writeElement('ValorDR', (string) round($modifier['value'], 4));

            if (isset($modifier['target']) && $modifier['target'] > 0) {
                $writer->writeElement('IndExeDR', (string) $modifier['target']);
            }

            $writer->endElement(); // DscRcgGlobal
        }
    }

    /**
     * Append all document references.
     *
     * @param  list<array<string, mixed>>  $references
     */
    protected function appendReferences(XMLWriter $writer, array $references): void
    {
        foreach ($references as $index => $reference) {
            $writer->startElement('Referencia');

            $writer->writeElement('NroLinRef', (string) ($index + 1));
            $writer->writeElement('TpoDocRef', $reference['document_type']);
            $this->optionalElement($writer, 'FolioRef', (string) ($reference['folio'] ?? null));
            $writer->writeElement('FchRef',
                (string) ($reference['date'] ?? $this->date->now('America/Santiago')->toDateString()));
            $this->optionalElement($writer, 'CodRef', (string) ($reference['reference_code'] ?? null));
            $this->optionalElement($writer, 'RazonRef', (string) ($reference['reason'] ?? null));

            $writer->endElement();
        }
    }

    /**
     * Append mapped non-empty values.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $fields
     */
    protected function optionalElements(XMLWriter $writer, array $data, array $fields): void
    {
        foreach ($fields as $key => $name) {
            $this->optionalElement($writer, $name, $data[$key] ?? null);
        }
    }

    /**
     * Append a positive numeric element.
     */
    protected function positiveElement(XMLWriter $writer, string $name, int|float $value): void
    {
        if ($value > 0) {
            $writer->writeElement($name, (string) $value);
        }
    }

    /**
     * Append an element only when its value is present.
     */
    protected function optionalElement(XMLWriter $writer, string $name, mixed $value): void
    {
        if ($value !== null && $value !== '') {
            $writer->writeElement($name, (string) $value);
        }
    }

    /**
     * Format a decimal without insignificant trailing zeroes.
     */
    protected function decimal(int|float $value): string
    {
        return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
    }
}
