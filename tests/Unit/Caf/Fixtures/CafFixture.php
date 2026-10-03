<?php

namespace Tests\Unit\Caf\Fixtures;

use Laragear\Dte\Enums\DteType;
use Laragear\Rut\Facades\Generator;
use OpenSSLAsymmetricKey;
use RuntimeException;
use function base64_encode;
use function is_array;
use function is_bool;
use function openssl_pkey_export;
use function openssl_pkey_get_details;
use function openssl_pkey_new;
use function str_replace;

class CafFixture
{
    /**
     * Create a CAF Fixture instance.
     */
    protected function __construct(
        public readonly string $issuer,
        public readonly string $privateKey,
        public readonly string $modulus,
        public readonly string $exponent,
    ) {
        //
    }

    /**
     * Create a valid CAF fixture.
     */
    public static function create(): static
    {
        $key = static::key();
        $details = openssl_pkey_get_details($key);

        if (!is_array($details) || !isset($details['rsa'])) {
            throw new RuntimeException('Unable to read the test CAF RSA public key.');
        }

        return new static(
            Generator::asCompanies()->makeOne()->formatRaw(),
            static::export($key),
            base64_encode($details['rsa']['n']),
            base64_encode($details['rsa']['e']),
        );
    }

    /**
     * Return a valid downloaded CAF authorization.
     */
    public function xml(int $from = 1, int $to = 100): string
    {
        $documentType = DteType::Invoice->value;

        // Do not edit this, correct indentation is required.
        return <<<XML
            <?xml version="1.0" encoding="ISO-8859-1"?>
            <AUTORIZACION>
                <CAF version="1.0">
                    <DA>
                        <RE>{$this->issuer}</RE>
                        <RS>EMPRESA EJEMPLO SPA</RS>
                        <TD>{$documentType}</TD>
                        <RNG><D>{$from}</D><H>{$to}</H></RNG>
                        <FA>2026-08-01</FA>
                        <RSAPK><M>{$this->modulus}</M><E>{$this->exponent}</E></RSAPK>
                        <IDK>300</IDK>
                    </DA>
                    <FRMA algo="SHA1withRSA">c2lpLXNpZ25hdHVyZQ==</FRMA>
                </CAF>
                <RSASK><![CDATA[{$this->privateKey}]]></RSASK>
            </AUTORIZACION>
            XML;
    }

    /**
     * Return CAF XML without the SII signature.
     */
    public function withoutSignature(): string
    {
        return (string) str_replace(
            '<FRMA algo="SHA1withRSA">c2lpLXNpZ25hdHVyZQ==</FRMA>',
            '',
            $this->xml(),
        );
    }

    /**
     * Return CAF XML with an unsupported document type.
     */
    public function withUnsupportedDocumentType(): string
    {
        return (string) str_replace('<TD>'.DteType::Invoice->value.'</TD>', '<TD>999</TD>', $this->xml());
    }

    /**
     * Return CAF XML with invalid private-key contents.
     */
    public function withInvalidPrivateKey(): string
    {
        return (string) str_replace($this->privateKey, 'invalid-private-key', $this->xml());
    }

    /**
     * Return CAF XML with an invalid public-key modulus.
     */
    public function withInvalidModulus(): string
    {
        return (string) str_replace($this->modulus, '**invalid-base64**', $this->xml());
    }

    /**
     * Return CAF XML with a different valid private key.
     */
    public function withMismatchedPrivateKey(): string
    {
        return (string) str_replace($this->privateKey, static::export(static::key()), $this->xml());
    }

    /**
     * Create an RSA private key.
     */
    protected static function key(): OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);

        if (is_bool($key)) {
            throw new RuntimeException('Unable to create the test CAF RSA key.');
        }

        return $key;
    }

    /**
     * Export an RSA private key as PEM.
     */
    protected static function export(OpenSSLAsymmetricKey $key): string
    {
        if (!openssl_pkey_export($key, $privateKey)) {
            throw new RuntimeException('Unable to export the test CAF RSA key.');
        }

        return $privateKey;
    }
}
