<?php

namespace Laragear\Dte\Validation;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\DateFactory;
use Laragear\Dte\Proxies\OpenSslProxy;
use SensitiveParameter;
use Throwable;

class SiiCertificateValidator
{
    /**
     * The MIME Types to check against the uploaded file when the file is an Uploaded file.
     *
     * @const string[]
     */
    public const array CERT_MIME_TYPES = [
        'com.rsa.pkcs-12',
        'application/x-pkcs12',
        'application/pkcs12',
        'application/octet-stream', // Fallback for browsers that don't get PKCS#12 files.
    ];

    /**
     * Create a new Sii Certificate Validator instance.
     */
    public function __construct(
        protected OpenSslProxy $openSsl,
        protected DateFactory $date,
    ) {
        //
    }

    /**
     * Validates the SII Certificate with a given password.
     */
    public function isValid(#[SensitiveParameter] ?string $password, mixed $value): bool
    {
        if ($password === null || $password === '') {
            return false;
        }

        $pkcs12 = $value;

        if ($value instanceof UploadedFile) {
            if (!in_array($value->getMimeType(), static::CERT_MIME_TYPES, true)) {
                return false;
            }

            $pkcs12 = $value->get();
        }

        try {
            $pem = $this->openSsl->readPkcs12String($pkcs12, $password);
        } catch (Throwable) {
            return false;
        }

        if (!isset($pem['cert'])) {
            return false;
        }

        $metadata = $this->openSsl->parseX509($pem['cert']);

        return $this->date->now()->isBetween(
            $this->date->createFromTimestamp($metadata['valid_from']),
            $this->date->createFromTimestamp($metadata['valid_to']),
        );
    }
}
