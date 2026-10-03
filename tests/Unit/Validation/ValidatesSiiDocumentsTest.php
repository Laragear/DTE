<?php

namespace Tests\Unit\Validation;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;
use Laragear\Dte\Validation\ValidatesSiiDocuments;
use Mockery;
use Tests\TestCase;
use Tests\Unit\Caf\Fixtures\CafFixture;

class ValidatesSiiDocumentsTest extends TestCase
{
    public function test_validate_certificate_returns_false_when_password_is_null(): void
    {
        static::assertFalse(ValidatesSiiDocuments::validateCertificate(null, 'binary'));
    }

    public function test_validate_certificate_returns_false_when_password_is_empty(): void
    {
        static::assertFalse(ValidatesSiiDocuments::validateCertificate('', 'binary'));
    }

    public function test_validate_certificate_returns_false_when_uploaded_file_mime_type_is_invalid(): void
    {
        // Line 61: UploadedFile with MIME type NOT in CERT_MIME_TYPES → return false
        $mock = Mockery::mock(UploadedFile::class);
        $mock->shouldReceive('getMimeType')->once()->andReturn('text/plain');

        static::assertFalse(ValidatesSiiDocuments::validateCertificate('password', $mock));
    }

    public function test_validate_certificate_returns_false_when_openssl_throws(): void
    {
        static::assertFalse(ValidatesSiiDocuments::validateCertificate('password', 'invalid binary'));
    }

    public function test_validate_sii_caf_returns_false_when_caf_parse_throws(): void
    {
        $validator = Mockery::mock(Validator::class);
        // getData is not called when parsing fails

        static::assertFalse(
            ValidatesSiiDocuments::validateSiiCaf('caf', 'not xml at all', [], $validator),
        );
    }

    public function test_validate_sii_caf_returns_false_when_rut_parse_throws(): void
    {
        // Lines 122-123: Rut::parse('not-a-valid-rut') throws → return false
        $fixture = CafFixture::create();
        $validCaf = $fixture->xml(1, 100);

        $validator = Mockery::mock(Validator::class);
        $validator->shouldReceive('getData')->once()->andReturn([]);

        static::assertFalse(
            ValidatesSiiDocuments::validateSiiCaf('caf', $validCaf, ['not-a-valid-rut'], $validator),
        );
    }

    public function test_validate_sii_caf_returns_false_when_issuer_rut_does_not_match(): void
    {
        $fixture = CafFixture::create();
        $validCaf = $fixture->xml(1, 100);

        // issuer_rut is the fixture's RUT, pass a different one
        $validator = Mockery::mock(Validator::class);
        $validator->shouldReceive('getData')->once()->andReturn(['rut' => '99999999-9']);

        static::assertFalse(
            ValidatesSiiDocuments::validateSiiCaf('caf', $validCaf, ['rut'], $validator),
        );
    }

    public function test_validate_sii_caf_returns_false_when_uploaded_file_has_invalid_mime_type(): void
    {
        $mock = Mockery::mock(UploadedFile::class);

        $validator = Mockery::mock(Validator::class);
        $validator->shouldReceive('validateMimetypes')->once()->andReturn(false);

        static::assertFalse(
            ValidatesSiiDocuments::validateSiiCaf('caf', $mock, [], $validator),
        );
    }

    public function test_validate_sii_rut_accepts_valid_ruts(): void
    {
        static::assertTrue(ValidatesSiiDocuments::validateSiiRut('rut', '76.123.456-0'));
        static::assertTrue(ValidatesSiiDocuments::validateSiiRut('rut', '761234560'));
        static::assertTrue(ValidatesSiiDocuments::validateSiiRut('rut', '60.803.000-K'));
    }

    public function test_validate_sii_rut_rejects_an_invalid_verification_digit(): void
    {
        static::assertFalse(ValidatesSiiDocuments::validateSiiRut('rut', '76.123.456-7'));
        static::assertFalse(ValidatesSiiDocuments::validateSiiRut('rut', '76.987.654-3'));
    }

    public function test_validate_sii_rut_rejects_malformed_ruts(): void
    {
        static::assertFalse(ValidatesSiiDocuments::validateSiiRut('rut', 'not-a-valid-rut'));
        static::assertFalse(ValidatesSiiDocuments::validateSiiRut('rut', ''));
    }

    public function test_validate_sii_rut_rejects_any_invalid_rut_in_a_list(): void
    {
        static::assertTrue(
            ValidatesSiiDocuments::validateSiiRut('rut', ['76.123.456-0', '76.987.654-5']),
        );

        static::assertFalse(
            ValidatesSiiDocuments::validateSiiRut('rut', ['76.123.456-0', '76.987.654-3']),
        );
    }

    public function test_validate_sii_caf_returns_true_for_valid_caf_without_rut_check(): void
    {
        $fixture = CafFixture::create();
        $validCaf = $fixture->xml(1, 100);

        $validator = Mockery::mock(Validator::class);

        static::assertTrue(
            ValidatesSiiDocuments::validateSiiCaf('caf', $validCaf, [], $validator),
        );
    }

    public function test_validate_sii_caf_returns_true_when_issuer_rut_matches(): void
    {
        $fixture = CafFixture::create();
        $validCaf = $fixture->xml(1, 100);

        $validator = Mockery::mock(Validator::class);
        $validator->shouldReceive('getData')->once()->andReturn(['rut' => $fixture->issuer]);

        static::assertTrue(
            ValidatesSiiDocuments::validateSiiCaf('caf', $validCaf, ['rut'], $validator),
        );
    }
}
