<?php

namespace Tests\Unit\Testing;

use Laragear\Dte\Contracts\CertificateResolverInterface;
use Laragear\Dte\Testing\InteractsWithDigitalCertificates;
use Laragear\Rut\Rut;
use Tests\TestCase;

class InteractsWithDigitalCertificatesTest extends TestCase
{
    use InteractsWithDigitalCertificates;

    public function test_with_fake_certificate_mock_resolver_for_any_rut(): void
    {
        $this->withFakeCertificate();

        $resolver = $this->app->make(CertificateResolverInterface::class);
        $cert = $resolver->resolve(Rut::parse('11111111-1'));

        static::assertNotNull($cert);
        static::assertNotEmpty($cert->pkcs12);
    }

    public function test_with_fake_certificate_mock_resolver_for_specific_rut(): void
    {
        $this->withFakeCertificate(Rut::parse('11111111-1'));

        $resolver = $this->app->make(CertificateResolverInterface::class);

        $cert = $resolver->resolve(Rut::parse('11111111-1'));
        static::assertNotNull($cert);
    }

    public function test_expects_no_certificate_returns_null(): void
    {
        $this->expectsNoCertificate();

        $resolver = $this->app->make(CertificateResolverInterface::class);
        $cert = $resolver->resolve(Rut::parse('11111111-1'));

        static::assertNull($cert);
    }

    public function test_create_fake_certificate_generates_p12_file(): void
    {
        $cert = $this->createFakeCertificate(Rut::parse('11111111-1'), 'app/testing/dte/certs');

        static::assertNotNull($cert);
        static::assertNotEmpty($cert->pkcs12);
    }

    public function test_create_fake_certificate_reuses_existing_file(): void
    {
        $cert1 = $this->createFakeCertificate(Rut::parse('33333333-3'), 'app/testing/dte/certs');
        $cert2 = $this->createFakeCertificate(Rut::parse('33333333-3'), 'app/testing/dte/certs');

        static::assertSame($cert1->pkcs12, $cert2->pkcs12);
    }

    public function test_with_fake_certificate_returns_null_for_non_matching_rut(): void
    {
        $this->withFakeCertificate(Rut::parse('11111111-1'));

        $resolver = $this->app->make(CertificateResolverInterface::class);
        $cert = $resolver->resolve(Rut::parse('99999999-9'));

        static::assertNull($cert);
    }
}
