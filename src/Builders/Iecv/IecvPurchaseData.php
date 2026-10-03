<?php

namespace Laragear\Dte\Builders\Iecv;

use Laragear\Dte\Enums\DteType;
use Laragear\Rut\Rut;

readonly class IecvPurchaseData
{
    /**
     * Create a new IECV Purchase Data instance.
     */
    public function __construct(
        public DteType|int $documentType,
        public int $folio,
        public string $issuedOn,
        public Rut|string $issuerRut,
        public int $amountNet = 0,
        public int $amountExempt = 0,
        public bool $ivaCommonUse = false,
        public bool $noCost = false,
        public bool $ivaRetainedTotal = false,
        public DteType|int|null $referenceType = null,
        public ?int $referenceFolio = null,
    ) {
        // Represents a single document from the SII Test Set for the Purchases
        // Book (IECV).
    }

    /**
     * Create a new instance fluently.
     */
    public static function make(
        DteType|int $documentType,
        int $folio,
        string $issuedOn,
        Rut|string $issuerRut,
        int $amountNet = 0,
        int $amountExempt = 0,
        bool $ivaCommonUse = false,
        bool $noCost = false,
        bool $ivaRetainedTotal = false,
        DteType|int|null $referenceType = null,
        ?int $referenceFolio = null,
    ): static {
        return new static(
            $documentType,
            $folio,
            $issuedOn,
            $issuerRut,
            $amountNet,
            $amountExempt,
            $ivaCommonUse,
            $noCost,
            $ivaRetainedTotal,
            $referenceType,
            $referenceFolio,
        );
    }
}
