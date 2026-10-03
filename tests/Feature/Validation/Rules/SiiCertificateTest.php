<?php

namespace Tests\Feature\Validation\Rules;

use Laragear\Dte\Proxies\OpenSslProxy;
use Laragear\Dte\Validation\Rules\SiiCertificate;
use Mockery\MockInterface;
use Tests\DatabaseTestCase;
use Tests\Unit\Certificate\Fixtures\CertificateFixture;

use function file_get_contents;

class SiiCertificateTest extends DatabaseTestCase
{
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

    public function test_passes_with_valid_certificate_and_string_password(): void
    {
        $rule = new SiiCertificate($this->fixture->password);

        $fail = null;
        $rule->validate('certificate', file_get_contents($this->fixture->path), function ($message) use (&$fail): void {
            $fail = $message;
        });

        static::assertNull($fail, 'The rule has failed validation.');
    }

    public function test_passes_with_valid_certificate_and_closure_password(): void
    {
        $rule = new SiiCertificate(fn () => $this->fixture->password);

        $fail = null;
        $rule->validate('certificate', file_get_contents($this->fixture->path), function ($message) use (&$fail): void {
            $fail = $message;
        });

        static::assertNull($fail, 'The rule has failed validation.');
    }

    /*
     |--------------------------------------------------------------------------
     | Sad paths
     |--------------------------------------------------------------------------
     */

    public function test_fails_with_wrong_password(): void
    {
        $rule = new SiiCertificate('wrong-password');

        $fail = null;
        $rule->validate('certificate', file_get_contents($this->fixture->path), function ($message) use (&$fail): void {
            $fail = $message;
        });

        static::assertNotNull($fail, 'The rule has not failed validation.');
    }

    public function test_fails_with_empty_password(): void
    {
        $rule = new SiiCertificate('');

        $fail = null;
        $rule->validate('certificate', file_get_contents($this->fixture->path), function ($message) use (&$fail): void {
            $fail = $message;
        });

        static::assertNotNull($fail, 'The rule has not failed validation.');
    }

    public function test_fails_with_null_password_from_closure(): void
    {
        $rule = new SiiCertificate(fn () => null);

        $fail = null;
        $rule->validate('certificate', file_get_contents($this->fixture->path), function ($message) use (&$fail): void {
            $fail = $message;
        });

        static::assertNotNull($fail, 'The rule has not failed validation.');
    }

    public function test_fails_when_certificate_is_expired(): void
    {
        $now = $this->freezeSecond();

        $this->mock(OpenSslProxy::class, static function (MockInterface $mock) use ($now): void {
            $mock->expects('readPkcs12String')->andReturn(['cert' => 'fake-cert', 'pkey' => 'fake-key']);
            $mock->expects('parseX509')->andReturn([
                'valid_from' => $now->getTimestamp() - 10000,
                'valid_to' => $now->getTimestamp() - 5000,
            ]);
        });

        $rule = new SiiCertificate('password');

        $fail = null;
        $rule->validate('certificate', 'binary-string-cert', function ($message) use (&$fail): void {
            $fail = $message;
        });

        static::assertNotNull($fail, 'The rule has not failed validation.');
    }

    public function test_fails_when_certificate_missing_from_pem(): void
    {
        $this->mock(OpenSslProxy::class, static function (MockInterface $mock): void {
            $mock->expects('readPkcs12String')->andReturn(['pkey' => 'fake-key']);
            $mock->expects('parseX509')->never();
        });

        $rule = new SiiCertificate('password');

        $fail = null;
        $rule->validate('certificate', 'binary-string-cert', function ($message) use (&$fail): void {
            $fail = $message;
        });

        static::assertNotNull($fail, 'The rule has not failed validation.');
    }
}
