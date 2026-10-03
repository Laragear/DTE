<?php

namespace Tests\Feature\Validation;

use Illuminate\Http\UploadedFile;
use Laragear\Dte\Proxies\OpenSslProxy;
use Laragear\MetaTesting\Validation\InteractsWithValidator;
use Mockery\MockInterface;
use Tests\DatabaseTestCase;
use Tests\Unit\Certificate\Fixtures\CertificateFixture;

use function file_get_contents;

class SiiCertificateRuleTest extends DatabaseTestCase
{
    use InteractsWithValidator;

    protected ?CertificateFixture $fixture = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = CertificateFixture::create();
    }

    protected function tearDown(): void
    {
        $this->fixture?->delete();
        $this->fixture = null;

        parent::tearDown();
    }

    /*
     |--------------------------------------------------------------------------
     | Happy paths
     |--------------------------------------------------------------------------
     */

    public function test_validates_a_valid_certificate()
    {
        $this->assertValidationPasses([
            'password' => $this->fixture->password,
            'cert' => UploadedFile::fake()->createWithContent('cert.p12', file_get_contents($this->fixture->path)),
        ], [
            'password' => 'required',
            'cert' => 'sii_certificate',
        ]);
    }

    public function test_validates_with_custom_password_field()
    {
        $this->assertValidationPasses([
            'cert_pass' => $this->fixture->password,
            'cert' => UploadedFile::fake()->createWithContent('cert.p12', file_get_contents($this->fixture->path)),
        ], [
            'cert_pass' => 'required',
            'cert' => 'sii_certificate:cert_pass',
        ]);
    }

    /*
     |--------------------------------------------------------------------------
     | Sad paths
     |--------------------------------------------------------------------------
     */

    public function test_fails_validation_with_missing_password()
    {
        $this->assertValidationFails([
            'cert' => 'binary-string-cert',
        ], [
            'cert' => 'sii_certificate',
        ]);
    }

    public function test_fails_validation_with_wrong_password_or_corrupt_file()
    {
        $this->assertValidationFails([
            'password' => 'wrong',
            'cert' => UploadedFile::fake()->createWithContent('cert.p12', file_get_contents($this->fixture->path)),
        ], [
            'password' => 'required',
            'cert' => 'sii_certificate',
        ]);
    }

    public function test_fails_validation_when_certificate_is_expired()
    {
        $now = $this->freezeSecond();

        // Mock OpenSslProxy to return an expired certificate metadata
        $this->mock(OpenSslProxy::class, static function (MockInterface $mock) use ($now): void {
            $mock->expects('readPkcs12String')->andReturn(['cert' => 'fake-cert', 'pkey' => 'fake-key']);
            $mock->expects('parseX509')->andReturn([
                'valid_from' => $now->getTimestamp() - 10000,
                'valid_to' => $now->getTimestamp() - 5000,
            ]);
        });

        $this->assertValidationFails([
            'password' => 'wrong',
            'cert' => 'binary-string-cert',
        ], [
            'password' => 'required',
            'cert' => 'sii_certificate',
        ]);
    }

    public function test_fails_validation_when_certificate_missing(): void
    {
        // Mock OpenSslProxy to return an expired certificate metadata
        $this->mock(OpenSslProxy::class, static function (MockInterface $mock): void {
            $mock->expects('readPkcs12String')->andReturn(['pkey' => 'fake-key']);
            $mock->expects('parseX509')->never();
        });

        $this->assertValidationFails([
            'password' => 'wrong',
            'cert' => 'binary-string-cert',
        ], [
            'password' => 'required',
            'cert' => 'sii_certificate',
        ]);
    }
}
