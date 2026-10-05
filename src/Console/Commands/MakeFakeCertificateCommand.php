<?php

namespace Laragear\Dte\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Laragear\Dte\Configuration\ConfigurationManager as Config;
use Laragear\Dte\Console\Commands\Concerns\HasDefaultRut;
use Laragear\Dte\Proxies\OpenSslProxy as OpenSsl;
use Laragear\Rut\Rut;
use OpenSSLAsymmetricKey;
use OpenSSLCertificate;
use OpenSSLCertificateSigningRequest;

class MakeFakeCertificateCommand extends Command
{
    use HasDefaultRut;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dte:make-fake-cert
                            {--rut= : The RUT for the certificate (defaults to dummy)}
                            {--disk= : The storage disk to use to save the .p12 certificate}
                            {--path= : The path to save the .p12 certificate}
                            {--password=secret : The password for the .p12 certificate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a self-signed dummy PKCS#12 (.p12) digital certificate for local/testing environments.';

    /**
     * Execute the console command.
     */
    public function handle(Repository $config, Factory $storage, OpenSsl $openSsl, Config $configManager): int
    {
        $rut = $this->rut($configManager);
        $options = $this->resolveOptions($config);
        $dn = $this->buildDistinguishedName($rut, $configManager, $config);

        $this->info("Generating dummy certificate for {$rut->format()}...");

        $key = $this->generatePrivateKey($openSsl);

        if ($key === false) {
            $this->error('Failed to generate private key.');

            return self::FAILURE;
        }

        $csr = $this->generateCsr($openSsl, $dn, $key);

        if ($csr === false) {
            $this->error('Failed to generate CSR.');

            return self::FAILURE;
        }

        $cert = $this->signCertificate($openSsl, $csr, $key);

        if ($cert === false) {
            $this->error('Failed to sign certificate.');

            return self::FAILURE;
        }

        $p12 = $this->exportPkcs12($openSsl, $cert, $key, $options['password']);

        if ($p12 === false) {
            $this->error('Failed to export PKCS#12.');

            return self::FAILURE;
        }

        $this->saveCertificate($p12, $storage, $options['disk'], $options['path']);

        $this->info('Successfully created fake certificate at disk '.$this->diskLabel($options['disk']).": {$options['path']}");
        $this->info("Password: {$options['password']}");

        return self::SUCCESS;
    }

    /**
     * Resolve the disk, path, and password from CLI options or configuration.
     *
     * @return array{disk: string, path: string, password: string}
     */
    protected function resolveOptions(Repository $config): array
    {
        return [
            'disk' => $this->option('disk') ?: $config->get('dte.certificate.disk') ?: 'local',
            'path' => $this->option('path') ?: $config->get('dte.certificate.path') ?: 'dte/certificate.p12',
            'password' => $this->option('password') ?: $config->get('dte.certificate.password') ?: 'secret',
        ];
    }

    /**
     * Build the distinguished name (DN) for the self-signed certificate.
     *
     * @return array<string, string>
     */
    protected function buildDistinguishedName(Rut $rut, Config $configManager, Repository $config): array
    {
        return [
            'countryName' => 'CL',
            'stateOrProvinceName' => 'RM',
            'localityName' => 'Santiago',
            'organizationName' => $configManager->hasIssuerResolver()
                ? $configManager->getIssuer($rut)->name
                : $config->get('app.name'),
            'commonName' => 'LARAVEL DEVELOPMENT FAKE CERTIFICATE',
            'serialNumber' => $rut->formatBasic(),
            'emailAddress' => $configManager->hasIssuerResolver()
                ? ($configManager->getIssuer($rut)->email ?: $config->get('mail.from.address'))
                : $config->get('mail.from.address'),
        ];
    }

    /**
     * Generate a new RSA private key.
     */
    protected function generatePrivateKey(OpenSsl $openSsl): OpenSSLAsymmetricKey|false
    {
        return $openSsl->pkeyNew([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
    }

    /**
     * Generate a CSR from the distinguished name and private key.
     */
    protected function generateCsr(
        OpenSsl $openSsl,
        array $dn,
        OpenSSLAsymmetricKey $key
    ): OpenSSLCertificateSigningRequest|false {
        return $openSsl->csrNew($dn, $key);
    }

    /**
     * Self-sign a CSR into a certificate.
     */
    protected function signCertificate(
        OpenSsl $openSsl,
        OpenSSLCertificateSigningRequest $csr,
        OpenSSLAsymmetricKey $key
    ): OpenSSLCertificate|false {
        return $openSsl->csrSign($csr, null, $key, 365 * 3);
    }

    /**
     * Export the certificate and private key into a PKCS#12 envelope.
     */
    protected function exportPkcs12(
        OpenSsl $openSsl,
        OpenSSLCertificate $certificate,
        OpenSSLAsymmetricKey $privateKey,
        string $password
    ): string|false {
        $p12 = '';

        return $openSsl->pkcs12Export($certificate, $p12, $privateKey, $password) ? $p12 : false;
    }

    /**
     * Write the PKCS#12 data to the configured disk and path.
     */
    protected function saveCertificate(string $p12, Factory $storage, Filesystem|string $disk, string $path): void
    {
        // @codeCoverageIgnoreStart
        if ($disk instanceof Filesystem) {
            $disk->put($path, $p12);
        } else {
            $storage->disk($disk)->put($path, $p12);
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * Resolve a printable label for the disk, which may be a name or a resolved filesystem.
     */
    protected function diskLabel(Filesystem|string $disk): string
    {
        // @codeCoverageIgnoreStart
        return $disk instanceof Filesystem ? $disk->path('') : $disk;
        // @codeCoverageIgnoreEnd
    }
}
