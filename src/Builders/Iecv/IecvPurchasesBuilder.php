<?php

namespace Laragear\Dte\Builders\Iecv;

use Illuminate\Support\DateFactory;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\IecvType;
use Laragear\Dte\Enums\SiiTaxes;
use Laragear\Dte\Support\Timestamp;
use Laragear\Dte\Support\XmlDomFactory;
use Laragear\Rut\Rut;
use XMLWriter;
use function number_format;
use function round;

class IecvPurchasesBuilder
{
    /**
     * Create a new Iecv Purchases Builder instance.
     */
    public function __construct(
        protected XmlDomFactory $xml,
        protected DateFactory $date,
    ) {
        //
    }

    /**
     * Build the purchases ledger XML content.
     *
     * @param  array<int, IecvPurchaseData>  $entries
     * @param  array<int, IecvPropertyData>  $properties
     */
    public function build(
        array $entries,
        string $period,
        string $resolutionDate,
        int $resolutionNumber,
        Rut $companyRut,
        Rut $senderRut,
        array $properties = [],
    ): string {
        $writer = $this->xml->writer();
        $writer->openMemory();
        $writer->startDocument('1.0', XmlDomFactory::ENCODING);
        $writer->startElement('LibroCompraVenta');
        $writer->writeAttribute('xmlns', XmlDomFactory::XML_NAMESPACE);
        $writer->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $writer->writeAttribute('xsi:schemaLocation', XmlDomFactory::XML_NAMESPACE.' LibroCV_v10.xsd');
        $writer->writeAttribute('version', '1.0');

        $writer->startElement('EnvioLibro');
        $writer->writeAttribute('ID', 'Libro'.IecvType::Purchases->value.'_'.str_replace('-', '', $period));

        $this->appendCaratula($writer, $companyRut, $senderRut, $period, $resolutionDate, $resolutionNumber);
        $this->appendResumenPeriodo($writer, $entries, $this->parseOptions($properties));
        $this->appendDetalle($writer, $entries);

        $writer->writeElement('TmstFirma', Timestamp::formatSii($this->date->now('America/Santiago')));

        $writer->endElement(); // EnvioLibro
        $writer->endElement(); // LibroCompraVenta
        $writer->endDocument();

        return $writer->outputMemory();
    }

    /**
     * Parse the given IECV properties into an options array.
     *
     * @param  array<int, IecvPropertyData>  $properties
     * @return array<string, mixed>
     */
    protected function parseOptions(array $properties): array
    {
        $options = [];

        foreach ($properties as $property) {
            $options[$property->property->value] = $property->value;
        }

        return $options;
    }

    /**
     * Write the ledger cover with issuer and period data.
     */
    protected function appendCaratula(
        XMLWriter $writer,
        Rut $companyRut,
        Rut $senderRut,
        string $period,
        string $resolutionDate,
        int $resolutionNumber
    ): void {
        $writer->startElement('Caratula');
        $writer->writeElement('RutEmisorLibro', $companyRut->formatBasic());
        $writer->writeElement('RutEnvia', $senderRut->formatBasic());
        $writer->writeElement('PeriodoTributario', $period);
        $writer->writeElement('FchResol', $resolutionDate);
        $writer->writeElement('NroResol', (string) $resolutionNumber);
        $writer->writeElement('TipoOperacion', IecvType::Purchases->value);
        $writer->writeElement('TipoLibro', 'ESPECIAL');
        $writer->writeElement('TipoEnvio', 'TOTAL');
        $writer->writeElement('FolioNotificacion', '2');
        $writer->endElement();
    }

    /**
     * Compute the IVA taxes and total for an entry.
     *
     * @return array{taxes: int, total: int}
     */
    protected function computeEntryAmounts(IecvPurchaseData $entry): array
    {
        $taxes = (int) round($entry->amountNet * SiiTaxes::ivaDecimal(), 0, PHP_ROUND_HALF_UP);
        $total = $entry->amountNet + $entry->amountExempt + $taxes;

        if ($entry->ivaRetainedTotal) {
            $total -= $taxes;
        }

        return ['taxes' => $taxes, 'total' => $total];
    }

    /**
     * Write the period summary grouped by document type.
     *
     * @param  array<int, IecvPurchaseData>  $entries
     * @param  array<string, mixed>  $options
     */
    protected function appendResumenPeriodo(XMLWriter $writer, array $entries, array $options): void
    {
        $writer->startElement('ResumenPeriodo');

        $grouped = $this->groupEntriesByType($entries);

        foreach ($grouped as $group) {
            $this->writeGroupTotals($writer, $group, $options);
        }

        $writer->endElement();
    }

