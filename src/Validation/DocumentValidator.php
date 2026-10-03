<?php

namespace Laragear\Dte\Validation;

use Laragear\Dte\Data\DocumentTotalsData;
use Laragear\Dte\Data\GlobalModifierData;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Data\PaymentTermData;
use Laragear\Dte\Data\ReceiverData;
use Laragear\Dte\Data\ReferenceData;
use Laragear\Dte\Data\TransportData;

use function array_merge;

/**
 * Validate a DTE payload against the XSD-derived section rules.
 */
final class DocumentValidator
{
    /**
     * Sections that only carry rules when present in the payload.
     *
     * @var array<string, class-string>
     */
    public const array OPTIONAL_SECTIONS = [
        'payment' => PaymentTermData::class,
        'transport' => TransportData::class,
    ];

    /**
     * Validate the document payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function validate(array $payload): void
    {
        validator($payload, $this->rules($payload))->validate();
    }

    /**
     * Return the rules for the given payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function rules(array $payload): array
    {
        $rules = array_merge([
            'document_type' => DteRules::DOCUMENT['document_type'],
            'issued_on' => DteRules::DOCUMENT['issued_on'],
            'ind_traslado' => DteRules::DOCUMENT['ind_traslado'],
            'tipo_despacho' => DteRules::DOCUMENT['tipo_despacho'],
            'ind_mnt_neto' => DteRules::DOCUMENT['ind_mnt_neto'],
            'issuer' => 'required|array',
        ], $this->prefix('issuer.', IssuerData::rules()));

        $rules = array_merge($rules, $this->prefix('receiver.', ReceiverData::rules()));
        $rules = array_merge($rules, $this->prefix('items.*.', Item::rules()));
        $rules = array_merge($rules, $this->prefix('references.*.', ReferenceData::rules()));
        $rules = array_merge($rules, $this->prefix('global_modifiers.*.', GlobalModifierData::rules()));
        $rules = array_merge($rules, $this->prefix('totals.', DocumentTotalsData::rules()));

        foreach (self::OPTIONAL_SECTIONS as $section => $dataClass) {
            if (isset($payload[$section])) {
                $rules = array_merge($rules, $this->prefix($section.'.', $dataClass::rules()));
            }
        }

        return $rules;
    }

    /**
     * Prefix every section rule with its payload path.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function prefix(string $path, array $rules): array
    {
        $prefixed = [];

        foreach ($rules as $key => $rule) {
            $prefixed[$path.$key] = $rule;
        }

        return $prefixed;
    }
}
