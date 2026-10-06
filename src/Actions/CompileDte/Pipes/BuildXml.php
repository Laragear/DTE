<?php

namespace Laragear\Dte\Actions\CompileDte\Pipes;

use Closure;
use Illuminate\Support\DateFactory;
use Laragear\Dte\Actions\CompileDte\Compilation;
use Laragear\Dte\Models\SiiDtePayload;
use Laragear\Dte\Support\Timestamp;
use Laragear\Dte\Support\XmlDomFactory;
use XMLWriter;
use function preg_replace;
use const LIBXML_NONET;

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
        $payload = $compilation->payload();

        $writer->startElement('Documento');
        $writer->writeAttribute('ID', "F{$dte->folio}T{$dte->document_type->value}");

        $this->appendHeader($writer, $payload, (int) $dte->folio);

        $payload->detail_items->toXml($writer);
        $payload->subtotals->toXml($writer);
        $payload->global_modifiers->toXml($writer);
        $payload->references->toXml($writer);
        $payload->emission_georef->toXml($writer);
        $payload->timber_handling->toXml($writer);
        $payload->commissions->toXml($writer);

        $writer->writeElement('TmstFirma', Timestamp::formatSii($this->date->now('America/Santiago')));

        $writer->endElement(); // Documento
    }

    /**
     * Append the Encabezado block through its child blocks.
     */
    protected function appendHeader(XMLWriter $writer, SiiDtePayload $payload, int $folio): void
    {
        $writer->startElement('Encabezado');

        $payload->header_id_doc->toXml($writer, $folio);
        $payload->header_issuer->toXml($writer);
        $payload->header_receiver->toXml($writer);
        $payload->header_transport->toXml($writer);
        $payload->header_totals->toXml($writer);
        $payload->header_other_currency->toXml($writer);

        $writer->endElement(); // Encabezado
    }
}