    /**
     * Group entries by document type with running totals.
     *
     * @param  array<int, IecvPurchaseData>  $entries
     * @return array<int|string, array{type: int|string, count: int, exempt: int, net: int, taxes: int, iva_common_use: bool, total: int}>
     */
    protected function groupEntriesByType(array $entries): array
    {
        $grouped = [];

        foreach ($entries as $entry) {
            $typeKey = $entry->documentType instanceof DteType
                ? $entry->documentType->value
                : $entry->documentType;

            if (!isset($grouped[$typeKey])) {
                $grouped[$typeKey] = [
                    'type' => $typeKey,
                    'count' => 0,
                    'exempt' => 0,
                    'net' => 0,
                    'taxes' => 0,
                    'iva_common_use' => false,
                    'total' => 0,
                ];
            }

            $amounts = $this->computeEntryAmounts($entry);
            $grouped[$typeKey]['count']++;
            $grouped[$typeKey]['exempt'] += $entry->amountExempt;
            $grouped[$typeKey]['net'] += $entry->amountNet;
            $grouped[$typeKey]['taxes'] += $amounts['taxes'];
            $grouped[$typeKey]['total'] += $amounts['total'];
            $grouped[$typeKey]['iva_common_use'] = $grouped[$typeKey]['iva_common_use'] || $entry->ivaCommonUse;
        }

        return $grouped;
    }

    /**
     * Write the totals for a single document type group.
     *
     * @param  array{type: int|string, count: int, exempt: int, net: int, taxes: int, iva_common_use: bool, total: int}  $group
     * @param  array<string, mixed>  $options
     */
    protected function writeGroupTotals(XMLWriter $writer, array $group, array $options): void
    {
        $writer->startElement('TotalesPeriodo');
        $writer->writeElement('TpoDoc', (string) $group['type']);
        $writer->writeElement('TotDoc', (string) $group['count']);
        $writer->writeElement('TotMntExe', (string) $group['exempt']);
        $writer->writeElement('TotMntNeto', (string) $group['net']);
        $writer->writeElement('TotMntIVA', (string) $group['taxes']);

        if ($group['iva_common_use']) {
            $writer->writeElement('TotOpIVAUsoComun', (string) $group['count']);
            $writer->writeElement('TotIVAUsoComun', (string) $group['taxes']);

            if (isset($options['FctProp'])) {
                $factor = (float) $options['FctProp'];
                $writer->writeElement('FctProp', (string) round($factor, 3));
                $writer->writeElement('TotCredIVAUsoComun', (string) round($group['taxes'] * $factor));
            }
        }

        $writer->writeElement('TotMntTotal', (string) $group['total']);
        $writer->endElement();
    }

    /**
     * Write every purchase entry into the ledger detail.
     *
     * @param  array<int, IecvPurchaseData>  $entries
     */
    protected function appendDetalle(XMLWriter $writer, array $entries): void
    {
        foreach ($entries as $entry) {
            $this->writeDetalleEntry($writer, $entry);
        }
    }

    /**
     * Write a single purchase entry into the ledger detail.
     */
    protected function writeDetalleEntry(XMLWriter $writer, IecvPurchaseData $entry): void
    {
        $amounts = $this->computeEntryAmounts($entry);
        $rate = SiiTaxes::ivaRate();
        $rateStr = number_format((float) $rate, 2);

        $writer->startElement('Detalle');

        $docType = $entry->documentType instanceof DteType
            ? $entry->documentType->value
            : $entry->documentType;
        $writer->writeElement('TpoDoc', (string) $docType);
        $writer->writeElement('NroDoc', (string) $entry->folio);
        $writer->writeElement('TasaImp', $entry->amountNet > 0 ? $rateStr : '0.00');

        if ($entry->noCost) {
            $writer->writeElement('IndSinCosto', '1');
        }

        $writer->writeElement('FchDoc', $entry->issuedOn);

        $issuerRut = $entry->issuerRut instanceof Rut
            ? $entry->issuerRut->formatBasic()
            : Rut::parse($entry->issuerRut)->formatBasic();
        $writer->writeElement('RUTDoc', $issuerRut);

        if ($entry->referenceType !== null) {
            $refType = $entry->referenceType instanceof DteType
                ? $entry->referenceType->value
                : $entry->referenceType;
            $writer->writeElement('TpoDocRef', (string) $refType);
        }

        if ($entry->referenceFolio !== null) {
            $writer->writeElement('FolioRef', (string) $entry->referenceFolio);
        }

        if ($entry->amountExempt > 0) {
            $writer->writeElement('MntExe', (string) $entry->amountExempt);
        }
        if ($entry->amountNet > 0) {
            $writer->writeElement('MntNeto', (string) $entry->amountNet);
        }

        if ($entry->ivaCommonUse && $amounts['taxes'] > 0) {
            $writer->writeElement('IVAUsoComun', (string) $amounts['taxes']);
        } else {
            $writer->writeElement('MntIVA', (string) $amounts['taxes']);
        }

        if ($entry->ivaRetainedTotal) {
            $writer->writeElement('IVARetTotal', (string) $amounts['taxes']);
        }

        $writer->writeElement('MntTotal', (string) $amounts['total']);

        $writer->endElement();
    }
}
