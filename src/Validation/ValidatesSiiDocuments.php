<?php

namespace Laragear\Dte\Validation;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;
use Laragear\Rut\Exceptions\RutException;
use Laragear\Rut\Rut;
use SensitiveParameter;

class ValidatesSiiDocuments
{
    /**
     * Validate the SII Certificate with its password.
     *
     * @param  UploadedFile  $value
     * @param  array<int, string>  $parameters
     *
     * @throws FileNotFoundException
     */
    public static function validateSiiCertificate(
        string $attribute,
        mixed $value,
        array $parameters,
        Validator $validator,
    ): bool {
        return static::validateCertificate(Arr::get($validator->getData(), $parameters[0] ?? 'password'), $value);
    }

    /**
     * Validates the SII Certificate with a given password.
     *
     * @param  UploadedFile|string  $value
     */
    public static function validateCertificate(#[SensitiveParameter] ?string $password, mixed $value): bool
    {
        return app(SiiCertificateValidator::class)->isValid($password, $value);
    }

    /**
     * Validates the SII CAF XML.
     */
    public static function validateSiiCaf(
        string $attribute,
        mixed $value,
        array $parameters,
        Validator $validator,
    ): bool {
        if ($value instanceof UploadedFile) {
            if (! $validator->validateMimetypes($attribute, $value, ['text/xml', 'application/xml'])) {
                return false;
            }
        }

        $expectedRut = isset($parameters[0])
            ? Arr::get($validator->getData(), $parameters[0], $parameters[0])
            : null;

        return app(SiiCafValidator::class)->isValid($value, $expectedRut);
    }

    /**
     * Validates the Chilean RUT format and verification digit.
     */
    public static function validateSiiRut(string $attribute, mixed $value): bool
    {
        foreach (Arr::wrap($value) as $rut) {
            try {
                if (Rut::parse($rut)->isInvalid()) {
                    return false;
                }
            } catch (RutException) {
                return false;
            }
        }

        return true;
    }
}
