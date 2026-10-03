<?php

namespace Laragear\Dte\Testing;

use Illuminate\Foundation\Console\Kernel;
use Illuminate\Support\Facades\Storage;
use Laragear\Dte\Certificate\DigitalCertificate as Cert;
use Laragear\Dte\Contracts\CertificateResolverInterface;
use Laragear\Rut\Rut;
use Mockery\MockInterface;

trait InteractsWithDigitalCertificates
{
    /**
     * Mock the certificate resolver to return a valid fake certificate.
     */
    protected function withFakeCertificate(Rut|string|int|null $rut = null, string $dir = 'app/testing/dte/certs'): void
    {
        // When called without arguments, returns a fake certificate for any
        // RUT. When called with a specific RUT, returns a fake certificate only
        // for that RUT.
        $this->mock(CertificateResolverInterface::class, function (MockInterface $mock) use ($rut, $dir): void {
            $mock->expects('resolve')->andReturnUsing(function (Rut $resolvedRut) use ($rut, $dir): ?Cert {
                if ($rut === null || $rut->isEqual($resolvedRut)) {
                    return $this->createFakeCertificate($resolvedRut, $dir);
                }

                return null;
            });
        });
    }

    /**
     * Mock the certificate resolver to return null (no certificate available).
     */
    protected function expectsNoCertificate(): void
    {
        // Useful for testing graceful handling of missing certificates,
        // regardless of the RUT being resolved.
        $this->mock(CertificateResolverInterface::class, function (MockInterface $mock): void {
            $mock->expects('resolve')->andReturn(null);
        });
    }

    /**
     * Create a fake certificate for the given RUT.
     *
     * @codeCoverageIgnore The artisan command dependency (dte:make-fake-cert) requires
     *                     OpenSSL extensions and generates files on disk, making it
     *                     impractical to test in a unit test environment without
     *                     running the real artisan command. Just ignore this.
     */
    protected function createFakeCertificate(Rut $rut, string $dir): Cert
    {
        $disk = Storage::build([
            'driver' => 'local',
            'root' => $this->app->storagePath($dir),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ]);

        $path = "{$rut->formatBasic()}.p12";

        if ($disk->missing($path)) {
            $this->app->make(Kernel::class)->call('dte:make-fake-cert', [
                '--rut' => $rut,
                '--disk' => $disk,
                '--path' => $path,
            ]);
        }

        return new Cert($disk->get($path), 'secret');
    }
}
