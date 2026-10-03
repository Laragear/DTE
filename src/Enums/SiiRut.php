<?php

namespace Laragear\Dte\Enums;

use Laragear\Rut\Rut;

enum SiiRut: string
{
    public const self DEFAULT = self::Dummy;

    /** Default dummy company RUT for testing and local development. */
    case Dummy = '76.123.456-0';

    /** Official Servicio de Impuestos Internos (SII) RUT. */
    case Sii = '60.803.000-K';

    /** Official generic / final consumer RUT for anonymous receipts (boletas). */
    case Consumer = '66.666.666-6';

    /** Official foreign / export recipient RUT. */
    case Foreign = '55.555.555-5';

    /** Official temporary / unregistered recipient RUT. */
    case Unregistered = '44.444.444-4';

    /**
     * Return the RUT instance.
     */
    public function toRut(): Rut
    {
        return Rut::parse($this->value);
    }

    /**
     * Return the basic formatted RUT (e.g. 76123456-0).
     */
    public function formatBasic(): string
    {
        return $this->toRut()->formatBasic();
    }

    /**
     * Return the raw unformatted RUT (e.g. 761234560).
     */
    public function formatRaw(): string
    {
        return $this->toRut()->formatRaw();
    }

    /**
     * Return the formatted RUT with dots and hyphen (e.g. 76.123.456-0).
     */
    public function format(): string
    {
        return $this->toRut()->format();
    }
}
