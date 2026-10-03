<?php

namespace Tests\Unit\Gateways;

use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Dte\Gateways\SignedTokenRequestBuilder;
use Laragear\Dte\Proxies\LibxmlProxy;
use Laragear\Dte\Proxies\OpenSslProxy;
use Laragear\Dte\Support\XmlDomFactory;
use Mockery\MockInterface;
use Tests\TestCase;

class SignedTokenRequestBuilderTest extends TestCase
{
    /**
     * Build a signed token request builder with mocked OpenSSL.
     */
    protected function makeBuilder(): SignedTokenRequestBuilder
    {
        $libxml = new LibxmlProxy;
        $xmlDomFactory = new XmlDomFactory($libxml);

        // Mock the OpenSslProxy used by XmlSigner to avoid real PKCS#12 parsing.
        $this->mock(OpenSslProxy::class, static function (MockInterface $mock): void {
            $mock->expects('readPkcs12String')
                ->between(1, 3)
                ->andReturn([
                    'pkey' => '-----BEGIN PRIVATE KEY-----fake-----END PRIVATE KEY-----', 'cert' => 'fake-cert-pem'
                ]);
            $mock->expects('privateKeyDetails')
                ->between(1, 3)
                ->andReturn(['rsa' => ['n' => 'modulus-base64', 'e' => 'AQAB']]);
            $mock->expects('sign')
                ->between(1, 3)
                ->andReturn('signature-b64');
        });

        return $this->app->make(SignedTokenRequestBuilder::class, [
            'xml' => $xmlDomFactory,
        ]);
    }

    public function test_build_creates_valid_xml_structure_for_seed(): void
    {
        $certificate = new DigitalCertificate('fake-pkcs12', 'secret');

        $builder = $this->makeBuilder();

        $signedXml = $builder->build('000000001042', $certificate);

        // Verify XML declaration is present on its own line.
        static::assertStringStartsWith("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n", $signedXml);

        // Verify required structure elements are present.
        static::assertStringContainsString('<getToken ID="GetToken">', $signedXml);
        static::assertStringContainsString('<item>', $signedXml);
        static::assertStringContainsString('<Semilla>000000001042</Semilla>', $signedXml);
        static::assertStringContainsString('</item>', $signedXml);
        static::assertStringContainsString('</getToken>', $signedXml);

        // Verify it contains an XMLDSig Signature element.
        static::assertStringContainsString('<Signature xmlns="http://www.w3.org/2000/09/xmldsig#">', $signedXml);

        // Verify it contains SignedInfo (required by XmlSigner).
        static::assertStringContainsString('<SignedInfo>', $signedXml);

        // Verify signature value from our mock is present.
        static::assertStringContainsString('signature-b64', $signedXml);
    }

    public function test_build_serializes_as_single_line_after_declaration(): void
    {
        $certificate = new DigitalCertificate('fake-pkcs12', 'secret');

        $builder = $this->makeBuilder();

        $signedXml = $builder->build('000000001042', $certificate);

        $lines = explode("\n", $signedXml);

        // Should have exactly 2 lines: XML declaration + single-line content.
        static::assertCount(2, $lines);

        // The second line should contain everything (no newlines).
        static::assertStringNotContainsString("\n", $lines[1]);
    }

    public function test_build_with_different_seed_values(): void
    {
        $certificate = new DigitalCertificate('fake-pkcs12', 'secret');

        $builder = $this->makeBuilder();

        foreach (['seed-abc', '1234567890', 'very-long-seed-value-for-testing'] as $seed) {
            $signedXml = $builder->build($seed, $certificate);

            static::assertStringContainsString(
                '<Semilla>'.$seed.'</Semilla>',
                $signedXml,
                "Seed '$seed' not found in signed XML"
            );
        }
    }
}
