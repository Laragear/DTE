<?php

namespace Laragear\Dte\Dev;

use RuntimeException;

use function array_map;
use function array_merge;
use function file_get_contents;
use function hash;
use function implode;

/**
 * Generate the DteRules class from the SII XSD files.
 *
 * Parses the XSD facets, maps them to Laravel rule strings
 * and applies the corrections the generic mapping cannot
 * express. Emits plain-PHP with string rules only, so the
 * result stays serializable, diffable and reviewable.
 *
 * @internal Library development tooling only.
 */
class RulesGenerator
{
    /**
     * The XSD files describing the DTE document format.
     *
     * @var list<string>
     */
    public const array SOURCES = [
        'resources/xsd/DTE_v10.xsd',
        'resources/xsd/SiiTypes_v10.xsd',
    ];

    /**
     * Rules the generic facet mapping cannot express.
     *
     * These adapt the XSD rules to the package shapes: raw
     * RUTs without the dash, boolean flags, optional zeros
     * that the XML omits, and envelope-only fields.
     *
     * @var array<string, array<string, string>>
     */
    public const array CORRECTIONS = [
        'DOCUMENT' => [
            'document_type' => 'required|integer|in:30,32,33,34,39,41,43,46,52,56,61',
            'ind_mnt_neto' => 'nullable|integer|in:0,1,2',
        ],
        'ISSUER' => [
            'rut' => 'required|sii_rut',
            'email' => 'nullable|email|max:80',
            'address' => 'required|string|max:70',
            'commune' => 'required|string|max:20',
            'resolution_date' => 'required|date|date_format:Y-m-d|after_or_equal:2000-01-01|before_or_equal:2050-12-31',
            'resolution_number' => 'required|integer|min:0|max_digits:6',
        ],
        'RECEIVER' => [
            'rut' => 'required|sii_rut',
            'email' => 'nullable|email|max:80',
        ],
        'ITEM' => [
            'code' => 'nullable|string|max:35',
            'code_type' => 'nullable|string|max:10',
            // A zero quantity marks a tax-only line, so the
            // XML omits it when the line amount is zero.
            'quantity' => 'nullable|numeric|min:0|max:999999999999.999999|regex:/^\d+(\.\d{1,6})?$/',
            'unit_price' => 'nullable|numeric|min:0|max:999999999999.999999|regex:/^\d+(\.\d{1,6})?$/',
            'discount_percentage' => 'nullable|numeric|min:0|max:999.99|regex:/^\d+(\.\d{1,2})?$/',
            'discount_amount' => 'nullable|numeric|min:0',
            'exempt' => 'required|boolean',
            'taxes' => 'nullable|array|max:2',
            'taxes.*' => 'required|integer|min:0|max_digits:18',
        ],
        'PAYMENT_TERM' => [
            'condition' => 'required|integer|in:1,2,3',
        ],
        'REFERENCE' => [
            'document_type' => 'required',
            'date' => 'nullable|date|date_format:Y-m-d|after_or_equal:2000-01-01|before_or_equal:2050-12-31',
        ],
        'GLOBAL_MODIFIER' => [
            'target' => 'nullable|integer|in:0,1,2',
        ],
        'TRANSPORT' => [
            'carrier_rut' => 'nullable|sii_rut',
            'driver_rut' => 'nullable|sii_rut',
            'driver_name' => 'nullable|string|max:30',
            'departure_at' => 'nullable|date|date_format:Y-m-d H:i:s|after_or_equal:2003-04-01 00:00:00|before_or_equal:2050-12-31 23:59:59',
            'arrival_at' => 'nullable|date|date_format:Y-m-d H:i:s|after_or_equal:2003-04-01 00:00:00|before_or_equal:2050-12-31 23:59:59',
        ],
        'TOTALS' => [
            'taxes' => 'nullable|array|max:20',
            'taxes.*' => 'integer|min:0|max_digits:18',
        ],
        'HEADER_ID_DOC' => [
            'document_type' => 'required|integer|in:30,32,33,34,39,41,43,46,52,56,61',
            'ind_mnt_neto' => 'nullable|integer|in:0,1,2',
            'tax_exempt' => 'nullable|boolean',
            'exempt_amount_override' => 'nullable|integer|min:0|max_digits:18',
            'payments' => 'nullable|array|max:30',
        ],
        'HEADER_OTHER_CURRENCY' => [
            'withheld_taxes' => 'nullable|array|max:20',
        ],
        'DETAIL_ITEMS' => [
            'items' => 'present|array|max:60',
            'items.*.code' => 'nullable|string|max:35',
            'items.*.code_type' => 'nullable|string|max:10',
            'items.*.quantity' => 'nullable|numeric|min:0|max:999999999999.999999|regex:/^\d+(\.\d{1,6})?$/',
            'items.*.unit_price' => 'nullable|numeric|min:0|max:999999999999.999999|regex:/^\d+(\.\d{1,6})?$/',
            'items.*.discount_percentage' => 'nullable|numeric|min:0|max:999.99|regex:/^\d+(\.\d{1,2})?$/',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.exempt' => 'required|boolean',
            'items.*.taxes' => 'nullable|array|max:2',
            'items.*.taxes.*' => 'required|integer|min:0|max_digits:18',
        ],
        'SUBTOTALS' => [
            'items' => 'present|array|max:20',
            'items.*.detail_lines' => 'nullable|array|max:60',
            'items.*.detail_lines.*' => 'integer|min:1',
        ],
        'GLOBAL_MODIFIERS' => [
            'items' => 'present|array|max:20',
            'items.*.target' => 'nullable|integer|in:0,1,2',
        ],
        'REFERENCES' => [
            'items' => 'present|array|max:40',
            'items.*.document_type' => 'required',
            'items.*.date' => 'nullable|date|date_format:Y-m-d|after_or_equal:2000-01-01|before_or_equal:2050-12-31',
        ],
        'COMMISSIONS' => [
            'items' => 'present|array|max:20',
        ],
    ];

