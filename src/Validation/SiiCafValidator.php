<?php

namespace Laragear\Dte\Validation;

use Illuminate\Http\UploadedFile;
use Laragear\Dte\Caf\CafParser;
use Laragear\Rut\Exceptions\RutException;
use Laragear\Rut\Rut;
use Throwable;

class SiiCafValidator
{
    /**
     * Create a new Sii CAF Validator instance.
     */
    public function __construct(
        protected CafParser $cafParser,
    ) {
        //
    }

    /**
     * Validates the SII CAF XML, optionally checking the issuer RUT.
     */
    public function isValid(mixed $value, ?string $expectedRut = null): bool
    {
        $xml = $value;

        if ($value instanceof UploadedFile) {
            $xml = $value->get();
        }

        try {
            $parsed = $this->cafParser->parse($xml);
        } catch (Throwable) {
            return false;
        }

        if ($expectedRut !== null) {
            try {
                if (!Rut::parse($parsed['issuer_rut'])->isEqual($expectedRut)) {
                    return false;
                }
            } catch (RutException) {
                return false;
            }
        }

        return true;
    }
}
