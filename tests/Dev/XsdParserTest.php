<?php

namespace Tests\Dev;

use Laragear\Dte\Dev\XsdParser;
use RuntimeException;
use Tests\TestCase;

use function dirname;

class XsdParserTest extends TestCase
{
    protected XsdParser $parser;

    /**
     * The XSD sources the generator reads.
     *
     * @var list<string>
     */
    protected array $sources;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new XsdParser;

        $root = dirname(__DIR__, 2);

        $this->sources = [
            $root.'/resources/xsd/DTE_v10.xsd',
            $root.'/resources/xsd/SiiTypes_v10.xsd',
        ];
    }

    public function test_parse_throws_when_a_file_is_missing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('XSD file not found [/missing/schema.xsd].');

        $this->parser->parse(['/missing/schema.xsd']);
    }

    public function test_parse_reads_element_occurrences_and_facets(): void
    {
        $elements = $this->parser->parse($this->sources)['elements'];

        static::assertSame([
            'minOccurs' => 1,
            'maxOccurs' => 4,
            'base' => 'xs:positiveInteger',
            'totalDigits' => '6',
        ], $elements['Acteco']);

        static::assertSame([
            'minOccurs' => 1,
            'maxOccurs' => 1,
            'base' => 'xs:string',
            'maxLength' => '10',
            'minLength' => '3',
            'pattern' => '[0-9]+-([0-9]|K)',
        ], $elements['RUTEmisor']);
    }

    public function test_parse_reads_optional_elements_as_zero_occurrence(): void
    {
        $elements = $this->parser->parse($this->sources)['elements'];

        static::assertSame(0, $elements['DscItem']['minOccurs']);
        static::assertSame(['minOccurs' => 0, 'maxOccurs' => 2, 'base' => 'SiiDte:FonoType', 'maxLength' => '20'], $elements['Telefono']);
    }

    public function test_parse_resolves_named_types_declared_in_an_included_schema(): void
    {
        $parsed = $this->parser->parse($this->sources);

        static::assertSame('SiiDte:RznSocLargaType', $parsed['elements']['RznSoc']['base']);
        static::assertArrayHasKey('RznSocLargaType', $parsed['types']);
        static::assertSame('100', $parsed['types']['RznSocLargaType']['maxLength'] ?? null);
    }

    public function test_parse_keeps_the_first_declaration_of_a_repeated_name(): void
    {
        $elements = $this->parser->parse($this->sources)['elements'];

        // Documento is declared by both the Document and Liquidacion branches.
        static::assertArrayHasKey('Documento', $elements);
        static::assertSame(1, $elements['Documento']['minOccurs']);
    }

    public function test_parse_reads_enumeration_and_inline_restrictions(): void
    {
        $elements = $this->parser->parse($this->sources)['elements'];

        static::assertSame(['CH', 'LT', 'EF', 'PE', 'TC', 'CF', 'OT'], $elements['MedioPago']['enum']);
        static::assertSame('1', $elements['TpoImpresion']['length']);
    }

    public function test_parse_is_repeatable_on_the_same_instance(): void
    {
        $first = $this->parser->parse($this->sources);
        $second = $this->parser->parse($this->sources);

        static::assertSame($first, $second);
    }

    public function test_parse_discards_types_left_by_a_previous_run(): void
    {
        $root = dirname(__DIR__, 2);

        $this->parser->parse($this->sources);

        $narrow = $this->parser->parse([$root.'/resources/xsd/DTE_v10.xsd']);

        // DTE_v10 includes SiiTypes rather than declaring its own named types,
        // so parsing it alone must leave no type table behind.
        static::assertSame([], $narrow['types']);
    }

    public function test_parse_reads_a_single_file_independently(): void
    {
        $parsed = $this->parser->parse([dirname(__DIR__, 2).'/resources/xsd/SiiTypes_v10.xsd']);

        static::assertNotSame([], $parsed['types']);
        static::assertSame([], $parsed['elements']);
    }
}
