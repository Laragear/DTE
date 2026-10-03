<?php

namespace Laragear\Dte\Builders\Concerns;

use BackedEnum;
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Data\ReferenceData;
use LogicException;
use function app;
use function array_map;
use function array_merge;
use function count;
use function is_string;

/**
 * Serializes builder state into the JSON payload stored on the SiiDte model.
 */
trait PayloadSerializer
{
    abstract public function documentType();

    abstract public function issuer();

    abstract public function receiver();

    /**
     * @return list<Item>
     */
    abstract public function items(): array;

    /**
     * @return list<ReferenceData>
     */
    abstract public function references();

    abstract public function globalModifiers(): array;

    abstract protected function calculatedTotals(): array;

    /**
     * Return the JSON-safe raw builder input.
     *
     * @return array<string, mixed>
     */
    public function payloadData(): array
    {
        return array_merge([
            'document_type' => $this->documentType()->value,
            'issued_on' => $this->issueDate->format('Y-m-d'),
            'issuer' => $this->issuerData(),
            'receiver' => $this->receiverData(),
            'items' => array_map($this->itemData(...), $this->items()),
            'references' => array_map($this->referenceData(...), $this->references()),
            'global_modifiers' => $this->globalModifiers(),
            'taxes' => $this->aggregateTaxes(),
            'totals' => $this->calculatedTotals(),
            'ind_mnt_neto' => $this->indMntNeto ?? null,
        ], $this->additionalData());
    }

    /**
     * Aggregate item-level taxes into a keyed array.
     *
     * @return array<int, int> [ taxCode => totalAmount ]
     */
    protected function aggregateTaxes(): array
    {
        $taxes = [];

        foreach ($this->items() as $item) {
            foreach ($item->taxes as $taxCode => $amount) {
                $taxes[$taxCode] = ($taxes[$taxCode] ?? 0) + $amount;
            }
        }

        return $taxes;
    }

    /**
     * Return subclass-specific raw input.
     *
     * @return array<string, mixed>
     */
    protected function additionalData(): array
    {
        return [];
    }

    /**
     * Normalize the economic activity (Acteco) to an array of up to 4 tags.
     */
    protected function normalizeActeco(string|array $acteco): array
    {
        $tags = is_string($acteco) ? [$acteco] : array_values($acteco);

        if (count($tags) > 4) {
            throw new LogicException('The maximum number of Acteco tags allowed is 4.');
        }

        return $tags;
    }

    /**
     * Serialize issuer data for JSON storage.
     *
     * @return array<string, mixed>
     */
    protected function issuerData(): array
    {
        $issuer = $this->issuer();
        $global = $this->configurationManager->getIssuer();

        return [
            'rut' => $issuer->rut->formatRaw(),
            'name' => $issuer->name,
            'activity' => $issuer->activity,
            'activity_code' => $this->normalizeActeco($issuer->activityCode),
            'address' => $issuer->address,
            'commune' => $issuer->commune,
            'city' => $issuer->city,
            'telephone' => $issuer->telephone ?? $global?->telephone,
            'email' => $issuer->email ?? $global?->email,
            'branch' => $issuer->branch ?? $global?->branch,
            'resolution_date' => $issuer->resolutionDate ?? $global?->resolutionDate,
            'resolution_number' => $issuer->resolutionNumber ?? $global?->resolutionNumber,
        ];
    }

    /**
     * Serialize receiver data for JSON storage.
     *
     * @return array{rut: string, legal_name: string, activity: ?string, email: ?string, address: ?string, commune: ?string, city: ?string}|null
     */
    protected function receiverData(): ?array
    {
        if ($this->receiver) {
            return [
                'rut' => $this->receiver->rut->formatRaw(),
                'name' => $this->receiver->name,
                'activity' => $this->receiver->activity,
                'email' => $this->receiver->email,
                'address' => $this->receiver->address,
                'commune' => $this->receiver->commune,
                'city' => $this->receiver->city,
            ];
        }

        return null;
    }

    /**
     * Serialize item data for JSON storage.
     *
     * @return array<string, mixed>
     */
    protected function itemData(Item $item): array
    {
        return [
            'name' => $item->name,
            'unit_price' => $item->unitPrice,
            'quantity' => $item->quantity,
            'description' => $item->description,
            'unit' => $item->unit,
            'code' => $item->code,
            'code_type' => $item->codeType,
            'discount_percentage' => $item->discountPercentage,
            'discount_amount' => $item->discountAmount,
            'exempt' => $item->exempt,
            'taxes' => $item->taxes,
        ];
    }

    /**
     * Serialize reference data for JSON storage.
     *
     * @return array<string, mixed>
     */
    protected function referenceData(ReferenceData $reference): array
    {
        return [
            'document_type' => $reference->documentType instanceof BackedEnum
                ? $reference->documentType->value
                : $reference->documentType,
            'folio' => $reference->folio,
            'date' => $reference->date?->format('Y-m-d'),
            'reason' => $reference->reason,
            'reference_code' => $reference->referenceCode,
        ];
    }
}
