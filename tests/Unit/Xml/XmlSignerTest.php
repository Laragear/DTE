<?php

namespace Tests\Unit\Xml;

use Closure;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Process\Factory;
use InvalidArgumentException;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Dte\Proxies\OpenSslProxy;
use Laragear\Dte\Support\XmlDomFactory;
use Laragear\Dte\Xml\XmlSigner;
use Laragear\Dte\Xml\XmlValidator;
use Mockery;
use RuntimeException;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Tests\TestCase;
use Tests\Unit\Certificate\Fixtures\CertificateFixture;

class XmlSignerTest extends TestCase
{
    protected XmlSigner $signer;

    /**
     * Create the signer under test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->signer = $this->app->make(XmlSigner::class);
    }

    /*
     |--------------------------------------------------------------------------
     | Happy Paths
     |--------------------------------------------------------------------------
     */

    public function test_appends_a_verifiable_sii_xmldsig_beside_the_target(): void
    {
        $this->withCertificate(function (DigitalCertificate $certificate): void {
            $document = $this->document('<DTE><Documento ID="F1"><Total>100</Total></Documento></DTE>');
            $target = $this->element($this->app->make(XmlDomFactory::class)->xpath($document), '/DTE/Documento');
            $signature = $this->signer->sign($target, $certificate);

            static::assertSame($target->parentNode, $signature->parentNode);
            static::assertSignatureStructure($document, $certificate);
            static::assertValidSignature($document, $certificate);
        });
    }

    /*
     |--------------------------------------------------------------------------
     | Sad Paths
     |--------------------------------------------------------------------------
     */

    public function test_rejects_a_target_without_an_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The XML signature target must have an ID attribute.');

