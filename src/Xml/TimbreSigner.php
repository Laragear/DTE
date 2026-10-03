<?php

namespace Laragear\Dte\Xml;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMText;
use InvalidArgumentException;
use Laragear\Dte\Proxies\OpenSslProxy;
use Laragear\Dte\Support\XmlDomFactory;
use function mb_convert_encoding;
use function preg_replace;

class TimbreSigner
{
    /**
     * Create a Timbre Signer instance.
     */
    public function __construct(
        protected OpenSslProxy $openSsl,
        protected XmlDomFactory $xml,
    ) {
        //
    }

    /**
     * Sign the TED details using the SII flatten recipe (not XMLDSig C14N).
     */
    public function sign(DOMElement $details, string $privateKey): string
    {
        // Per the instructivo (A.2.4), the signature is over the <DD> string
        // with all characters between closing and opening tags removed,
        // namespace references stripped, and text inside terminal elements
        // preserved unchanged.
        if ($details->localName !== 'DD') {
            throw new InvalidArgumentException('The TED signature must target a DD element.');
        }

        $canonical = $this->flatten($this->details($details));

        return $this->openSsl->signRaw($canonical, $privateKey);
    }

    /**
     * Reconstruct the namespace-free DD fragment required by SII.
     */
    protected function details(DOMElement $details): DOMElement
    {
        $document = $this->xml->document('1.0', XmlDomFactory::ENCODING);
        $clone = $this->cloneElement($details, $document);
        $document->appendChild($clone);

        return $clone;
    }

    /**
     * Flatten the DD: serialize to ISO-8859-1, then strip inter-tag whitespace.
     */
    protected function flatten(DOMElement $details): string
    {
        // PHP's DOM outputs UTF-8 bytes even when declared ISO-8859-1, so we
        // convert to the correct encoding before flattening.
        $xml = $details->ownerDocument->saveXML($details->ownerDocument->documentElement);
        $iso = mb_convert_encoding($xml, XmlDomFactory::ENCODING, 'UTF-8');

        // Remove every character between a closing tag and the next opening tag
        return preg_replace('/>\s+</', '><', $iso);
    }

    /**
     * Clone an element without inherited namespaces.
     */
    protected function cloneElement(DOMElement $source, DOMDocument $document): DOMElement
    {
        $clone = $document->createElement((string) $source->localName);

        foreach ($source->attributes as $attribute) {
            if ($attribute instanceof DOMAttr && $attribute->namespaceURI !== 'http://www.w3.org/2000/xmlns/') {
                $clone->setAttribute((string) $attribute->localName, $attribute->value);
            }
        }

        foreach ($source->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $clone->appendChild($this->cloneElement($child, $document));
            } elseif ($child instanceof DOMText) {
                $clone->appendChild($document->createTextNode($child->data));
            }
        }

        return $clone;
    }
}
