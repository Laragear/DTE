<?php

namespace Laragear\Dte\Xml;

use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Contracts\Container\Container;
use Laragear\Dte\Proxies\LibxmlProxy;
use Laragear\Dte\Proxies\OpenSslProxy;
use Laragear\Dte\Support\XmlDomFactory;
use RuntimeException;
use Throwable;
use function base64_decode;
use function base64_encode;
use function sha1;

/**
 * Validates the structural integrity and XMLDSig signature of an inbound DTE XML payload.
 */
class XmlValidator
{
    public const string XMLDSIGNS = 'http://www.w3.org/2000/09/xmldsig#';

    /**
     * Create a new Xml Validator instance.
     */
    public function __construct(
        protected Container $container,
        protected OpenSslProxy $openSsl,
        protected XmlDomFactory $xml,
        protected LibxmlProxy $libxml,
    ) {
        //
    }

    /**
     * Validate a DTE or EnvioDTE XML string.
     *
     * @throws RuntimeException If any check fails.
     */
    public function validate(string $xmlString): DOMDocument
    {
        $document = $this->parse($xmlString);

        $this->validateSignature($document);

        return $document;
    }

    /**
     * Verify only the XMLDSig signature is intact.
     *
     * @throws RuntimeException If the signature is missing or invalid.
     */
    public function verifySignature(string $xmlString, ?string $targetId = null): bool
    {
        $document = $this->parse($xmlString);

        return $this->validateSignature($document, $targetId);
    }

    /**
     * Verify the XMLDSig signature directly from a DOM document.
     */
    public function verifySignatureDocument(DOMDocument $document, ?string $targetId = null): bool
    {
        return $this->validateSignature($document, $targetId);
    }

    /**
     * Parse the raw XML string into a DOM document.
     *
     * @throws RuntimeException If the XML is malformed.
     */
    protected function parse(string $xml): DOMDocument
    {
        $document = $this->xml->document();
        $previous = $this->libxml->useInternalErrors(true);

        try {
            $loaded = $document->loadXML($xml, LIBXML_NONET);

            if (!$loaded) {
                throw new RuntimeException('Invalid DTE XML: the document is malformed or empty.');
            }
        } finally {
            $this->libxml->clearErrors();
            $this->libxml->useInternalErrors($previous);
        }

        return $document;
    }

    /**
     * Verify the XMLDSig signature embedded in the document.
     */
    protected function validateSignature(DOMDocument $document, ?string $targetId = null): bool
    {
        $xpath = $this->xml->xpath($document);
        $xpath->registerNamespace('ds', static::XMLDSIGNS);

        $signature = $targetId !== null
            ? $xpath->query("//ds:Signature[.//ds:Reference[@URI=\"#{$targetId}\"]]")->item(0)
            : $xpath->query('//ds:Signature')->item(0);

        if ($signature === null) {
            throw new RuntimeException(
                $targetId !== null
                    ? "Invalid DTE XML: XMLDSig signature for target [{$targetId}] is missing."
                    : 'Invalid DTE XML: XMLDSig signature is missing.',
            );
        }

        $x509Cert = $this->extractX509Cert($signature) ?? throw new RuntimeException(
            'Invalid DTE XML: X509 certificate is missing from KeyInfo.',
        );

        $this->verifyDigest($xpath, $signature);
        $this->verifySignatureValue($xpath, $signature, $x509Cert);

        return true;
    }

    /**
     * Verify the digest of the signed element.
     */
    protected function verifyDigest(DOMXPath $xpath, DOMNode $signature): void
    {
        $reference = $xpath->query('.//ds:Reference', $signature)->item(0) ?? throw new RuntimeException(
            'Invalid DTE XML: XMLDSig reference is missing.',
        );

        $id = ltrim($reference->getAttribute('URI'), '#');

        $target = $xpath->query("//*[@ID=\"$id\"]")->item(0) ?? throw new RuntimeException(
            'Invalid DTE XML: XMLDSig digest reference does not match.',
        );

        $digestValueNode = $xpath->query('.//ds:DigestValue', $reference)->item(0) ?? throw new RuntimeException(
            'Invalid DTE XML: XMLDSig digest value is missing.',
        );

        $expected = trim($digestValueNode->textContent);
        $actual = base64_encode(sha1($target->C14N(false, false), true));

        if ($expected !== $actual) {
            throw new RuntimeException('Invalid DTE XML: XMLDSig digest reference does not match.');
        }
    }

    /**
     * Verify the signature value using the public key.
     */
    protected function verifySignatureValue(DOMXPath $xpath, DOMNode $signature, string $x509Cert): void
    {
        $signedInfo = $xpath->query('.//ds:SignedInfo', $signature)->item(0) ?? throw new RuntimeException(
            'Invalid DTE XML: XMLDSig SignedInfo is missing.',
        );

        $signatureValueNode = $xpath->query('.//ds:SignatureValue', $signature)->item(0) ?? throw new RuntimeException(
            'Invalid DTE XML: XMLDSig SignatureValue is missing.',
        );

        $signatureValue = base64_decode(trim($signatureValueNode->textContent), true);

        if ($signatureValue === false) {
            throw new RuntimeException('Invalid DTE XML: XMLDSig SignatureValue is not valid base64.');
        }

        $signedInfoC14n = $signedInfo->C14N(false, false);

        try {
            $result = $this->openSsl->verify($signedInfoC14n, $signatureValue, $x509Cert, OPENSSL_ALGO_SHA1);
        } catch (Throwable $e) {
            throw new RuntimeException('Invalid DTE XML: XMLDSig signature verification failed.', previous: $e);
        }

        if ($result !== 1) {
            throw new RuntimeException('Invalid DTE XML: XMLDSig signature verification failed.');
        }
    }

    /**
     * Extract the embedded X509Certificate PEM string from the document's KeyInfo.
     */
    protected function extractX509Cert(DOMNode $context): ?string
    {
        $document = $context->ownerDocument;
        $nodes = $document->getElementsByTagNameNS(static::XMLDSIGNS, 'X509Certificate');

        if ($nodes->length === 0) {
            $nodes = $document->getElementsByTagName('X509Certificate');
        }

        // Find the X509Certificate node within this specific Signature context.
        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);

            if ($context->contains($node)) {
                return
                    "-----BEGIN CERTIFICATE-----\n"
                    .chunk_split(trim($node->textContent), 64, "\n")
                    ."-----END CERTIFICATE-----\n";
            }
        }

        return null;
    }
}
