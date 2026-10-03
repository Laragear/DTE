<?php

namespace Laragear\Dte\Builders;

use Laragear\Dte\Builders\Concerns\HasItems;
use Laragear\Dte\Builders\Concerns\HasReferences;
use Laragear\Dte\Enums\DteType;

class PurchaseInvoiceBuilder extends DocumentBuilder
{
    use HasItems;
    use HasReferences;

    /**
     * Return electronic purchase invoice type 46.
     */
    public function documentType(): DteType
    {
        return DteType::PurchaseInvoice;
    }

    /**
     * Validate document-specific input.
     */
    protected function validateSpecific(): void
    {
        $this->validateB2bReceiver();
    }
}
