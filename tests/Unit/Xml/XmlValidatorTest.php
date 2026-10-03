<?php

namespace Tests\Unit\Xml;

use DOMDocument;
use DOMNode;
use DOMXPath;
use Exception;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Dte\Proxies\LibxmlProxy;
use Laragear\Dte\Proxies\OpenSslProxy;
use Laragear\Dte\Support\XmlDomFactory;
use Laragear\Dte\Xml\XmlSigner;
use Laragear\Dte\Xml\XmlValidator;
use Mockery;
use RuntimeException;
use Tests\TestCase;
use Tests\Unit\Certificate\Fixtures\CertificateFixture;

class XmlValidatorTest extends TestCase
{
    protected function makeValidator(): XmlValidator
    {
        return $this->app->make(XmlValidator::class);
    }

    public function test_validate_returns_document_on_success(): void
    {
        $validator = Mockery::mock(XmlValidator::class, [
            $this->app,
            $this->app->make(OpenSslProxy::class),
            $this->app->make(XmlDomFactory::class),
            $this->app->make(LibxmlProxy::class),
        ]);
        $validator->makePartial()->shouldAllowMockingProtectedMethods();
        $validator->expects('validateSignature')->andReturn(true);

        $xml = '<?xml version="1.0"?><DTE xmlns="http://www.sii.cl/SiiDte"><Documento ID="F1T33"></Documento></DTE>';

        $result = $validator->validate($xml);

        static::assertInstanceOf(DOMDocument::class, $result);
    }

    public function test_verify_signature_returns_bool(): void
    {
        $validator = Mockery::mock(XmlValidator::class, [
            $this->app,
            $this->app->make(OpenSslProxy::class),
            $this->app->make(XmlDomFactory::class),
            $this->app->make(LibxmlProxy::class),
        ]);
        $validator->makePartial()->shouldAllowMockingProtectedMethods();
        $validator->expects('validateSignature')->andReturn(true);

        $xml = '<?xml version="1.0"?><DTE xmlns="http://www.sii.cl/SiiDte"><Documento ID="F1T33"></Documento></DTE>';

        $result = $validator->verifySignature($xml);
        static::assertTrue($result);
    }

    public function test_validate_signature_throws_exception_on_openssl_error(): void
    {
        $fixture = CertificateFixture::create();

        $cert = new DigitalCertificate(file_get_contents($fixture->path), $fixture->password);

        $document = $this->app->make(XmlDomFactory::class)->document();
        $document->loadXML(
            $this->app->make(XmlSigner::class)->signString('<DTE><Documento ID="F1"></Documento></DTE>', $cert, ['F1']),
        );

        $this->mock(OpenSslProxy::class)
            ->expects('verify')
            ->andThrow(new Exception('OpenSSL error'));

        $validator = $this->makeValidator();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid DTE XML: XMLDSig signature verification failed.');

        $validator->verifySignature($document->saveXML());
    }

    public function test_validate_signature_returns_true_on_success(): void
    {
        $validator = $this->makeValidator();

        $fixture = CertificateFixture::create();

        $cert = new DigitalCertificate(file_get_contents($fixture->path), $fixture->password);

        $document = $this->app->make(XmlDomFactory::class)->document();
        $document->loadXML('<DTE><Documento ID="F1"></Documento></DTE>');

        $signer = $this->app->make(XmlSigner::class);
        $target = $document->getElementsByTagName('Documento')->item(0);
        $signer->sign($target, $cert);

        static::assertTrue($validator->verifySignature($document->saveXML()));
    }

