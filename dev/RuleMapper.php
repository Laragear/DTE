<?php

namespace Laragear\Dte\Dev;

use function array_merge;
use function implode;
use function is_numeric;

/**
 * Map XSD facet maps to Laravel validation rule strings.
 *
 * Holds the single facet→rule translation table. Emits plain rule
 * strings only: no closures, no Rule objects, no conditionals.
 *
 * @internal Library development tooling only.
 */
class RuleMapper
{
    /**
     * Map one element facet map to rule parts.
     *
     * @param  array<string, mixed>  $facets
     * @return list<string>
     */
    public function map(array $facets): array
    {
        $rules = [($facets['minOccurs'] ?? 1) === 0 ? 'nullable' : 'required'];

        return array_merge($rules, $this->mapBase($facets), $this->mapFacets($facets));
    }

    /**
     * Map the base type to its rules.
     *
     * @param  array<string, mixed>  $facets
     * @return list<string>
     */
    protected function mapBase(array $facets): array
    {
        $base = $facets['base'] ?? 'string';

        $short = str_contains($base, ':') ? substr($base, strpos($base, ':') + 1) : $base;

        return match ($short) {
            'positiveInteger', 'nonNegativeInteger', 'integer' => ['integer'],
            'decimal' => ['numeric'],
            'date' => ['date', 'date_format:Y-m-d'],
            'dateTime' => ['date', 'date_format:Y-m-d\TH:i:s'],
            'time' => ['date_format:H:i:s'],
            default => $this->mapStringBase($facets, $short),
        };
    }

    /**
     * Map string-like base types to their rules.
     *
     * @param  array<string, mixed>  $facets
     * @return list<string>
     */
    protected function mapStringBase(array $facets, string $short): array
    {
        if (($facets['enum'] ?? null) && $this->isIntegerEnum($facets['enum'])) {
            return ['integer'];
        }

        return match (true) {
            in_array($short, ['RUTType', 'MailType'], true) => ['string'],
            in_array($short, ['MntImpType', 'ValorType', 'FolioType', 'NroResolType', 'MontoType'], true) => ['integer'],
            in_array($short, ['Dec16_2Type', 'Dec14_4Type', 'Dec14_4-0Type', 'Dec8_4Type', 'Dec6_4Type', 'PctType', 'Dec12_6Type'], true) => ['numeric'],
            $short === 'FechaType' => ['date', 'date_format:Y-m-d'],
            $short === 'FechaHoraType' => ['date', 'date_format:Y-m-d\TH:i:s'],
            $short === 'HoraType' => ['date_format:H:i:s'],
            default => ['string'],
        };
    }

    /**
     * Map length, range, digit and enumeration facets.
     *
     * @param  array<string, mixed>  $facets
     * @return list<string>
     */
    protected function mapFacets(array $facets): array
    {
        $rules = [];

        if (isset($facets['maxLength'])) {
            $rules[] = 'max:'.$facets['maxLength'];
        }

        if (isset($facets['minLength'])) {
            $rules[] = 'min:'.$facets['minLength'];
        }

        if (isset($facets['length'])) {
            $rules[] = 'size:'.$facets['length'];
        }

        if (isset($facets['pattern'])) {
            $rules[] = 'regex:/^'.str_replace('/', '\/', $facets['pattern']).'$/';
        }

        if (isset($facets['enum'])) {
            $rules[] = 'in:'.implode(',', $facets['enum']);
        }

        $rules = array_merge($rules, $this->mapBounds($facets), $this->mapDigits($facets));

        return $rules;
    }

    /**
     * Map inclusive bounds to min/max or date bounds.
     *
     * @param  array<string, mixed>  $facets
     * @return list<string>
     */
    protected function mapBounds(array $facets): array
    {
        $rules = [];
        $isDate = ($facets['base'] ?? '') === 'xs:date' || str_contains($facets['base'] ?? '', 'Fecha');

        foreach (['minInclusive' => 'min', 'maxInclusive' => 'max'] as $facet => $rule) {
            if (! isset($facets[$facet])) {
                continue;
            }

            if ($bound = $this->boundRule($rule, $facets[$facet], $isDate)) {
                $rules[] = $bound;
            }
        }

        return array_merge($rules, $this->implicitMinimum($facets));
    }

    /**
     * Map one inclusive bound to a rule, or null when it is not a bound.
     */
    protected function boundRule(string $rule, string $value, bool $isDate): ?string
    {
        if (! is_numeric($value) && ! $this->isDate($value)) {
            return null;
        }

        if ($isDate || $this->isDate($value)) {
            return ($rule === 'min' ? 'after_or_equal:' : 'before_or_equal:').$value;
        }

        return $rule.':'.$value;
    }

    /**
     * Map the implicit lower bound of the integer base types.
     *
     * @param  array<string, mixed>  $facets
     * @return list<string>
     */
    protected function implicitMinimum(array $facets): array
    {
        if (isset($facets['minInclusive'])) {
            return [];
        }

        $base = $facets['base'] ?? '';

        return match (true) {
            str_contains($base, 'positiveInteger') => ['min:1'],
            str_contains($base, 'nonNegativeInteger') => ['min:0'],
            default => [],
        };
    }

    /**
     * Map digit and fraction constraints.
     *
     * @param  array<string, mixed>  $facets
     * @return list<string>
     */
    protected function mapDigits(array $facets): array
    {
        $rules = [];

        if (isset($facets['fractionDigits'])) {
            // The decimal rule demands an exact decimal count, so a
            // regex bounds the fraction digits instead.
            $rules[] = 'regex:/^\d+(\.\d{1,'.$facets['fractionDigits'].'})?$/';
        }

        if (isset($facets['totalDigits']) && ! isset($facets['fractionDigits'])) {
            $rules[] = 'max_digits:'.$facets['totalDigits'];
        }

        return $rules;
    }

    /**
     * Check if every enumeration value is an integer.
     *
     * @param  list<string>  $enum
     */
    protected function isIntegerEnum(array $enum): bool
    {
        foreach ($enum as $value) {
            if (! is_numeric($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a value looks like a date bound.
     */
    protected function isDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}/', $value);
    }
}
