<?php

namespace Tests\Unit\Certificate;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Laragear\Dte\Certificate\CertificateResolver;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Dte\Contracts\Certifiable;
use Laragear\Dte\Contracts\CertificateResolverInterface;
use Laragear\Rut\Rut;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\DatabaseTestCase;
use UnexpectedValueException;

class CertificateResolverTest extends DatabaseTestCase
{
    public function test_resolves_a_certificate_using_callback()
    {
        $rut = Rut::parse('76.123.456-0');
        $expected = new DigitalCertificate('binary', 'secret');

        CertificateResolver::resolveUsing(function (Rut $r) use ($rut, $expected) {
            static::assertSame($rut->formatBasic(), $r->formatBasic());

            return $expected;
        });

        $resolver = $this->app->make(CertificateResolverInterface::class);

        static::assertSame($expected, $resolver->resolve($rut));
    }

    public function test_resolves_a_certifiable_object()
    {
        $rut = Rut::parse('76.123.456-0');
        $expected = new DigitalCertificate('binary', 'secret');

        $certifiable = Mockery::mock(Certifiable::class);
        $certifiable->expects('toDigitalCertificate')->andReturn($expected);

        CertificateResolver::resolveUsing(function () use ($certifiable) {
            return $certifiable;
        });

        $resolver = $this->app->make(CertificateResolverInterface::class);

        static::assertSame($expected, $resolver->resolve($rut));
    }

    public function test_resolves_using_default_configuration(): void
    {
        $this->mock(Repository::class, static function (MockInterface $mock): void {
            $mock->expects('get')->with('dte.certificate.disk')->andReturn('test-disk');
            $mock->expects('get')->with('dte.certificate.path')->andReturn('test-path');
            $mock->expects('get')->with('dte.certificate.password')->andReturn('test-password');
        });

        $this->mock(Factory::class, static function (MockInterface $mock): void {
            $storage = Mockery::mock(Filesystem::class);
            $storage->expects('path')->with('test-path')->andReturn('test-certificate');

            $mock->expects('disk')->andReturn($storage);
        });

        CertificateResolver::resolveUsingDefaults();

        $resolver = $this->app->make(CertificateResolverInterface::class);

        $certificate = $resolver->resolve(Rut::parse('76.123.456-0'));

        static::assertSame('test-certificate', $certificate->pkcs12);
        static::assertSame('test-password', $certificate->password);
    }

    public function test_returns_null_when_callback_returns_null()
    {
        $rut = Rut::parse('76.123.456-0');

        CertificateResolver::resolveUsing(function () {
            return null;
        });

        $resolver = $this->app->make(CertificateResolverInterface::class);

        static::assertNull($resolver->resolve($rut));
    }

    public function test_throws_when_callback_returns_invalid_type()
    {
        $rut = Rut::parse('76.123.456-0');

        CertificateResolver::resolveUsing(function () {
            return 'string';
        });

        $resolver = $this->app->make(CertificateResolverInterface::class);

        $this->expectException(UnexpectedValueException::class);
        $resolver->resolve($rut);
    }

    public function test_registers_default_certificate_resolver()
    {
        $this->mock(Repository::class, static function (MockInterface $mock): void {
            $mock->expects('get')->with('dte.certificate.disk')->andReturn('test-disk');
            $mock->expects('get')->with('dte.certificate.path')->andReturn('test-path');
            $mock->expects('get')->with('dte.certificate.password')->andReturn('test-password');
        });

        $this->mock(Factory::class, static function (MockInterface $mock): void {
            $storage = Mockery::mock(Filesystem::class);
            $storage->expects('path')->with('test-path')->andReturn('test-certificate');

            $mock->expects('disk')->andReturn($storage);
        });

        $resolver = $this->app->make(CertificateResolverInterface::class);

        $resolver->resolve(Rut::parse('76.123.456-0'));
    }

    public function test_throws_when_no_callback_defined()
    {
        $resolver = new CertificateResolver($this->app);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('No certificate resolver callback defined.');

        $resolver->resolve(Rut::parse('76.123.456-0'));
    }
}
