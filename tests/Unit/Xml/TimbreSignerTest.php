<?php

namespace Tests\Unit\Xml;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Exception;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Support\XmlDomFactory;
use Laragear\Dte\Xml\TimbreSigner;
use RuntimeException;
use Tests\TestCase;
use Tests\Unit\Caf\Fixtures\CafFixture;
use function base64_decode;
use function mb_convert_encoding;
use function preg_replace;

class TimbreSignerTest extends TestCase
{
    protected TimbreSigner $signer;

    /**
     * Create the signer under test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->signer = $this->app->make(TimbreSigner::class);
    }

    /*
     |--------------------------------------------------------------------------
     | Happy Paths
     |--------------------------------------------------------------------------
     */

    public function test_creates_a_verifiable_sha1_with_rsa_timbre(): void
    {
        $fixture = CafFixture::create();
        $details = $this->document(
            '<DD testAttr="value" xmlns="http://www.sii.cl/SiiDte"><RE>'
            .$fixture->issuer
            .'</RE><TD>'
            .DteType::Invoice->value
            .'</TD><F>1</F></DD>',
        )->documentElement;

        $signature = $this->signer->sign($details, $fixture->privateKey);
        $key = $this->publicKey($fixture->modulus, $fixture->exponent);

        // Verify using the flatten recipe (SII instructivo A.2.4)
        $flat = $this->flatten($details);

        static::assertSame(
            1,
            openssl_verify(
                $flat,
                base64_decode($signature, true),
                $key,
                OPENSSL_ALGO_SHA1,
            ),
        );
    }

    public function test_the_official_sii_ted_signature_matches_its_flattened_details(): void
    {
        $document = $this->document($this->officialXml());
        $xpath = $this->app->make(XmlDomFactory::class)->xpath($document);
        $details = $this->element($xpath, '(//*[local-name()="DD"])[1]');
        $signature = $this->element($xpath, '(//*[local-name()="TED"]/*[local-name()="FRMT"])[1]');
        $modulus = $this->element($xpath, '(//*[local-name()="DD"]//*[local-name()="RSAPK"]/*[local-name()="M"])[1]');
        $exponent = $this->element($xpath, '(//*[local-name()="DD"]//*[local-name()="RSAPK"]/*[local-name()="E"])[1]');

        // Verify using the flatten recipe (SII instructivo A.2.4)
        $flat = $this->flatten($details);

        $key = $this->publicKey($modulus->textContent, $exponent->textContent);
        $result = openssl_verify($flat, base64_decode($signature->textContent, true), $key, OPENSSL_ALGO_SHA1);

        static::assertSame(1, $result);
    }

    /*
     |--------------------------------------------------------------------------
     | Sad Paths
     |--------------------------------------------------------------------------
     */

    public function test_rejects_elements_other_than_dd(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The TED signature must target a DD element.');

        $this->signer->sign($this->document('<TED/>')->documentElement, 'unused');
    }

    public function test_rejects_an_invalid_private_key(): void
    {
        $this->expectException(Exception::class);

        $this->signer->sign($this->document('<DD/>')->documentElement, 'invalid-private-key');
    }

    /**
     * Parse an XML test document.
     */
    protected function document(string $xml): DOMDocument
    {
        $document = $this->app->make(XmlDomFactory::class)->document();
        $document->preserveWhiteSpace = false;

        if (!$document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('Unable to load the XML test fixture.');
        }

        return $document;
    }

    /**
     * Return a required XML fixture element.
     */
    protected function element(DOMXPath $xpath, string $query): DOMElement
    {
        $element = $xpath->query($query)?->item(0);

        return $element instanceof DOMElement
            ? $element
            : throw new RuntimeException("Missing XML fixture element [$query].");
    }

    /**
     * Flatten a DD element for the flatten assertion.
     */
    protected function flatten(DOMElement $details): string
    {
        // This replicates what TimbreSigner::flatten() does internally.
        $xml = $details->ownerDocument->saveXML($details);
        $noNs = str_replace(' xmlns="http://www.sii.cl/SiiDte"', '', $xml);
        $iso = mb_convert_encoding($noNs, 'ISO-8859-1', 'UTF-8');

        return preg_replace('/>\s+</', '><', $iso);
    }

    /**
     * Create an RSA-SHA1 public verification key.
     */
    protected function publicKey(string $modulus, string $exponent): string
    {
        $n = base64_decode($modulus, true);
        $e = base64_decode($exponent, true);

        $enc = function ($type, $value) use (&$enc) {
            $len = strlen($value);
            if ($len < 128) {
                $lenBytes = chr($len);
            } else {
                $lenHex = dechex($len);
                if ((strlen($lenHex) % 2) !== 0) {
                    $lenHex = '0'.$lenHex;
                }
                $lenBytes = chr(0x80 | (strlen($lenHex) / 2)).hex2bin($lenHex);
            }

            return chr($type).$lenBytes.$value;
        };

        if (ord($n[0]) > 0x7F) {
            $n = "\x00".$n;
        }
        if (ord($e[0]) > 0x7F) {
            $e = "\x00".$e;
        }

        $rsaSeq = $enc(0x30, $enc(0x02, $n).$enc(0x02, $e));
        $algoId = hex2bin('300d06092a864886f70d0101010500');
        $bitStr = $enc(0x03, "\x00".$rsaSeq);
        $spkiSeq = $enc(0x30, $algoId.$bitStr);

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spkiSeq), 64,
                "\n")."-----END PUBLIC KEY-----\n";
    }

    /**
     * Read the official SII invoice example.
     */
    protected function officialXml(): string
    {
        return $this->app->make(Filesystem::class)->get(__DIR__.'/../../stubs/F60T33-ejemplo.xml');
    }
}
