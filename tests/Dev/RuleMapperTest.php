<?php

namespace Tests\Dev;

use Laragear\Dte\Dev\RuleMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RuleMapperTest extends TestCase
{
    protected RuleMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new RuleMapper;
    }

    /**
     * @return array<string, array{array<string, mixed>, list<string>}>
     */
    public static function providesBaseTypes(): array
    {
        return [
            'positive integer' => [['base' => 'xs:positiveInteger'], ['required', 'integer', 'min:1']],
            'non negative integer' => [['base' => 'xs:nonNegativeInteger'], ['required', 'integer', 'min:0']],
            'integer' => [['base' => 'xs:integer'], ['required', 'integer']],
            'decimal' => [['base' => 'xs:decimal'], ['required', 'numeric']],
            'date' => [['base' => 'xs:date'], ['required', 'date', 'date_format:Y-m-d']],
            'date time' => [['base' => 'xs:dateTime'], ['required', 'date', 'date_format:Y-m-d\TH:i:s']],
            'time' => [['base' => 'xs:time'], ['required', 'date_format:H:i:s']],
            'rut type' => [['base' => 'SiiDte:RUTType'], ['required', 'string']],
            'mail type' => [['base' => 'SiiDte:MailType'], ['required', 'string']],
            'monto type' => [['base' => 'SiiDte:MontoType'], ['required', 'integer']],
            'folio type' => [['base' => 'SiiDte:FolioType'], ['required', 'integer']],
            'moneda type' => [['base' => 'SiiDte:MntImpType'], ['required', 'integer']],
            'dec 16_2 type' => [['base' => 'SiiDte:Dec16_2Type'], ['required', 'numeric']],
            'pct type' => [['base' => 'SiiDte:PctType'], ['required', 'numeric']],
            'fecha type' => [['base' => 'SiiDte:FechaType'], ['required', 'date', 'date_format:Y-m-d']],
            'fecha hora type' => [['base' => 'SiiDte:FechaHoraType'], ['required', 'date', 'date_format:Y-m-d\TH:i:s']],
            'hora type' => [['base' => 'SiiDte:HoraType'], ['required', 'date_format:H:i:s']],
            'unprefixed string' => [['base' => 'string'], ['required', 'string']],
            'unknown base' => [['base' => 'SiiDte:UnheardOfType'], ['required', 'string']],
        ];
    }

    /**
     * @param  array<string, mixed>  $facets
     * @param  list<string>  $expected
     */
    #[DataProvider('providesBaseTypes')]
    public function test_maps_base_types(array $facets, array $expected): void
    {
        static::assertSame($expected, $this->mapper->map($facets));
    }

    /**
     * @return array<string, array{array<string, mixed>, list<string>}>
     */
    public static function providesOccurrenceFacets(): array
    {
        return [
            'required by default' => [[], ['required', 'string']],
            'optional element is nullable' => [['minOccurs' => 0], ['nullable', 'string']],
        ];
    }

    /**
     * @param  array<string, mixed>  $facets
     * @param  list<string>  $expected
     */
    #[DataProvider('providesOccurrenceFacets')]
    public function test_maps_element_occurrence(array $facets, array $expected): void
    {
        static::assertSame($expected, $this->mapper->map($facets));
    }

    /**
     * @return array<string, array{array<string, mixed>, list<string>}>
     */
    public static function providesLengthFacets(): array
    {
        return [
            'max length' => [['maxLength' => '100'], ['required', 'string', 'max:100']],
            'min length' => [['minLength' => '3'], ['required', 'string', 'min:3']],
            'fixed length' => [['length' => '1'], ['required', 'string', 'size:1']],
            'length range' => [['maxLength' => '18', 'minLength' => '1'], ['required', 'string', 'max:18', 'min:1']],
        ];
    }

    /**
     * @param  array<string, mixed>  $facets
     * @param  list<string>  $expected
     */
    #[DataProvider('providesLengthFacets')]
    public function test_maps_length_facets(array $facets, array $expected): void
    {
        static::assertSame($expected, $this->mapper->map($facets));
    }

    /**
     * @return array<string, array{array<string, mixed>, list<string>}>
     */
    public static function providesPatternFacets(): array
    {
        return [
            'bare pattern' => [['pattern' => '[0-9]+'], ['required', 'string', 'regex:/^[0-9]+$/']],
            'pattern with slash is escaped' => [['pattern' => '[a-z/]+'], ['required', 'string', 'regex:/^[a-z\/]+$/']],
        ];
    }

    /**
     * @param  array<string, mixed>  $facets
     * @param  list<string>  $expected
     */
    #[DataProvider('providesPatternFacets')]
    public function test_maps_pattern_facets(array $facets, array $expected): void
    {
        static::assertSame($expected, $this->mapper->map($facets));
    }

    /**
     * @return array<string, array{array<string, mixed>, list<string>}>
     */
    public static function providesEnumerationFacets(): array
    {
        return [
            'string enum' => [
                ['base' => 'xs:string', 'enum' => ['CH', 'LT', 'EF']],
                ['required', 'string', 'in:CH,LT,EF'],
            ],
            'integer enum collapses to integer' => [
                ['base' => 'xs:positiveInteger', 'enum' => ['1', '2', '3']],
                ['required', 'integer', 'in:1,2,3', 'min:1'],
            ],
            'mixed enum keeps the in rule' => [
                ['base' => 'xs:string', 'enum' => ['1', 'A']],
                ['required', 'string', 'in:1,A'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $facets
     * @param  list<string>  $expected
     */
    #[DataProvider('providesEnumerationFacets')]
    public function test_maps_enumeration_facets(array $facets, array $expected): void
    {
        static::assertSame($expected, $this->mapper->map($facets));
    }

    /**
     * @return array<string, array{array<string, mixed>, list<string>}>
     */
    public static function providesInclusiveBounds(): array
    {
        return [
            'numeric minimum' => [['base' => 'xs:string', 'minInclusive' => '5'], ['required', 'string', 'min:5']],
            'numeric maximum' => [['base' => 'xs:string', 'maxInclusive' => '99'], ['required', 'string', 'max:99']],
            'numeric range' => [
                ['base' => 'xs:string', 'minInclusive' => '1', 'maxInclusive' => '99'],
                ['required', 'string', 'min:1', 'max:99'],
            ],
            'positive integer is bounded' => [
                ['base' => 'xs:positiveInteger', 'minInclusive' => '1', 'maxInclusive' => '99'],
                ['required', 'integer', 'min:1', 'max:99'],
            ],
            'explicit minimum suppresses the implicit one' => [
                ['base' => 'xs:positiveInteger', 'minInclusive' => '3'],
                ['required', 'integer', 'min:3'],
            ],
            'explicit zero suppresses the implicit non negative minimum' => [
                ['base' => 'xs:nonNegativeInteger', 'minInclusive' => '0'],
                ['required', 'integer', 'min:0'],
            ],
            'non numeric bound is ignored' => [
                ['base' => 'xs:string', 'minInclusive' => 'abc'],
                ['required', 'string'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $facets
     * @param  list<string>  $expected
     */
    #[DataProvider('providesInclusiveBounds')]
    public function test_maps_inclusive_bounds(array $facets, array $expected): void
    {
        static::assertSame($expected, $this->mapper->map($facets));
    }

    /**
     * @return array<string, array{array<string, mixed>, list<string>}>
     */
    public static function providesDateBounds(): array
    {
        return [
            'date base uses date comparisons' => [
                ['base' => 'xs:date', 'minInclusive' => '2000-01-01', 'maxInclusive' => '2050-12-31'],
                ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2050-12-31'],
            ],
            'date bound on a string base is detected' => [
                ['base' => 'xs:string', 'minInclusive' => '2000-01-01'],
                ['required', 'string', 'after_or_equal:2000-01-01'],
            ],
            'fecha base forces date comparisons' => [
                ['base' => 'SiiDte:FechaType', 'minInclusive' => '5'],
                ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:5'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $facets
     * @param  list<string>  $expected
     */
    #[DataProvider('providesDateBounds')]
    public function test_maps_date_bounds(array $facets, array $expected): void
    {
        static::assertSame($expected, $this->mapper->map($facets));
    }

    /**
     * @return array<string, array{array<string, mixed>, list<string>}>
     */
    public static function providesDigitFacets(): array
    {
        return [
            'fraction digits become a regex' => [
                ['base' => 'xs:decimal', 'fractionDigits' => '2'],
                ['required', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/'],
            ],
            'total digits become max digits' => [
                ['base' => 'xs:positiveInteger', 'totalDigits' => '6'],
                ['required', 'integer', 'min:1', 'max_digits:6'],
            ],
            'fraction digits win over total digits' => [
                ['base' => 'xs:decimal', 'totalDigits' => '18', 'fractionDigits' => '6'],
                ['required', 'numeric', 'regex:/^\d+(\.\d{1,6})?$/'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $facets
     * @param  list<string>  $expected
     */
    #[DataProvider('providesDigitFacets')]
    public function test_maps_digit_facets(array $facets, array $expected): void
    {
        static::assertSame($expected, $this->mapper->map($facets));
    }

    public function test_maps_an_element_declared_without_a_type(): void
    {
        static::assertSame(['required', 'string'], $this->mapper->map(['minOccurs' => 1, 'maxOccurs' => 1]));
    }
}
