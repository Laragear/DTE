<?php

namespace Laragear\Dte\Xml;

use DOMDocument;
use DOMException;
use DOMNode;
use Laragear\Dte\Proxies\LibxmlProxy;
use Laragear\Dte\Support\XmlDomFactory;
use function is_string;

class XmlCanonicalizer
{
    /**
     * Create an XML Canonicalizer instance.
     */
    public function __construct(
        protected XmlDomFactory $xml,
        protected LibxmlProxy $libxml,
    ) {
        //
    }

    /**
     * Canonicalize XML using inclusive W3C C14N without comments.
     */
    public function canonicalize(string|DOMNode $xml): string
    {
        $node = is_string($xml) ? $this->document($xml) : $xml;

        $canonical = $node->C14N();

        if ($canonical === false) {
            throw new DOMException('Unable to canonicalize the XML node.');
        }

        return $canonical;
    }

    /**
     * Parse XML without external network access.
     */
    protected function document(string $xml): DOMDocument
    {
        $document = $this->xml->document();
        $previous = $this->libxml->useInternalErrors(true);

        try {
            if (!$document->loadXML($xml, LIBXML_NONET)) {
                throw new DOMException('Unable to parse XML for canonicalization.');
            }
        } finally {
            $this->libxml->clearErrors();
            $this->libxml->useInternalErrors($previous);
        }

        return $document;
    }
}