    /**
     * The hand-maintained XSD element to builder key map.
     *
     * @var array<string, array<string, string|null>>
     */
    protected array $fieldMap;

    /**
     * Create a RulesGenerator instance.
     */
    public function __construct(
        protected XsdParser $parser,
        protected RuleMapper $mapper,
        protected ClassEmitter $emitter,
    ) {
        $this->fieldMap = require __DIR__.'/field-map.php';
    }

    /**
     * Generate the DteRules class source.
     */
    public function generate(string $root): string
    {
        $files = $this->sourceFiles($root);
        $elements = $this->parser->parse($files)['elements'];

        $missing = [];

        $sections = $this->mapSections($this->fieldMap, $elements, $missing);

        $this->guardAgainstUnmappedElements($missing);

        $sources = implode(' + ', static::SOURCES);

        return $this->emitter->emit(
            $this->applyCorrections($sections),
            $this->sourceHash($files),
            $sources,
        );
    }

    /**
     * Resolve the XSD source files against the given root.
     *
     * @return list<string>
     */
    protected function sourceFiles(string $root): array
    {
        return array_map(
            static fn (string $source): string => $root.'/'.$source,
            static::SOURCES,
        );
    }

    /**
     * Map every field map entry to its validation rule.
     *
     * @param  array<string, array<string, string|null>>  $map
     * @param  array<string, array<string, mixed>>  $elements
     * @param  list<string>  $missing  Collected unmapped elements.
     * @return array<string, array<string, string>>
     */
    protected function mapSections(array $map, array $elements, array &$missing): array
    {
        $sections = [];

        foreach ($map as $section => $fields) {
            foreach ($fields as $element => $key) {
                if ($key === null) {
                    continue;
                }

                if (! isset($elements[$element])) {
                    $missing[] = $section.'.'.$element;

                    continue;
                }

                $sections[$section] = array_merge(
                    $sections[$section] ?? [],
                    $this->mapField($elements[$element], $key),
                );
            }
        }

        return $sections;
    }

    /**
     * Map a single XSD element to its validation rule.
     *
     * @param  array<string, mixed>  $facets
     * @return array<string, string>
     */
    protected function mapField(array $facets, string $key): array
    {
        // The economic activity repeats up to 4 times,
        // each a positive integer of up to 6 digits.
        if ($key === 'activity_code') {
            return [
                $key => 'required|array|max:'.$facets['maxOccurs'],
                $key.'.*' => implode('|', array_filter(
                    $this->mapper->map(array_merge($facets, ['minOccurs' => 1, 'maxOccurs' => 1])),
                    static fn (string $rule): bool => $rule !== 'required',
                )),
            ];
        }

        return [$key => implode('|', $this->mapper->map($facets))];
    }

    /**
     * Fail loudly when the field map does not cover the parsed elements.
     *
     * @param  list<string>  $missing
     */
    protected function guardAgainstUnmappedElements(array $missing): void
    {
        if ($missing === []) {
            return;
        }

        throw new RuntimeException(
            'Unmapped XSD elements (add them to dev/field-map.php): '.implode(', ', $missing),
        );
    }

    /**
     * Overlay the corrections the generic facet mapping cannot express.
     *
     * @param  array<string, array<string, string>>  $sections
     * @return array<string, array<string, string>>
     */
    protected function applyCorrections(array $sections): array
    {
        foreach (static::CORRECTIONS as $section => $rules) {
            $sections[$section] = array_merge($sections[$section] ?? [], $rules);
        }

        return $sections;
    }

    /**
     * Hash the XSD sources so the generated class can detect drift.
     *
     * @param  list<string>  $files
     */
    protected function sourceHash(array $files): string
    {
        return hash('sha256', implode('', array_map(
            static fn (string $file): string => file_get_contents($file),
            $files,
        )));
    }
}
