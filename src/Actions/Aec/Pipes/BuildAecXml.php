<?php

namespace Laragear\Dte\Actions\Aec\Pipes;

use Closure;
use InvalidArgumentException;
use Laragear\Dte\Actions\Aec\AecData;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Support\Timestamp;
use Laragear\Dte\Support\XmlDomFactory;
use XMLWriter;
use function is_string;

class BuildAecXml
{
    /**
     * Create a new Build AEC XML instance.
     */
    public function __construct(
        protected XmlDomFactory $xml,
    ) {
        //
    }

    /**
     * Handle the incoming AEC Data.
     *
     * @param  Closure(AecData): AecData  $next
     */
    public function handle(AecData $data, Closure $next): AecData
    {
        $data->xmlString = $this->buildXml($data);

        return $next($data);
    }

    /**
     * Initializes the XML document structure and coordinates writing all sub-elements.
     */
    protected function buildXml(AecData $data): string
    {
        $writer = $this->xml->createWriter();
        $writer->startElementNS(null, 'AEC', XmlDomFactory::XML_NAMESPACE);
        $writer->writeAttribute('version', '1.0');

        $this->writeDocumentoAec($writer, $data);

        $writer->endElement(); // AEC
        $writer->endDocument();

        return $writer->outputMemory();
    }

    /**
     * Appends the primary DocumentoAEC node containing the cover and assignments.
     */
    protected function writeDocumentoAec(XMLWriter $writer, AecData $data): void
    {
        $writer->startElement('DocumentoAEC');
        $writer->writeAttribute('ID', $data->aecID);

        $this->writeCaratula($writer, $data);
        $this->writeCesiones($writer, $data);

        $writer->endElement(); // DocumentoAEC
    }

    /**
     * Appends the header element with routing RUTs and submission timestamp.
     */
    protected function writeCaratula(XMLWriter $writer, AecData $data): void
    {
        $writer->startElement('Caratula');
        $writer->writeAttribute('version', '1.0');
        $writer->writeElement('RutCedente', $data->dte->issuer_rut->formatBasic());
        $writer->writeElement('RutCesionario', $data->cession->assigneeRut->formatBasic());
        $writer->writeElement('TmstFirmaEnvio', Timestamp::formatSii($data->signedAt));
        $writer->endElement();
    }

    /**
     * Appends the container element grouping the ceded DTE and cession data.
     */
    protected function writeCesiones(XMLWriter $writer, AecData $data): void
    {
        $writer->startElement('Cesiones');
        $this->writeDteCedido($writer, $data);
        $this->writeCesion($writer, $data);
        $writer->endElement();
    }

    /**
     * Appends the ceded DTE block with source, receipt, and signature.
     */
    protected function writeDteCedido(XMLWriter $writer, AecData $data): void
    {
        $writer->startElement('DTECedido');
        $writer->writeAttribute('version', '1.0');
        $writer->startElement('DocumentoDTECedido');
        $writer->writeAttribute('ID', $data->dtecID);

        $writer->writeRaw($this->sourceElementStr($this->dteXml($data->dte), 'DTE'));
        $writer->writeRaw($this->sourceElementStr($data->receiptXml, 'Recibo'));
        $writer->writeElement('TmstFirma', Timestamp::formatSii($data->signedAt));

        $writer->endElement(); // DocumentoDTECedido
        $writer->endElement(); // DTECedido
    }

    /**
     * Appends the legal cession node containing sequence, party details, and terms.
     */
    protected function writeCesion(XMLWriter $writer, AecData $data): void
    {
        $writer->startElement('Cesion');
        $writer->writeAttribute('version', '1.0');
        $writer->startElement('DocumentoCesion');
        $writer->writeAttribute('ID', $data->cessionID);

        $writer->writeElement('SeqCesion', '1');
        $this->writeDteIdentity($writer, $data->dte);
        $this->writeCedent($writer, $data);
        $this->writeAssignee($writer, $data->cession);
        $this->writeTerms($writer, $data);

        $writer->endElement(); // DocumentoCesion
        $writer->endElement(); // Cesion
    }

