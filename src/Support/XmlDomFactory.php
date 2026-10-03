<?php

namespace Laragear\Dte\Support;

use DOMDocument;
use DOMXPath;
use Laragear\Dte\Proxies\LibxmlProxy;
use SimpleXMLElement;
use XMLWriter;

/** @internal */
class XmlDomFactory
{
    public const string XML_NAMESPACE = 'http://www.sii.cl/SiiDte';

    public const string ENCODING = 'ISO-8859-1';

    /**
     * Create a new XML DOM Factory instance.
     */
    public function __construct(
        protected LibxmlProxy $libxml,
    ) {
        //
    }

    /**
     * Create a strict DOM document.
     */
    public function document(string $version = '1.0', string $encoding = 'UTF-8'): DOMDocument
    {
        $document = new DOMDocument($version, $encoding);
        $document->formatOutput = false;
        $document->preserveWhiteSpace = true;
        $document->strictErrorChecking = true;

        return $document;
    }

    /**
     * Create a SimpleXML element without external network access.
     */
    public function simpleXml(string $xml, int $options = LIBXML_NONET): SimpleXMLElement
    {
        $previous = $this->libxml->useInternalErrors(true);

        try {
            return new SimpleXMLElement($xml, $options);
        } finally {
            $this->libxml->clearErrors();
            $this->libxml->useInternalErrors($previous);
        }
    }

    /**
     * Create a new DOM XPath instance.
     */
    public function xpath(DOMDocument $document): DOMXPath
    {
        return new DOMXPath($document);
    }

    /**
     * Create a new XML Writer instance.
     */
    public function writer(): XMLWriter
    {
        return new XMLWriter;
    }

    /**
     * Create an XMLWriter configured for SII document generation.
     */
    public function createWriter(): XMLWriter
    {
        $writer = $this->writer();
        $writer->openMemory();
        $writer->startDocument('1.0', static::ENCODING);

        return $writer;
    }
}