    public function test_extract_x509_cert_returns_pem_when_found(): void
    {
        $validator = $this->makeValidator();

        $document = $this->app->make(XmlDomFactory::class)->document();
        $document->loadXML(
            '<?xml version="1.0"?><DTE><Documento ID="F1T33"><Signature xmlns="http://www.w3.org/2000/09/xmldsig#"><KeyInfo><X509Data><X509Certificate>MIIBzTCCAW2gAwIBAgIJAJ0Y5sXJx8VhMA0GCSqGSIb3DQEBCwUAMEUxCzAJBgNVBAYTAkNMMQ8wDQYDVQQIDAZTYW50aWFnbzEPMA0GA1UEBwwGU2FudGlhZ28xDzANBgNVBAoMBkxhcmFnZWFyMRQwEgYDVQQDDAt0ZXN0LmV4YW1wbGUwHhcNMjQwMTAxMDAwMDAwWhcNMjUwMTAxMDAwMDAwWjBFMQswCQYDVQQGEwJDTDERMA8GA1UECAwIU2FudGlhZ28xDzANBgNVBAcMBkNhY2FsYTEPMA0GA1UECgwGTGFyYWdlYXIxFDASBgNVBAMMC3Rlc3QuZXhhbXBsZTCBmzAQBgcqhkjOPQIBBgUrgQQAIwOBhgAEAK3Z7z7X7z7X7z7X7z7X7z7X7z7X7z7X7z7X7z7X7z7X7z7X7z7X7z7X7z7X7z7X7z7-----END CERTIFICATE-----</X509Certificate></X509Data></KeyInfo></Signature></Documento></DTE>',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid DTE XML: XMLDSig reference is missing.');

        $validator->verifySignature($document->saveXML());
    }

    public function test_extract_x509_cert_fallback_to_local_name(): void
    {
        $validator = $this->makeValidator();

        $document = $this->app->make(XmlDomFactory::class)->document();
        $document->loadXML(
            '<?xml version="1.0"?><DTE><Documento ID="F1T33"><Signature xmlns="http://www.w3.org/2000/09/xmldsig#"><KeyInfo><X509Data><X509Certificate>testcert</X509Certificate></X509Data></KeyInfo></Signature></Documento></DTE>',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid DTE XML: XMLDSig reference is missing.');

        $validator->verifySignature($document->saveXML());
    }

    public function test_validate_throws_on_malformed_xml(): void
    {
        $validator = $this->makeValidator();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid DTE XML: the document is malformed or empty.');

        $validator->validate('not xml at all');
    }

    public function test_validate_signature_throws_when_signature_missing(): void
    {
        $validator = $this->makeValidator();

        $document = $this->app->make(XmlDomFactory::class)->document();
        $document->loadXML('<?xml version="1.0"?><DTE><Documento ID="F1T33"></Documento></DTE>');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid DTE XML: XMLDSig signature is missing.');

        $validator->verifySignature($document->saveXML());
    }

    public function test_validate_signature_throws_when_x509_cert_missing(): void
    {
        $validator = $this->makeValidator();

        $document = $this->app->make(XmlDomFactory::class)->document();
        $document->loadXML(
            '<?xml version="1.0"?><DTE><Documento ID="F1T33"><Signature xmlns="http://www.w3.org/2000/09/xmldsig#"><SignedInfo><Reference URI="#F1T33"></Reference></SignedInfo></Signature></Documento></DTE>',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid DTE XML: X509 certificate is missing from KeyInfo.');

        $validator->verifySignature($document->saveXML());
    }

    public function test_validate_signature_throws_when_digest_reference_fails(): void
    {
        $validator = $this->makeValidator();
        // Since we are not using mocking anymore, just pass broken digest
        $xml = '<?xml version="1.0"?><DTE><Documento ID="F1T33"></Documento><ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#"><ds:SignedInfo><ds:Reference URI="#F1T33"><ds:DigestValue>broken</ds:DigestValue></ds:Reference></ds:SignedInfo><ds:KeyInfo><ds:X509Data><ds:X509Certificate>MIIB</ds:X509Certificate></ds:X509Data></ds:KeyInfo></ds:Signature></DTE>';
        $document = $this->app->make(XmlDomFactory::class)->document();
        $document->loadXML($xml);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid DTE XML: XMLDSig digest reference does not match.');

        $validator->verifySignature($document->saveXML());
    }

    public function test_validate_signature_throws_when_key_missing(): void
    {
        $validator = $this->makeValidator();
        // Missing SignedInfo
        $xml =
            '<?xml version="1.0"?><DTE><Documento ID="F1T33"></Documento><ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#"><ds:SignedInfo><ds:Reference URI="#F1T33"><ds:DigestValue>'
            .base64_encode(sha1('<Documento ID="F1T33"></Documento>', true))
            .'</ds:DigestValue></ds:Reference></ds:SignedInfo></ds:Signature></DTE>';
        $document = $this->app->make(XmlDomFactory::class)->document();
        $document->loadXML($xml);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid DTE XML: X509 certificate is missing from KeyInfo.');

        $validator->verifySignature($document->saveXML());
    }

    public function test_validate_signature_throws_when_verification_fails(): void
    {
        $validator = $this->makeValidator();

        $document = $this->app->make(XmlDomFactory::class)->document();
        $document->loadXML('<?xml version="1.0"?><DTE><Documento ID="F1T33"></Documento><ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#"><ds:SignedInfo><ds:Reference URI="#F1T33"><ds:DigestValue>'
            .base64_encode(sha1('<Documento ID="F1T33"></Documento>', true))
            .'</ds:DigestValue></ds:Reference></ds:SignedInfo><ds:SignatureValue>broken</ds:SignatureValue><ds:KeyInfo><ds:X509Data><ds:X509Certificate>MIIB</ds:X509Certificate></ds:X509Data></ds:KeyInfo></ds:Signature></DTE>');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid DTE XML: XMLDSig signature verification failed.');

        $validator->verifySignature($document->saveXML());
    }

    public function test_extract_x509_cert_returns_null_when_not_found(): void
    {
        $validator = $this->makeValidator();

        $document = $this->app->make(XmlDomFactory::class)->document();
        $document->loadXML('<?xml version="1.0"?><DTE><Documento ID="F1T33"></Documento></DTE>');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid DTE XML: X509 certificate is missing from KeyInfo.');

        // Create fake signature to bypass first check
        $document->loadXML(
            '<?xml version="1.0"?><DTE><Documento ID="F1T33"></Documento><Signature xmlns="http://www.w3.org/2000/09/xmldsig#"></Signature></DTE>',
        );

        $validator->verifySignature($document->saveXML());
    }

    public function test_throws_if_digest_target_does_not_match(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid DTE XML: XMLDSig digest reference does not match.');

        $xml = '<?xml version="1.0"?><DTE><Document ID="no_match"></Document><ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#"><ds:SignedInfo><ds:Reference URI="#Documento"><ds:DigestValue>AAA=</ds:DigestValue></ds:Reference></ds:SignedInfo><ds:SignatureValue>BBB==</ds:SignatureValue><ds:KeyInfo><ds:X509Data><ds:X509Certificate>cert</ds:X509Certificate></ds:X509Data></ds:KeyInfo></ds:Signature></DTE>';

        $validator = $this->makeValidator();
        $validator->verifySignature($xml);
    }

    public function test_throws_if_digest_value_missing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid DTE XML: XMLDSig digest value is missing.');

        $xml = '<?xml version="1.0"?><DTE><Document ID="Documento"></Document><ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#"><ds:SignedInfo><ds:Reference URI="#Documento"></ds:Reference></ds:SignedInfo><ds:SignatureValue>BBB==</ds:SignatureValue><ds:KeyInfo><ds:X509Data><ds:X509Certificate>cert</ds:X509Certificate></ds:X509Data></ds:KeyInfo></ds:Signature></DTE>';

        $validator = $this->makeValidator();
        $validator->verifySignature($xml);
    }

    public function test_throws_if_actual_digest_differs(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid DTE XML: XMLDSig digest reference does not match.');

        $xml = '<?xml version="1.0"?><DTE><Document ID="Documento"></Document><ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#"><ds:SignedInfo><ds:Reference URI="#Documento"><ds:DigestValue>WrongDigest=</ds:DigestValue></ds:Reference></ds:SignedInfo><ds:SignatureValue>BBB==</ds:SignatureValue><ds:KeyInfo><ds:X509Data><ds:X509Certificate>cert</ds:X509Certificate></ds:X509Data></ds:KeyInfo></ds:Signature></DTE>';

        $validator = $this->makeValidator();
        $validator->verifySignature($xml);
    }

    public function test_throws_if_signed_info_missing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid DTE XML: XMLDSig SignedInfo is missing.');

        $docXml = '<Document ID="Documento"></Document>';
        $targetDigest = base64_encode(sha1($docXml, true));
        $xml =
            '<?xml version="1.0"?><DTE>'
            .$docXml
            .'<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#"><ds:Reference URI="#Documento"><ds:DigestValue>'
            .$targetDigest
            .'</ds:DigestValue></ds:Reference><ds:SignatureValue>BBB==</ds:SignatureValue><ds:KeyInfo><ds:X509Data><ds:X509Certificate>cert</ds:X509Certificate></ds:X509Data></ds:KeyInfo></ds:Signature></DTE>';

        $validator = $this->makeValidator();
        $validator->verifySignature($xml);
    }

    public function test_throws_if_signature_value_missing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid DTE XML: XMLDSig SignatureValue is missing.');

        $docXml = '<Document ID="Documento"></Document>';
        $targetDigest = base64_encode(sha1($docXml, true));
        $xml =
            '<?xml version="1.0"?><DTE>'
            .$docXml
            .'<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#"><ds:SignedInfo><ds:Reference URI="#Documento"><ds:DigestValue>'
            .$targetDigest
            .'</ds:DigestValue></ds:Reference></ds:SignedInfo><ds:KeyInfo><ds:X509Data><ds:X509Certificate>cert</ds:X509Certificate></ds:X509Data></ds:KeyInfo></ds:Signature></DTE>';

        $validator = $this->makeValidator();
        $validator->verifySignature($xml);
    }

