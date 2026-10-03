<?php

namespace Laragear\Dte\Xml;

use DOMElement;
use DOMNode;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laragear\Dte\Certificate\DigitalCertificate as Cert;
use Laragear\Dte\Proxies\LibxmlProxy;
use Laragear\Dte\Proxies\OpenSslProxy;
use Laragear\Dte\Support\CertificateParser;
use Laragear\Dte\Support\XmlDomFactory;
use RuntimeException;
use function base64_encode;
use function is_array;
use function sha1;
use function str_replace;

class XmlSigner
{
    /**
     * Create a new XML Signer instance.
     */
    public function __construct(
        protected Filesystem $file,
        protected OpenSslProxy $openSsl,
        protected LibxmlProxy $libxml,
        protected XmlDomFactory $xml,
        protected CertificateParser $certificateParser,
    ) {
        //
    }

    /**
     * Sign a raw XML string and return the signed XML string.
     *
     * @param  array<string>  $targetIds
     */
    public function signString(string $xml, Cert $certificate, array $targetIds = []): string
    {
        $doc = $this->xml->document();

        $previous = $this->libxml->useInternalErrors(true);

        $doc->loadXML($xml, LIBXML_NONET);

        $this->libxml->clearErrors();
        $this->libxml->useInternalErrors($previous);

        try {
            if ($targetIds === []) {
                $root = $doc->documentElement
                    ?? throw new RuntimeException('Cannot sign: XML document has no root element.');

                $this->sign($root, $certificate);
            } else {
                $xpath = $this->xml->xpath($doc);

                foreach ($targetIds as $id) {
                    $target = $xpath->query("//*[@ID=\"$id\"]")->item(0)
                        ?? throw new RuntimeException("Cannot sign: XML document has no element with ID '$id'.");

                    $this->sign($target, $certificate);
                }
            }
        } finally {
            $this->libxml->clearErrors();
        }

        return $doc->saveXML();
    }

    /**
     * Apply the SII XMLDSig signature beside the referenced element.
     */
    public function sign(DOMElement $target, Cert $certificate): DOMNode|false
    {
        if ($target->parentNode === null) {
            throw new InvalidArgumentException('The XML signature target must be attached to a document.');
        }

        $id = $target->getAttribute('ID');

        if ($id === '') {
            throw new InvalidArgumentException('The XML signature target must have an ID attribute.');
        }

        $digest = $this->computeDigest($target);
        $signedInfoXml = $this->buildSignedInfoXml($id, $digest);

        return $this->injectSignature($certificate, $signedInfoXml, $target->parentNode);
    }

    /**
     * Sign the root element using an enveloped signature (URI="").
     *
     * @see https://www4c.sii.cl/bolcoreinternetui/api/openapi.yaml (getToken step)
     * @see https://www.sii.cl/factura_electronica/factura_mercado/autenticacion.pdf
     */
    public function signRoot(DOMElement $root, Cert $certificate): DOMNode|false
    {
        $digest = $this->computeDigest($root);

        // This is used for the SII seed token auth flow (CrSeed → GetTokenFromSeed),
        // which requires a different XMLDSig format than DTE documents:
        // - Uses Reference URI="" instead of URI="#{id}"
        // - The root element does not need an ID attribute
        $signedInfoXml = $this->buildEnvelopedSignedInfoXml($digest);

        return $this->injectSignature($certificate, $signedInfoXml, $root);
    }

    /**
     * Build the SignedInfo XML string for an enveloped signature (URI="").
     */
    protected function buildEnvelopedSignedInfoXml(string $digest): string
    {
        // This format is required by the SII seed token auth flow, where the entire
        // document is signed without referencing a specific element by ID.
        return str_replace(
            '{$digest}',
            $digest,
            $this->file->get(__DIR__.'/stubs/enveloped_signedinfo.stub')
        );
    }

    /**
     * Compute the digest of the target element.
     */
    protected function computeDigest(DOMElement $target): string
    {
        return base64_encode(sha1($target->C14N(false, false), true));
    }

    /**
     * Build the SignedInfo XML string.
     */
    protected function buildSignedInfoXml(string $id, string $digest): string
    {
        return str_replace(
            ['{$id}', '{$digest}'],
            [$id, $digest],
            $this->file->get(__DIR__.'/stubs/signedinfo.stub')
        );
    }

    /**
     * Extract RSA modulus and exponent from the certificate.
     */
    protected function extractRsaComponents(string $privateKey): array
    {
        $details = $this->openSsl->privateKeyDetails($privateKey);
        $rsa = is_array($details) ? $details['rsa'] ?? null : null;

        if (!is_array($rsa)) {
            throw new RuntimeException('Unable to extract the certificate RSA public key.');
        }

        return [Str::toBase64($rsa['n']), Str::toBase64($rsa['e'])];
    }

    /**
     * Build the full Signature XML string.
     */
    protected function buildSignatureXml(
        string $signedInfoXml,
        string $signatureValue,
        string $modulus,
        string $exponent,
        string $x509b64
    ): string {
        return str_replace(
            ['{$signedInfoXml}', '{$signatureValue}', '{$modulus}', '{$exponent}', '{$x509b64}'],
            [$signedInfoXml, $signatureValue, $modulus, $exponent, $x509b64],
            $this->file->get(__DIR__.'/stubs/signature.stub')
        );
    }

    /**
     * Creates the signature using the certificate and injects it into the node.
     *
     * @param  DOMNode  $parent  The node that will be the parent of the <Signature> element.
     */
    protected function injectSignature(Cert $certificate, string $signedInfoXml, DOMNode $parent): DOMNode|false
    {
        $pem = $this->openSsl->readPkcs12String($certificate->pkcs12, $certificate->password);

        [$modulus, $exponent] = $this->extractRsaComponents($pem['pkey']);
        $x509b64 = $this->certificateParser->parse($pem['cert']);

        // Build the Signature XML with a placeholder SignatureValue.
        $signatureXml = $this->buildSignatureXml($signedInfoXml, '', $modulus, $exponent, $x509b64);

        $sigDoc = $this->xml->document();
        $sigDoc->loadXML($signatureXml);

        // Import into the target document and attach, so that inherited namespace
        // declarations (e.g. xmlns:xsi from EnvioDTE) are visible for C14N.
        $document = $parent->ownerDocument ?? $parent;
        $sigNode = $document->importNode($sigDoc->documentElement, true);
        $parent->appendChild($sigNode);

        // Compute C14N in the final DOM context and sign.
        $signedInfo = $sigNode->getElementsByTagName('SignedInfo')->item(0);

        // The signature value is computed after the Signature node is imported and
        // attached to the target document so that C14N produces the same bytes as the
        // SII verifier will see, including any namespace declarations inherited from
        // ancestor elements (e.g. xmlns:xsi from EnvioDTE).
        $signatureValue = $this->openSsl->sign($signedInfo->C14N(false, false), $pem['pkey']);

        $signatureValueNode = $sigNode->getElementsByTagName('SignatureValue')->item(0);
        $signatureValueNode->textContent = $signatureValue;

        return $sigNode;
    }
}