        $this->withCertificate(function (DigitalCertificate $certificate): void {
            $document = $this->document('<DTE><Documento/></DTE>');

            $this->signer->sign(
                $this->element($this->app->make(XmlDomFactory::class)->xpath($document), '/DTE/Documento'),
                $certificate
            );
        });
    }

    public function test_rejects_a_detached_target(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The XML signature target must be attached to a document.');

        $this->withCertificate(function (DigitalCertificate $certificate): void {
            $this->signer->sign(
                $this->app->make(XmlDomFactory::class)->document()->createElement('Documento'),
                $certificate
            );
        });
    }

    public function test_rejects_certificate_with_missing_rsa_details(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Unable to extract the certificate RSA public key.');

        $proxyMock = Mockery::mock(OpenSslProxy::class, [
            $this->app->make(TemporaryDirectory::class),
            $this->app->make(Filesystem::class),
            $this->app->make(Factory::class),
        ])->makePartial();

        $proxyMock->expects('privateKeyDetails')->andReturnNull();
        $this->app->instance(OpenSslProxy::class, $proxyMock);

        $signer = $this->app->make(XmlSigner::class);

        $this->withCertificate(function (DigitalCertificate $certificate) use ($signer): void {
            $document = $this->document('<DTE><Documento ID="F1"><Total>100</Total></Documento></DTE>');
            $target = $this->element($this->app->make(XmlDomFactory::class)->xpath($document), '/DTE/Documento');
            $signer->sign($target, $certificate);
        });
    }

    public function test_detects_tampering_after_signing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid DTE XML: XMLDSig digest reference does not match.');

        $this->withCertificate(function (DigitalCertificate $certificate): void {
            $document = $this->document('<DTE><Documento ID="F1"><Total>100</Total></Documento></DTE>');
            $target = $this->element($this->app->make(XmlDomFactory::class)->xpath($document), '/DTE/Documento');
            $this->signer->sign($target, $certificate);

            $this->element($this->app->make(XmlDomFactory::class)->xpath($document),
                '/DTE/Documento/Total')->textContent = '101';

            $validator = $this->app->make(XmlValidator::class);
            $validator->verifySignature($document->saveXML());
        });
    }

    /**
     * Execute a test with a temporary valid digital certificate.
     *
     * @param  Closure(DigitalCertificate): void  $callback
     */
    protected function withCertificate(Closure $callback): void
    {
        $fixture = CertificateFixture::create();

        try {
            $callback(new DigitalCertificate(file_get_contents($fixture->path), $fixture->password));
        } finally {
            $fixture->delete();
        }
    }

    /**
     * Assert the algorithms and certificate emitted for SII.
     */
    protected function assertSignatureStructure(DOMDocument $document, DigitalCertificate $certificate): void
    {
        $xpath = $this->app->make(XmlDomFactory::class)->xpath($document);
        $xpath->registerNamespace('ds', XmlValidator::XMLDSIGNS);

        static::assertSame('http://www.w3.org/TR/2001/REC-xml-c14n-20010315', $this->attribute(
            $xpath,
            '//ds:CanonicalizationMethod',
            'Algorithm',
        ));
        static::assertSame('http://www.w3.org/2000/09/xmldsig#rsa-sha1', $this->attribute(
            $xpath,
            '//ds:SignatureMethod',
            'Algorithm',
        ));
        static::assertSame('http://www.w3.org/TR/2001/REC-xml-c14n-20010315', $this->attribute(
            $xpath,
            '//ds:Transform',
            'Algorithm',
        ));
        static::assertSame('http://www.w3.org/2000/09/xmldsig#sha1', $this->attribute(
            $xpath,
            '//ds:DigestMethod',
            'Algorithm',
        ));
        static::assertSame('#F1', $this->attribute($xpath, '//ds:Reference', 'URI'));
        static::assertNotSame('', $this->element($xpath, '//ds:RSAKeyValue/ds:Modulus')->textContent);
        static::assertNotSame('', $this->element($xpath, '//ds:RSAKeyValue/ds:Exponent')->textContent);
        static::assertStringContainsString('MII', $this->element($xpath, '//ds:X509Certificate')->textContent);
    }

    /**
     * Assert the signature and digest are cryptographically valid.
     */
    protected function assertValidSignature(DOMDocument $document): void
    {
        $validator = $this->app->make(XmlValidator::class);

        static::assertTrue($validator->verifySignature($document->saveXML()));
    }

    /**
     * Parse an XML test document.
     */
    protected function document(string $xml): DOMDocument
    {
        $document = $this->app->make(XmlDomFactory::class)->document();

        if (!$document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('Unable to load the XML test fixture.');
        }

        return $document;
    }

    /**
     * Return a required signature element.
     */
    protected function element(DOMXPath $xpath, string $query): DOMElement
    {
        $element = $xpath->query($query)?->item(0);

        return $element instanceof DOMElement
            ? $element
            : throw new RuntimeException("Missing XML signature element [$query].");
    }

    /**
     * Return an attribute from a required signature element.
     */
    protected function attribute(DOMXPath $xpath, string $query, string $attribute): string
    {
        return $this->element($xpath, $query)->getAttribute($attribute);
    }

    public function test_throws_when_xml_has_no_root_element(): void
    {
        $this->withCertificate(function ($certificate) {
            $signer = $this->app->make(XmlSigner::class);
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageIs('Cannot sign: XML document has no root element.');
            $signer->signString('<?xml version="1.0"?>', $certificate);
        });
    }

    public function test_throws_when_xml_element_by_id_is_not_found(): void
    {
        $this->withCertificate(function ($certificate) {
            $signer = $this->app->make(XmlSigner::class);
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageIs("Cannot sign: XML document has no element with ID 'does-not-exist'.");
            $signer->signString('<root ID="root"></root>', $certificate, ['does-not-exist']);
        });
    }

    public function test_signs_string_without_target_ids(): void
    {
        $this->withCertificate(function ($certificate) {
            $signer = $this->app->make(XmlSigner::class);
            $xml = '<?xml version="1.0"?><root ID="root"></root>';
            $signed = $signer->signString($xml, $certificate);
            static::assertStringContainsString('<Signature xmlns="http://www.w3.org/2000/09/xmldsig#">', $signed);
        });
    }
}
