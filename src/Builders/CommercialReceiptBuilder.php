<?php

namespace Laragear\Dte\Builders;

use DateTimeImmutable;
use Illuminate\Support\DateFactory;
use InvalidArgumentException;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\SiiInboundDocument;
use Laragear\Dte\Support\Timestamp;
use Laragear\Dte\Support\XmlDomFactory;
use Laragear\Dte\Xml\XmlSigner;
use Laragear\Rut\Rut;
use XMLWriter;

class CommercialReceiptBuilder
{
    public const string DECLARATION = 'El acuse de recibo que se declara en este acto, de acuerdo a lo dispuesto en la letra b) del Art. 4, y la letra c) del Art. 5 de la Ley 19.983, acredita que la entrega de mercaderias o servicio(s) prestado(s) ha(n) sido recibido(s).';

    /**
     * Create a new Commercial Receipt Builder instance.
     */
    public function __construct(
        protected XmlDomFactory $xml,
        protected XmlSigner $signer,
        protected DateFactory $date,
    ) {
        //
    }

    /**
     * Build a signed Ley 19.983 commercial receipt envelope.
     */
    public function build(
        SiiInboundDocument $dte,
        Rut $signer,
        string $location,
        DigitalCertificate $certificate,
        ?DateTimeImmutable $signedAt = null,
    ): string {
        $this->validateType($dte->document_type);
        $signedAt ??= $this->date->now('America/Santiago')->toDateTimeImmutable();

        $writer = $this->xml->createWriter();
        $writer->startElementNS(null, 'EnvioRecibos', XmlDomFactory::XML_NAMESPACE);
        $writer->writeAttribute('version', '1.0');

        $setID = 'SetRecibos-'.$dte->folio;
        $receiptID = 'Recibo-'.$dte->document_type->value.'-'.$dte->folio;

        $this->writeSet($writer, $dte, $signedAt, $setID, $receiptID, $signer, $location);

        $writer->endElement(); // EnvioRecibos
        $writer->endDocument();

        $xmlString = $writer->outputMemory();

        return $this->signer->signString($xmlString, $certificate, [$receiptID, $setID]);
    }

    /**
     * Write the set of receipts structure for the commercial receipt.
     */
    protected function writeSet(
        XMLWriter $writer,
        SiiInboundDocument $dte,
        DateTimeImmutable $signedAt,
        string $setID,
        string $receiptID,
        Rut $signer,
        string $location
    ): void {
        $writer->startElement('SetRecibos');
        $writer->writeAttribute('ID', $setID);

        $writer->startElement('Caratula');
        $writer->writeAttribute('version', '1.0');
        $writer->writeElement('RutResponde', $dte->receiver_rut->formatBasic());
        $writer->writeElement('RutRecibe', $dte->issuer_rut->formatBasic());
        $writer->writeElement('TmstFirmaEnv', Timestamp::formatSii($signedAt));
        $writer->endElement(); // Caratula

        $this->writeReceipt($writer, $dte, $receiptID, $signer, $location, $signedAt);

        $writer->endElement(); // SetRecibos
    }

    /**
     * Write the individual receipt element and its details.
     */
    protected function writeReceipt(
        XMLWriter $writer,
        SiiInboundDocument $dte,
        string $receiptID,
        Rut $signer,
        string $location,
        DateTimeImmutable $signedAt
    ): void {
        $writer->startElement('Recibo');
        $writer->writeAttribute('version', '1.0');

        $writer->startElement('DocumentoRecibo');
        $writer->writeAttribute('ID', $receiptID);
        $this->appendDocument($writer, $dte);
        $writer->writeElement('Recinto', $location);
        $writer->writeElement('RutFirma', $signer->formatBasic());
        $writer->writeElement('Declaracion', static::DECLARATION);
        $writer->writeElement('TmstFirmaRecibo', Timestamp::formatSii($signedAt));
        $writer->endElement(); // DocumentoRecibo

        $writer->endElement(); // Recibo
    }

    /**
     * Append the document details to the XML writer.
     */
    protected function appendDocument(XMLWriter $writer, SiiInboundDocument $dte): void
    {
        $writer->writeElement('TipoDoc', (string) $dte->document_type->value);
        $writer->writeElement('Folio', (string) $dte->folio);
        $writer->writeElement('FchEmis', $dte->issued_on->format('Y-m-d'));
        $writer->writeElement('RUTEmisor', $dte->issuer_rut->formatBasic());
        $writer->writeElement('RUTRecep', $dte->receiver_rut->formatBasic());
        $writer->writeElement('MntTotal', (string) $dte->amount_total);
    }

    /**
     * Validate if the DTE type supports a commercial receipt.
     */
    protected function validateType(DteType $type): void
    {
        if (!in_array($type, $this->supportedTypes(), true)) {
            throw new InvalidArgumentException('The DTE type does not support a Ley 19.983 commercial receipt.');
        }
    }

    /**
     * Return the list of supported DTE types for commercial receipts.
     *
     * @return list<DteType>
     */
    protected function supportedTypes(): array
    {
        return [
            DteType::Invoice,
            DteType::InvoiceExempt,
            DteType::InvoiceLiquidation,
            DteType::PurchaseInvoice,
            DteType::DispatchGuide,
        ];
    }
}
