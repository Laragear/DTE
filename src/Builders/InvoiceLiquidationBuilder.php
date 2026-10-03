<?php

namespace Laragear\Dte\Builders;

use Laragear\Dte\Builders\Concerns\HasItems;
use Laragear\Dte\Builders\Concerns\HasReferences;
use Laragear\Dte\Enums\DteType;

class InvoiceLiquidationBuilder extends DocumentBuilder
{
    use HasItems;
    use HasReferences;

    /**
     * Return electronic invoice liquidation type 43.
     */
    public function documentType(): DteType
    {
        return DteType::InvoiceLiquidation;
    }
}
