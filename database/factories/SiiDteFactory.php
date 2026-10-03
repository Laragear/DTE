<?php

namespace Laragear\Dte\Database\Factories;

use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\SiiDte;

/** @extends DteFactory<SiiDte> */
class SiiDteFactory extends DteFactory
{
    protected $model = SiiDte::class;

    /** @var array<int, int> */
    protected static array $folio = [];

    /**
     * Return the default emitted DTE attributes.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amountNet = $this->faker->numberBetween(1000, 1000000);
        $amountTaxes = (int) round($amountNet * 0.19);

        return [
            'issuer_rut' => $this->companyRut(),
            'receiver_rut' => $this->companyRut(),
            'document_type' => DteType::DEFAULT,
            'folio' => $this->faker->unique()->numberBetween(1, 999999999),
            'issued_on' => $this->faker->dateTimeBetween('-1 month'),
            'amount_net' => $amountNet,
            'amount_exempt' => 0,
            'amount_taxes' => $amountTaxes,
            'taxes' => null,
            'amount_total' => $amountNet + $amountTaxes,
            'status' => DteStatus::DEFAULT,
        ];
    }

    /**
     * Create a sent DTE.
     */
    public function sent(): static
    {
        return $this->state(['status' => DteStatus::Sent]);
    }

    /**
     * Create a rejected DTE.
     */
    public function rejected(): static
    {
        return $this->state(['status' => DteStatus::Rejected]);
    }

    /**
     * Create a accepted DTE.
     */
    public function accepted(): static
    {
        return $this->state(['status' => DteStatus::Accepted]);
    }

    /**
     * Create an Invoice.
     */
    public function invoice(): static
    {
        return $this->state(['document_type' => DteType::Invoice]);
    }

    /**
     * Create an Exempt Invoice.
     */
    public function exemptInvoice(): static
    {
        return $this->state(['document_type' => DteType::InvoiceExempt]);
    }

    /**
     * Create a Receipt.
     */
    public function receipt(): static
    {
        return $this->state(['document_type' => DteType::Receipt]);
    }

    /**
     * Create an Exempt Receipt.
     */
    public function exemptReceipt(): static
    {
        return $this->state(['document_type' => DteType::ExemptReceipt]);
    }

    /**
     * Create an Invoice Liquidation.
     */
    public function invoiceLiquidation(): static
    {
        return $this->state(['document_type' => DteType::InvoiceLiquidation]);
    }

    /**
     * Create a Purchase Invoice.
     */
    public function purchaseInvoice(): static
    {
        return $this->state(['document_type' => DteType::PurchaseInvoice]);
    }

    /**
     * Create a Dispatch Guide.
     */
    public function dispatchGuide(): static
    {
        return $this->state(['document_type' => DteType::DispatchGuide]);
    }

    /**
     * Create a Debit Note.
     */
    public function debitNote(): static
    {
        return $this->state(['document_type' => DteType::DebitNote]);
    }

    /**
     * Create a Credit Note.
     */
    public function creditNote(): static
    {
        return $this->state(['document_type' => DteType::CreditNote]);
    }

    /**
     * Creates a document using serialized counting.
     */
    public function serialized(int $start = 100): static
    {
        return $this->state(function (array $attributes) use ($start): array {
            /** @var $type int */
            $type = $attributes['document_type']->value;

            if (! isset(static::$folio[$type])) {
                static::$folio[$type] = $start;
            }

            return [
                'folio' => static::$folio[$type]++,
            ];
        });
    }
}