    /**
     * Appends the core identification element of the DTE (type, folio, amounts).
     */
    protected function writeDteIdentity(XMLWriter $writer, SiiDte $dte): void
    {
        $writer->startElement('IdDTE');
        $writer->writeElement('TipoDTE', (string) $dte->document_type->value);
        $writer->writeElement('RUTEmisor', $dte->issuer_rut->formatBasic());
        $writer->writeElement('RUTReceptor', $dte->receiver_rut->formatBasic());
        $writer->writeElement('Folio', (string) $dte->folio);
        $writer->writeElement('FchEmis', $dte->issued_on->format('Y-m-d'));
        $writer->writeElement('MntTotal', (string) $dte->amount_total);
        $writer->endElement(); // IdDTE
    }

    /**
     * Appends the cedent element with issuer and signer info.
     */
    protected function writeCedent(XMLWriter $writer, AecData $data): void
    {
        $issuer = $data->dte->payload?->header_issuer->toArray() ?? [];

        $writer->startElement('Cedente');
        $writer->writeElement('RUT', $data->dte->issuer_rut->formatBasic());
        $writer->writeElement('RazonSocial', (string) ($issuer['name'] ?? ''));
        $writer->writeElement('Direccion', (string) ($issuer['address'] ?? ''));
        $writer->writeElement('eMail', $data->cedentEmail);

        $writer->startElement('RUTAutorizado');
        $writer->writeElement('RUT', $data->authorizedSigner->formatBasic());
        $writer->writeElement('Nombre', $data->authorizedName);
        $writer->endElement(); // RUTAutorizado
        $writer->endElement(); // Cedente
    }

    /**
     * Appends the assignee element with the new creditor info.
     */
    protected function writeAssignee(XMLWriter $writer, mixed $cession): void
    {
        $writer->startElement('Cesionario');
        $writer->writeElement('RUT', $cession->assigneeRut->formatBasic());
        $writer->writeElement('RazonSocial', $cession->assigneeName);
        $writer->writeElement('Direccion', $cession->assigneeAddress);
        $writer->writeElement('eMail', $cession->assigneeEmail);
        $writer->endElement();
    }

    /**
     * Appends the financial terms, due dates, debtor email, and transaction timestamp.
     */
    protected function writeTerms(XMLWriter $writer, AecData $data): void
    {
        $writer->writeElement('MontoCesion', (string) $data->cession->amount);
        $writer->writeElement('UltimoVencimiento', $data->cession->lastDueDate->format('Y-m-d'));

        if ($data->cession->terms !== null) {
            $writer->writeElement('OtrasCondiciones', $data->cession->terms);
        }

        $email = $data->dte->payload?->header_receiver->email;

        if (is_string($email) && $email !== '') {
            $writer->writeElement('eMailDeudor', $email);
        }

        $writer->writeElement('TmstCesion', Timestamp::formatSii($data->signedAt));
    }

    /**
     * Retrieves the raw DTE XML payload or throws if it's missing.
     */
    protected function dteXml(SiiDte $dte): string
    {
        return $dte->payload?->xml ?? throw new InvalidArgumentException(
            'The AEC requires a compiled DTE with a folio and signed XML payload.',
        );
    }

    /**
     * Extracts a specific XML element string from a raw document using XPath.
     */
    protected function sourceElementStr(string $xml, string $name): string
    {
        $document = $this->xml->document();

        if (!@$document->loadXML($xml, LIBXML_NONET)) {
            throw new InvalidArgumentException("The [$name] XML payload is invalid.");
        }

        $element = $this->xml->xpath($document)->query("//*[local-name()='$name']")?->item(0);

        if (!$element) {
            throw new InvalidArgumentException("The XML payload does not contain a [$name] element.");
        }

        return $document->saveXML($element);
    }
}
