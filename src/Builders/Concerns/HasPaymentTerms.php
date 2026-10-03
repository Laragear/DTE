<?php

namespace Laragear\Dte\Builders\Concerns;

use Laragear\Dte\Data\PaymentTermData;

trait HasPaymentTerms
{
    protected ?PaymentTermData $paymentTerm = null;

    /**
     * Add a payment term to the document.
     */
    public function addPaymentTerm(PaymentTermData $paymentTerm): static
    {
        $this->paymentTerm = $paymentTerm;

        return $this;
    }

    /**
     * Return payment terms data for serialization.
     *
     * @return array<string, mixed>|null
     */
    protected function paymentTermsData(): ?array
    {
        if ($this->paymentTerm === null) {
            return null;
        }

        return [
            'condition' => $this->paymentTerm->condition,
            'expiration_date' => $this->paymentTerm->expirationDate->format('Y-m-d'),
        ];
    }
}