    public function test_throws_when_signature_is_invalid(): void
    {
        $xml = '<?xml version="1.0"?><DTE><Documento ID="Doc"></Documento><ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#"><ds:SignedInfo><ds:Reference URI="#Doc"><ds:DigestValue>AAA=</ds:DigestValue></ds:Reference></ds:SignedInfo><ds:SignatureValue>QUJD</ds:SignatureValue><ds:KeyInfo><ds:X509Data><ds:X509Certificate>cert</ds:X509Certificate></ds:X509Data></ds:KeyInfo></ds:Signature></DTE>';

        $ssl = Mockery::mock(OpenSslProxy::class)->makePartial();
        $ssl->expects('verify')->andReturn(0); // 0 means invalid signature

        $mock = new class($this->app, $ssl, $this->app->make(XmlDomFactory::class), $this->app->make(LibxmlProxy::class)) extends XmlValidator {
            protected function verifyDigest(DOMXPath $xpath, DOMNode $signature): void
            {
                //
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid DTE XML: XMLDSig signature verification failed.');
        $mock->verifySignature($xml);
    }

    public function test_throws_when_signature_value_is_not_valid_base64(): void
    {
        $xml = '<?xml version="1.0"?><DTE><Documento ID="Doc"></Documento><ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#"><ds:SignedInfo><ds:Reference URI="#Doc"><ds:DigestValue>AAA=</ds:DigestValue></ds:Reference></ds:SignedInfo><ds:SignatureValue>BBB==</ds:SignatureValue><ds:KeyInfo><ds:X509Data><ds:X509Certificate>cert</ds:X509Certificate></ds:X509Data></ds:KeyInfo></ds:Signature></DTE>';

        $ssl = Mockery::mock(OpenSslProxy::class)->makePartial();
        $ssl->expects('verify')->never();

        $mock = new class($this->app, $ssl, $this->app->make(XmlDomFactory::class), $this->app->make(LibxmlProxy::class)) extends XmlValidator {
            protected function verifyDigest(DOMXPath $xpath, DOMNode $signature): void
            {
                //
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid DTE XML: XMLDSig SignatureValue is not valid base64.');
        $mock->verifySignature($xml);
    }

    public function test_verify_signature_throws_for_nonexistent_target_id(): void
    {
        $validator = Mockery::mock(XmlValidator::class, [
            $this->app,
            $this->app->make(OpenSslProxy::class),
            $this->app->make(XmlDomFactory::class),
            $this->app->make(LibxmlProxy::class),
        ])->makePartial();

        // A valid signed XML but with a target ID that doesn't exist
        $xml = '<?xml version="1.0"?><DTE xmlns="http://www.sii.cl/SiiDte">'
            .'<Documento ID="F1T33"></Documento>'
            .'<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#">'
            .'<ds:SignedInfo><ds:Reference URI="#F1T33"/></ds:SignedInfo>'
            .'</ds:Signature></DTE>';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid DTE XML: XMLDSig signature for target [NonExistentID] is missing.');
        $validator->verifySignature($xml, 'NonExistentID');
    }
}
