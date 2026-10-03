<?php

namespace Tests\Unit\Certificate\Fixtures;

use OpenSSLAsymmetricKey;
use OpenSSLCertificate;
use RuntimeException;
use function file_put_contents;
use function is_bool;
use function openssl_csr_new;
use function openssl_csr_sign;
use function openssl_pkcs12_export;
use function openssl_pkey_new;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

class CertificateFixture
{
    /**
     * Create a Certificate Fixture instance.
     */
    protected function __construct(
        public readonly string $path,
        public readonly string $password,
    ) {
        //
    }

    /**
     * Create a valid temporary PKCS#12 certificate.
     */
    public static function create(string $password = 'secret'): static
    {
        $key = static::key();
        $certificate = static::certificate($key);

        if (!openssl_pkcs12_export($certificate, $contents, $key, $password)) {
            throw new RuntimeException('Unable to export the test PKCS#12 certificate.');
        }

        return new static(static::write($contents), $password);
    }

    /**
     * Create a corrupt temporary certificate file.
     */
    public static function corrupt(string $password = 'secret'): static
    {
        return new static(static::write('corrupt PKCS#12'), $password);
    }

    /**
     * Delete the temporary certificate file.
     */
    public function delete(): void
    {
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }

    /**
     * Create a private key for the certificate.
     */
    protected static function key(): OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);

        if (is_bool($key)) {
            throw new RuntimeException('Unable to create the test private key.');
        }

        return $key;
    }

    /**
     * Create a self-signed X.509 certificate.
     */
    protected static function certificate(OpenSSLAsymmetricKey $key): OpenSSLCertificate
    {
        $request = openssl_csr_new(['commonName' => 'DTE Test'], $key);

        if (is_bool($request)) {
            throw new RuntimeException('Unable to create the test certificate request.');
        }

        $certificate = openssl_csr_sign($request, null, $key, 30);

        if (is_bool($certificate)) {
            throw new RuntimeException('Unable to sign the test certificate.');
        }

        return $certificate;
    }

    /**
     * Write temporary certificate contents.
     */
    protected static function write(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'dte-certificate-');

        if ($path === false || file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Unable to write the test certificate.');
        }

        return $path;
    }
}
