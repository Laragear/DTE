<?php

namespace Laragear\Dte\Gateways;

use DOMDocument;
use DOMElement;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Dte\Support\XmlDomFactory;
use Laragear\Dte\Xml\XmlSigner;

/**
 * Build the signed XML request for SII REST token exchange.
 */
class SignedTokenRequestBuilder
{
    /**
     * The XML declaration version used in the signed token request.
     */
    protected const string XML_VERSION = '1.0';

    /**
     * The XML declaration encoding used in the signed token request.
     */
    protected const string XML_ENCODING = 'UTF-8';

    /**
     * The ID attribute value required by XmlSigner::sign().
     */
    protected const string TOKEN_ELEMENT_ID = 'GetToken';

    /**
     * Create a new signed token request builder instance.
     */
    public function __construct(
        protected XmlDomFactory $xml,
        protected XmlSigner $signer,
    ) {
        //
    }

    /**
     * Build an enveloped-signed `<getToken>` XML string for a seed.
     *
     * @see https://www4c.sii.cl/bolcoreinternetui/api/openapi.yaml (getToken step)
     */
    public function build(string $seed, DigitalCertificate $certificate): string
    {
        // Constructs the DOM tree required by SII's /boleta.electronica.token
        // endpoint with a `<getToken>` root containing
        // `<item><Semilla>{seed}</Semilla></item>` and applies an XMLDSig
        // signature via XmlSigner. The result is serialized as a single-line
        // string with a properly formatted XML declaration.
        $tokenXml = $this->createDocument();
        $getToken = $this->createRootElement($tokenXml);

        $this->appendSeedItem($tokenXml, $getToken, $seed);

        $this->sign($getToken, $certificate);

        return $this->serialize($tokenXml);
    }

    /**
     * Create a new DOMDocument configured for SII token requests.
     */
    protected function createDocument(): DOMDocument
    {
        return $this->xml->document(static::XML_VERSION, static::XML_ENCODING);
    }

    /**
     * Create the <getToken> root element with its required ID attribute.
     */
    protected function createRootElement(DOMDocument $doc): DOMElement
    {
        $root = $doc->createElement('getToken');
        $root->setAttribute('ID', static::TOKEN_ELEMENT_ID);

        $doc->appendChild($root);

        return $root;
    }

    /**
     * Append the <item><Semilla>{seed}</Semilla></item> structure.
     */
    protected function appendSeedItem(DOMDocument $doc, DOMElement $root, string $seed): void
    {
        $item = $doc->createElement('item');
        $semilla = $doc->createElement('Semilla', $seed);

        $item->appendChild($semilla);
        $root->appendChild($item);
    }

    /**
     * Sign the getToken element with the provided certificate.
     */
    protected function sign(DOMElement $target, DigitalCertificate $certificate): void
    {
        $this->signer->sign($target, $certificate);
    }

    /**
     * Serialize the document to a single-line XML string.
     */
    protected function serialize(DOMDocument $doc): string
    {
        // Strips all whitespace/newlines from the body while preserving a
        // properly formatted single XML declaration line at the top.
        $xmlString = $doc->saveXML();
        $xmlString = str_replace(["\r", "\n"], '', $xmlString);
        $xmlString = str_replace(
            '<?xml version="1.0" encoding="UTF-8"?>',
            "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n",
            $xmlString
        );

        return $xmlString;
    }
}
