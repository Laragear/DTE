<?php

namespace Laragear\Dte\Models\Concerns;

use DOMDocument;
use InvalidArgumentException;
use Laragear\Dte\Support\XmlDomFactory;
use LogicException;
use SimpleXMLElement;
use Throwable;
use function app;
use function is_string;
use const LIBXML_NONET;

trait HasXmlPayload
{
    /*
     |--------------------------------------------------------------------------
     | XML payload utilities
     |--------------------------------------------------------------------------
     */

    /**
     * Parse the stored XML payload into a DOM document.
     */
    public function toDomDocument(): DOMDocument
    {
        $document = $this->xmlDomFactory()->document();

        try {
            $loaded = $document->loadXML($this->xmlPayload(), LIBXML_NONET);
        } catch (Throwable $e) {
            throw new InvalidArgumentException('The model XML payload is malformed.', previous: $e);
        }

        if ($loaded === false) {
            throw new InvalidArgumentException('The model XML payload is malformed.');
        }

        return $document;
    }

    /**
     * Parse the stored XML payload into a SimpleXML element.
     */
    public function toSimpleXml(): SimpleXMLElement
    {
        $xml = $this->xmlPayload();

        try {
            return $this->xmlDomFactory()->simpleXml($xml, LIBXML_NONET);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('The model XML payload is malformed.', previous: $exception);
        }
    }

    /**
     * Resolve the XML DOM factory.
     */
    protected function xmlDomFactory(): XmlDomFactory
    {
        return app(XmlDomFactory::class);
    }

    /**
     * Return the stored XML payload.
     */
    protected function xmlPayload(): string
    {
        $xml = $this->getAttribute('xml');

        if (!is_string($xml)) {
            throw new LogicException('The model does not contain an XML payload.');
        }

        return $xml;
    }
}
