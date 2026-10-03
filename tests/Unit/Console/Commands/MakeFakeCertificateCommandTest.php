<?php

namespace Tests\Unit\Console\Commands;

use Illuminate\Support\Facades\Storage;
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Proxies\OpenSslProxy;
use Mockery\MockInterface;
use Tests\TestCase;

class MakeFakeCertificateCommandTest extends TestCase
{
    public function test_generates_fake_certificate_using_issuer(): void
    {
        $this->mock(ConfigurationManager::class, static function (MockInterface $mock): void {
            $issuer = IssuerData::make(
                '76.111.222-3',
                'Test Company',
                'Software',
                ['620100'],
                'Test Address 123',
                'Santiago',
                '2025-01-01',
                76000,
                'Santiago',
                '+56212345678',
                'test@example.com',
                'Casa Matriz',
            );

            $mock->expects('hasIssuerResolver')->times(3)->andReturnTrue();
            $mock->expects('getIssuer')->times(3)->andReturn($issuer);
        });

        $this
            ->artisan('dte:make-fake-cert')
            ->expectsOutput('Generating dummy certificate for 76.111.222-3...')
            ->assertSuccessful();
    }

    public function test_generates_fake_certificate(): void
    {
        Storage::fake('local');

        $this
            ->artisan('dte:make-fake-cert', [
                '--rut' => '76.123.456-7',
                '--disk' => 'local',
                '--path' => 'certificate.p12',
                '--password' => 'test-pass',
            ])
            ->expectsOutput('Generating dummy certificate for 76.123.456-7...')
            ->expectsOutput('Successfully created fake certificate at disk local: certificate.p12')
            ->expectsOutput('Password: test-pass')
            ->assertSuccessful();

        Storage::disk('local')->assertExists('certificate.p12');

        // Verify the certificate is valid
        $p12 = Storage::disk('local')->get('certificate.p12');
        $read = openssl_pkcs12_read($p12, $certs, 'test-pass');

        static::assertTrue($read);
        static::assertArrayHasKey('cert', $certs);
        static::assertArrayHasKey('pkey', $certs);

        $certInfo = openssl_x509_parse($certs['cert']);
        static::assertSame($this->app->make('config')->get('app.name'), $certInfo['subject']['O']);
        static::assertSame($this->app->make('config')->get('mail.from.address'), $certInfo['subject']['emailAddress']);
        static::assertSame('76123456-7', $certInfo['subject']['serialNumber']);
    }

    protected function mockOpenSsl(): MockInterface
    {
        return $this->mock(OpenSslProxy::class, function (MockInterface $mock) {
            $key = openssl_pkey_new();
            $csr = openssl_csr_new(['commonName' => 'test'], $key);
            $cert = openssl_csr_sign($csr, null, $key, 1);

            $mock->expects('pkeyNew')->zeroOrMoreTimes()->andReturn($key)->byDefault();
            $mock->expects('csrNew')->zeroOrMoreTimes()->andReturn($csr)->byDefault();
            $mock->expects('csrSign')->zeroOrMoreTimes()->andReturn($cert)->byDefault();
            $mock
                ->expects('pkcs12Export')
                ->zeroOrMoreTimes()
                ->andReturnUsing(function ($c, &$out, $k, $p) {
                    $out = 'fake_p12';

                    return true;
                })
                ->byDefault();
        });
    }

    public function test_fails_when_csr_new_fails(): void
    {
        $this->mockOpenSsl()->expects('csrNew')->andReturn(false);

        $this
            ->artisan('dte:make-fake-cert')
            ->expectsOutput('Failed to generate CSR.')
            ->assertFailed();
    }

    public function test_fails_when_csr_sign_fails(): void
    {
        $this->mockOpenSsl()->expects('csrSign')->andReturn(false);

        $this
            ->artisan('dte:make-fake-cert')
            ->expectsOutput('Failed to sign certificate.')
            ->assertFailed();
    }

    public function test_fails_when_pkcs12_export_fails(): void
    {
        $this->mockOpenSsl()->expects('pkcs12Export')->andReturn(false);

        $this
            ->artisan('dte:make-fake-cert')
            ->expectsOutput('Failed to export PKCS#12.')
            ->assertFailed();
    }

    public function test_fails_when_pkey_new_fails(): void
    {
        $this->mockOpenSsl()->expects('pkeyNew')->andReturn(false);

        $this
            ->artisan('dte:make-fake-cert')
            ->expectsOutput('Failed to generate private key.')
            ->assertFailed();
    }
}
