<?php

namespace Tests\Unit\Caf;

use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\DateFactory;
use InvalidArgumentException;
use Laragear\Dte\Caf\CafParser;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Proxies\OpenSslProxy;
use Laragear\Dte\Support\XmlDomFactory;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Unit\Caf\Fixtures\CafFixture;
use function base64_decode;
use function rtrim;

class CafParserTest extends TestCase
{
    protected CafParser $parser;

    protected MockInterface|OpenSslProxy $openSsl;

    /**
     * Create the parser under test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->openSsl = $this->mock(OpenSslProxy::class);
        $this->parser = $this->app->make(CafParser::class);
    }

    public function test_extracts_caf_authorization_data_and_rsa_keys(): void
    {
        $fixture = CafFixture::create();
        $xml = $fixture->xml(10, 20);

        $this->openSsl
            ->expects('privateKeyDetails')
            ->twice()
            ->with(rtrim($fixture->privateKey))
            ->andReturn([
                'rsa' => [
                    'n' => base64_decode($fixture->modulus, true),
                    'e' => base64_decode($fixture->exponent, true),
                ],
            ]);

        $data = $this->parser->parse($xml);

        static::assertSame($fixture->issuer, $data['issuer_rut']);
        static::assertSame(DteType::Invoice, $data['document_type']);
        static::assertSame(10, $data['folio_from']);
        static::assertSame(20, $data['folio_to']);
        static::assertSame(10, $data['folio_current']);
        static::assertEquals(Carbon::createFromDate(2026, 8, 1, 'America/Santiago')->startOfDay(),
            $data['authorized_on']);
        static::assertSame($fixture->modulus, $data['public_key_modulus']);
        static::assertSame($fixture->exponent, $data['public_key_exponent']);
        static::assertSame(rtrim($fixture->privateKey), $data['private_key']);
        static::assertSame('c2lpLXNpZ25hdHVyZQ==', $data['signature']);
        static::assertSame($xml, $data['xml']);
    }

    public function test_parses_caf_when_root_is_caf(): void
    {
        $fixture = CafFixture::create();
        $xml = str_replace(['<AUTORIZACION>', '</AUTORIZACION>'], ['<CAF>', '</CAF>'], $fixture->xml());

        $this->openSsl
            ->expects('privateKeyDetails')
            ->twice()
            ->andReturn([
                'rsa' => [
                    'n' => base64_decode($fixture->modulus, true),
                    'e' => base64_decode($fixture->exponent, true),
                ],
            ]);

        $data = $this->parser->parse($xml);

        static::assertSame(1, $data['folio_from']);
    }

    public function test_rejects_xml_without_a_caf_node(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The XML document does not contain a CAF node.');

        $this->openSsl->expects('privateKeyDetails')->never();

        $this->parser->parse('<AUTORIZACION/>');
    }

    public function test_rejects_a_missing_sii_signature(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The CAF element [.//FRMA] is missing.');

        $fixture = CafFixture::create();

        $this->openSsl
            ->expects('privateKeyDetails')
            ->never()
            ->andReturn([
                'rsa' => [
                    'n' => base64_decode($fixture->modulus, true),
                    'e' => base64_decode($fixture->exponent, true),
                ],
            ]);

        $this->parser->parse($fixture->withoutSignature());
    }

    public function test_rejects_an_invalid_private_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The CAF RSA private key is invalid.');

        $fixture = CafFixture::create();

        $this->openSsl
            ->expects('privateKeyDetails')
            ->with(Mockery::on(function ($arg) {
                return str_contains($arg, 'invalid-private-key');
            }))
            ->andReturnNull();

        $this->parser->parse($fixture->withInvalidPrivateKey());
    }

    public function test_rejects_an_invalid_public_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The CAF RSA public-key modulus is not valid Base64.');

        $this->openSsl->expects('privateKeyDetails')->never();

        $this->parser->parse(CafFixture::create()->withInvalidModulus());
    }

    public function test_rejects_a_private_key_that_does_not_match_the_public_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The CAF RSA private key does not match its public key.');

        $fixture = CafFixture::create();
        $mismatchedXml = $fixture->withMismatchedPrivateKey();

        // The withMismatchedPrivateKey method replaces the private key with a newly generated one.
        // We just need to capture that call and return a mismatched modulus.
        $this->openSsl
            ->expects('privateKeyDetails')
            ->twice()
            ->andReturn([
                'rsa' => [
                    'n' => 'mismatched',
                    'e' => 'mismatched',
                ],
            ]);

        $this->parser->parse($mismatchedXml);
    }

    public function test_rejects_an_unsupported_document_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The CAF document type is not supported.');

        $fixture = CafFixture::create();

        $this->openSsl
            ->expects('privateKeyDetails')
            ->never()
            ->andReturn([
                'rsa' => [
                    'n' => base64_decode($fixture->modulus, true),
                    'e' => base64_decode($fixture->exponent, true),
                ],
            ]);

        $this->parser->parse($fixture->withUnsupportedDocumentType());
    }

    public function test_rejects_a_reversed_folio_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The CAF folio range is invalid.');

        $this->openSsl->expects('privateKeyDetails')->never();

        $this->parser->parse(CafFixture::create()->xml(20, 10));
    }

    public function test_rejects_non_integer_elements(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The CAF element [.//DA/RNG/D] must be a positive integer.');

        $this->openSsl->expects('privateKeyDetails')->never();

        $xml = str_replace('<D>1</D>', '<D>abc</D>', CafFixture::create()->xml());
        $this->parser->parse($xml);
    }

    public function test_rejects_invalid_date_format(): void
    {
        $this->openSsl->expects('privateKeyDetails')->never();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The CAF element [.//DA/FA] must use YYYY-MM-DD format.');

        $xml = str_replace('<FA>2026-08-01</FA>', '<FA>01-08-2026</FA>', CafFixture::create()->xml());
        $this->parser->parse($xml);
    }

    public function test_rejects_invalid_date_values(): void
    {
        $this->openSsl->expects('privateKeyDetails')->never();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The CAF element [.//DA/FA] must use YYYY-MM-DD format.');

        // 2026-02-30 is parsed by PHP as 2026-03-02, which does not match the original string.
        $xml = str_replace('<FA>2026-08-01</FA>', '<FA>2026-02-30</FA>', CafFixture::create()->xml());
        $this->parser->parse($xml);
    }

    public function test_rejects_private_key_that_becomes_invalid_during_validation(): void
    {
        $fixture = CafFixture::create();

        $this->openSsl
            ->expects('privateKeyDetails')
            ->twice()
            ->andReturn(
                [
                    'rsa' => [
                        'n' => base64_decode($fixture->modulus, true),
                        'e' => base64_decode($fixture->exponent, true),
                    ],
                ],
                null,
            );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The CAF RSA private key is invalid.');

        $this->parser->parse($fixture->xml());
    }

    public function test_rejects_malformed_xml(): void
    {
        $this->expectException(Exception::class);

        $this->openSsl->expects('privateKeyDetails')->never();

        $this->parser->parse('<AUTORIZACION>');
    }

    public function test_rejects_date_when_create_from_format_returns_null(): void
    {
        $this->openSsl->expects('privateKeyDetails')->never();

        $mockDateFactory = $this->mock(DateFactory::class);
        $mockDateFactory->expects('createFromFormat')->andReturn(null);

        $parser = new CafParser(
            $this->app->make(XmlDomFactory::class),
            $this->openSsl,
            $mockDateFactory,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The CAF element [.//DA/FA] must use YYYY-MM-DD format.');

        $xml = str_replace('<FA>2026-08-01</FA>', '<FA>2026-08-01</FA>', CafFixture::create()->xml());
        $parser->parse($xml);
    }
}
