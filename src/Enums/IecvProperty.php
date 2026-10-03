<?php

namespace Laragear\Dte\Enums;

use Laragear\Dte\Builders\Iecv\IecvPropertyData;

enum IecvProperty: string
{
    case CommonIvaFactor = 'FctProp';

    // Potential Future Integrations:
    //
    // case TotalAnnulled = 'TotAnulado';                   // Number of annulled documents (skipped in Detalle)
    // case TotalIvaOutOfPeriod = 'TotIVAFueraPlazo';       // IVA from documents outside the legal period
    // case TotalOwnIva = 'TotIVAPropio';                   // Own IVA (often for liquidations)
    // case TotalThirdPartyIva = 'TotIVATerceros';          // Third-party IVA
    // case TotalLaw18211 = 'TotLey18211';                  // Totals for special laws (e.g., Zofri)
    // case TotalCreditConstructionCompany = 'TotCredEC';   // Crédito Especial Empresas Constructoras
    // case TotalDepositContainers = 'TotDepEnvase';        // Container/packaging deposits
    // case TotalIvaNonRetained = 'TotIVANoRetenido';       // Total non-retained IVA
    // case CommonIvaCredit = 'TotCredIVAUsoComun';         // Manual override for Common IVA Credit (due to rounding)

    /**
     * Set the value for this property.
     */
    public function of(mixed $value): IecvPropertyData
    {
        return new IecvPropertyData($this, $value);
    }
}
