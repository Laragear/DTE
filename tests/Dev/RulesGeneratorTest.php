<?php

namespace Tests\Dev;

use Laragear\Dte\Dev\ClassEmitter;
use Laragear\Dte\Dev\RuleMapper;
use Laragear\Dte\Dev\RulesGenerator;
use Laragear\Dte\Dev\XsdParser;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

use function array_combine;
use function array_fill;
use function array_filter;
use function array_keys;
use function array_merge;
use function array_values;
use function count;
use function dirname;
use function file_get_contents;
use function hash;

class RulesGeneratorTest extends TestCase
{
    protected string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    /**
     * Build a generator over the given collaborators, defaulting to real ones.
     */
    protected function generator(
        XsdParser|MockInterface|null $parser = null,
        RuleMapper|MockInterface|null $mapper = null,
        ClassEmitter|MockInterface|null $emitter = null,
    ): RulesGenerator {
        return new RulesGenerator(
            $parser ?? new XsdParser,
            $mapper ?? new RuleMapper,
            $emitter ?? new ClassEmitter,
        );
    }

    /**
     * The hand-maintained field map the generator reads.
     *
     * @return array<string, array<string, string|null>>
     */
    protected static function fieldMap(): array
    {
        return require dirname(__DIR__, 2).'/dev/field-map.php';
    }

    /**
     * Every XSD element the field map maps to a builder key.
     *
     * @return list<string>
     */
    protected static function mappedElements(): array
    {
        return array_merge(...array_values(array_map(
            static fn (array $fields): array => array_keys(array_filter($fields)),
            static::fieldMap(),
        )));
    }

    /**
     * The facets of a plain required single-occurrence string element.
     *
     * @return array<string, mixed>
     */
    protected static function stringFacets(): array
    {
        return ['minOccurs' => 1, 'maxOccurs' => 1, 'base' => 'xs:string'];
    }

    /**
     * Facets resolving every mapped element, with one element overridden.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, array<string, mixed>>
     */
    protected static function elements(array $overrides = []): array
    {
        $mapped = static::mappedElements();

        return array_merge(
            array_combine($mapped, array_fill(0, count($mapped), static::stringFacets())),
            $overrides,
        );
    }

    /**
     * A parser double resolving every mapped element.
     *
     * @param  array<string, array<string, mixed>>  $elements
     */
    protected function parserReturning(array $elements): MockInterface
    {
        $parser = Mockery::mock(XsdParser::class);

        $parser->expects('parse')->once()->andReturn(['types' => [], 'elements' => $elements]);

        return $parser;
    }

    /**
     * An emitter double capturing the sections it is handed.
     *
     * @param  array<string, array<string, string>>|null  $sections
     */
    protected function emitterCapturing(?array &$sections): MockInterface
    {
        $emitter = Mockery::mock(ClassEmitter::class);

        $emitter->expects('emit')
            ->once()
            ->with(Mockery::capture($sections), Mockery::type('string'), Mockery::type('string'))
            ->andReturn('generated');

        return $emitter;
    }

    public function test_generation_is_deterministic(): void
    {
        $generator = $this->generator();

        static::assertSame($generator->generate($this->root), $generator->generate($this->root));
    }

    public function test_generation_is_stable_across_separate_compositions(): void
    {
        static::assertSame(
            $this->generator()->generate($this->root),
            $this->generator()->generate($this->root),
        );
    }

    public function test_generate_reads_the_declared_xsd_sources(): void
    {
        $parser = Mockery::mock(XsdParser::class);

        $parser->expects('parse')
            ->once()
            ->with([
                $this->root.'/resources/xsd/DTE_v10.xsd',
                $this->root.'/resources/xsd/SiiTypes_v10.xsd',
            ])
            ->andReturn(['types' => [], 'elements' => static::elements()]);

        $this->generator($parser)->generate($this->root);
    }

    public function test_generate_lists_every_unmapped_element(): void
    {
        $expected = 'Unmapped XSD elements (add them to dev/field-map.php): ';

        foreach (static::fieldMap() as $section => $fields) {
            foreach (array_keys(array_filter($fields)) as $element) {
                $expected .= "$section.$element, ";
            }
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs(rtrim($expected, ', '));

        $this->generator($this->parserReturning([]))->generate($this->root);
    }

    public function test_generate_does_not_emit_when_elements_are_missing(): void
    {
        $mapper = Mockery::mock(RuleMapper::class);
        $mapper->expects('map')->never();

        $emitter = Mockery::mock(ClassEmitter::class);
        $emitter->expects('emit')->never();

        $this->expectException(RuntimeException::class);

        $this->generator($this->parserReturning([]), $mapper, $emitter)->generate($this->root);
    }

    public function test_generate_names_the_xsd_sources(): void
    {
        $sources = null;

        $emitter = Mockery::mock(ClassEmitter::class);

        $emitter->expects('emit')
            ->once()
            ->with(Mockery::type('array'), Mockery::type('string'), Mockery::capture($sources))
            ->andReturn('generated');

        $this->generator($this->parserReturning(static::elements()), null, $emitter)
            ->generate($this->root);

        static::assertSame(
            'resources/xsd/DTE_v10.xsd + resources/xsd/SiiTypes_v10.xsd',
            $sources,
        );
    }

    public function test_generate_hashes_the_current_xsd_contents(): void
    {
        $hash = null;

        $emitter = Mockery::mock(ClassEmitter::class);

        $emitter->expects('emit')
            ->once()
            ->with(Mockery::type('array'), Mockery::capture($hash), Mockery::type('string'))
            ->andReturn('generated');

        $this->generator($this->parserReturning(static::elements()), null, $emitter)
            ->generate($this->root);

        static::assertSame(
            hash('sha256', file_get_contents($this->root.'/resources/xsd/DTE_v10.xsd')
                .file_get_contents($this->root.'/resources/xsd/SiiTypes_v10.xsd')),
            $hash,
        );
    }

    public function test_generate_overrides_mapped_rules_with_corrections(): void
    {
        $sections = null;

        $this->generator($this->parserReturning(static::elements()), null, $this->emitterCapturing($sections))
            ->generate($this->root);

        static::assertSame(
            'required|integer|in:30,32,33,34,39,41,43,46,52,56,61',
            $sections['DOCUMENT']['document_type'],
        );

        static::assertSame('required|sii_rut', $sections['ISSUER']['rut']);
        static::assertSame('required|boolean', $sections['ITEM']['exempt']);
    }

    public function test_generate_emits_corrections_for_every_declared_section(): void
    {
        $sections = null;

        $this->generator($this->parserReturning(static::elements()), null, $this->emitterCapturing($sections))
            ->generate($this->root);

        foreach (RulesGenerator::CORRECTIONS as $section => $rules) {
            foreach ($rules as $key => $rule) {
                static::assertSame($rule, $sections[$section][$key], "Missing correction [$section.$key].");
            }
        }
    }

    public function test_generate_keeps_mapped_rules_beside_the_corrections(): void
    {
        $sections = null;

        $mapper = Mockery::mock(RuleMapper::class);
        $mapper->expects('map')->zeroOrMoreTimes()->andReturn(['required', 'string']);

        $this->generator($this->parserReturning(static::elements()), $mapper, $this->emitterCapturing($sections))
            ->generate($this->root);

        // A corrected key is overridden, an untouched mapped key survives.
        static::assertSame('required|sii_rut', $sections['ISSUER']['rut']);
        static::assertSame('required|string', $sections['ISSUER']['branch']);
        static::assertSame('required|string', $sections['ISSUER']['telephone']);
    }

    public function test_generate_maps_parsed_elements_through_the_mapper(): void
    {
        $mapper = Mockery::mock(RuleMapper::class);

        // Every mapped element resolves with the same facets, so the mapper
        // returns a distinct rule only for the first one it is handed.
        $mapper->expects('map')
            ->zeroOrMoreTimes()
            ->with(static::stringFacets())
            ->andReturn(['required', 'string', 'max:100'], ['required', 'string']);

        $sections = null;

        $this->generator(
            $this->parserReturning(static::elements()),
            $mapper,
            $this->emitterCapturing($sections),
        )->generate($this->root);

        // The first field map entry under ISSUER is RUTEmisor, so it is the
        // one carrying the distinctive rule. Its key is overwritten by a
        // correction, so the untouched `branch` proves the same wiring.
        static::assertSame('required|string', $sections['ISSUER']['branch']);
        static::assertSame('required|string', $sections['ISSUER']['telephone']);
        static::assertSame('required|sii_rut', $sections['ISSUER']['rut']);
    }

    public function test_generate_never_maps_elements_the_field_map_ignores(): void
    {
        // CdgSIISucur maps to null in dev/field-map.php, so the mapper must
        // never be called at all when only that element resolves.
        $mapper = Mockery::mock(RuleMapper::class);
        $mapper->expects('map')->never();

        // The unmapped guard fires first, so the emitter is never reached.
        $emitter = Mockery::mock(ClassEmitter::class);
        $emitter->expects('emit')->never();

        $this->expectException(RuntimeException::class);

        $this->generator($this->parserReturning(['CdgSIISucur' => static::stringFacets()]), $mapper, $emitter)
            ->generate($this->root);
    }

    public function test_generate_maps_repeating_activity_codes_to_an_array_rule(): void
    {
        $facets = ['minOccurs' => 1, 'maxOccurs' => 1, 'base' => 'xs:positiveInteger', 'totalDigits' => '6'];

        $mapper = Mockery::mock(RuleMapper::class);

        // The activity code is clamped to a single occurrence before mapping.
        $mapper->expects('map')->once()->with($facets)->andReturn(['required', 'integer', 'min:1']);

        $mapper->expects('map')->zeroOrMoreTimes()->andReturn(['required', 'string']);

        $sections = null;

        $this->generator(
            $this->parserReturning(static::elements([
                'Acteco' => ['minOccurs' => 1, 'maxOccurs' => 4, 'base' => 'xs:positiveInteger', 'totalDigits' => '6'],
            ])),
            $mapper,
            $this->emitterCapturing($sections),
        )->generate($this->root);

        static::assertSame('required|array|max:4', $sections['ISSUER']['activity_code']);
        static::assertSame('integer|min:1', $sections['ISSUER']['activity_code.*']);
    }

    public function test_generate_produces_the_committed_rules_source(): void
    {
        static::assertSame(
            file_get_contents($this->root.'/src/Validation/DteRules.php'),
            $this->generator()->generate($this->root),
        );
    }

    /**
     * Guard the injected parser: it alone decides which rules are emitted.
     */
    public function test_generate_uses_the_injected_parser(): void
    {
        $parser = Mockery::mock(XsdParser::class);

        $parser->expects('parse')
            ->once()
            ->with([
                $this->root.'/resources/xsd/DTE_v10.xsd',
                $this->root.'/resources/xsd/SiiTypes_v10.xsd',
            ])
            ->andReturn(['types' => [], 'elements' => [
                'RznSoc' => ['minOccurs' => 1, 'maxOccurs' => 1, 'base' => 'xs:string'],
            ]]);

        $mapper = Mockery::mock(RuleMapper::class);

        $mapper->expects('map')
            ->once()
            ->with(['minOccurs' => 1, 'maxOccurs' => 1, 'base' => 'xs:string'])
            ->andReturn(['required', 'from-double']);

        // The double resolves only RznSoc, so every other mapped element is
        // reported missing; RznSoc is absent from that list, proving the
        // parser — not the real XSD files — decided what resolved.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs(
            'Unmapped XSD elements (add them to dev/field-map.php): ISSUER.RUTEmisor, '
            .'ISSUER.GiroEmis, ISSUER.Acteco, ISSUER.Telefono, ISSUER.CorreoEmisor, '
            .'ISSUER.Sucursal, ISSUER.DirOrigen, ISSUER.CmnaOrigen, ISSUER.CiudadOrigen, '
            .'RECEIVER.RUTRecep, RECEIVER.RznSocRecep, RECEIVER.GiroRecep, RECEIVER.CorreoRecep, '
            .'RECEIVER.DirRecep, RECEIVER.CmnaRecep, RECEIVER.CiudadRecep, ITEM.NmbItem, '
            .'ITEM.DscItem, ITEM.QtyItem, ITEM.UnmdItem, ITEM.PrcItem, ITEM.DescuentoPct, '
            .'ITEM.DescuentoMonto, ITEM.IndExe, ITEM.CodImpAdic, ITEM.TpoCodigo, ITEM.VlrCodigo, '
            .'REFERENCE.TpoDocRef, REFERENCE.FolioRef, REFERENCE.FchRef, REFERENCE.CodRef, '
            .'REFERENCE.RazonRef, GLOBAL_MODIFIER.TpoMov, GLOBAL_MODIFIER.GlosaDR, '
            .'GLOBAL_MODIFIER.TpoValor, GLOBAL_MODIFIER.ValorDR, GLOBAL_MODIFIER.IndExeDR, '
            .'PAYMENT_TERM.FmaPago, PAYMENT_TERM.FchVenc, TRANSPORT.Patente, '
            .'TRANSPORT.PatenteCarro, TRANSPORT.RUTTrans, TRANSPORT.RUTChofer, '
            .'TRANSPORT.NombreChofer, TRANSPORT.DirDest, TRANSPORT.CmnaDest, '
            .'TRANSPORT.CiudadDest, TRANSPORT.FchSalida, TRANSPORT.FchLlegada, TOTALS.MntNeto, '
            .'TOTALS.MntExe, TOTALS.IVA, TOTALS.MntTotal, TOTALS.MontoNF, DOCUMENT.TipoDTE, '
            .'DOCUMENT.Folio, DOCUMENT.FchEmis, DOCUMENT.IndNoRebaja, DOCUMENT.TipoDespacho, '
            .'DOCUMENT.IndTraslado, HEADER_ID_DOC.TipoDTE, HEADER_ID_DOC.FchEmis, '
            .'HEADER_ID_DOC.IndNoRebaja, HEADER_ID_DOC.TipoDespacho, HEADER_ID_DOC.IndTraslado, '
            .'HEADER_ID_DOC.MntPagos, HEADER_ID_DOC.FchPago, HEADER_ID_DOC.MntPago, '
            .'HEADER_ID_DOC.GlosaPagos, HEADER_ID_DOC.FmaPago, HEADER_ID_DOC.FchVenc, '
            .'HEADER_OTHER_CURRENCY.TpoMoneda, HEADER_OTHER_CURRENCY.TpoCambio, '
            .'HEADER_OTHER_CURRENCY.MntNetoOtrMnda, HEADER_OTHER_CURRENCY.MntExeOtrMnda, '
            .'HEADER_OTHER_CURRENCY.MntFaeCarneOtrMnda, HEADER_OTHER_CURRENCY.MntMargComOtrMnda, '
            .'HEADER_OTHER_CURRENCY.IVAOtrMnda, HEADER_OTHER_CURRENCY.IVANoRetOtrMnda, '
            .'HEADER_OTHER_CURRENCY.MntTotOtrMnda, HEADER_OTHER_CURRENCY.ImpRetOtrMnda, '
            .'HEADER_OTHER_CURRENCY.TipoImpOtrMnda, HEADER_OTHER_CURRENCY.TasaImpOtrMnda, '
            .'HEADER_OTHER_CURRENCY.VlrImpOtrMnda, DETAIL_ITEMS.NmbItem, DETAIL_ITEMS.DscItem, '
            .'DETAIL_ITEMS.QtyItem, DETAIL_ITEMS.UnmdItem, DETAIL_ITEMS.PrcItem, '
            .'DETAIL_ITEMS.DescuentoPct, DETAIL_ITEMS.DescuentoMonto, DETAIL_ITEMS.IndExe, '
            .'DETAIL_ITEMS.CodImpAdic, DETAIL_ITEMS.TpoCodigo, DETAIL_ITEMS.VlrCodigo, '
            .'SUBTOTALS.GlosaSTI, SUBTOTALS.OrdenSTI, SUBTOTALS.SubTotNetoSTI, '
            .'SUBTOTALS.SubTotIVASTI, SUBTOTALS.SubTotAdicSTI, SUBTOTALS.SubTotExeSTI, '
            .'SUBTOTALS.ValSubtotSTI, SUBTOTALS.LineasDeta, GLOBAL_MODIFIERS.TpoMov, '
            .'GLOBAL_MODIFIERS.GlosaDR, GLOBAL_MODIFIERS.TpoValor, GLOBAL_MODIFIERS.ValorDR, '
            .'GLOBAL_MODIFIERS.IndExeDR, REFERENCES.TpoDocRef, REFERENCES.FolioRef, '
            .'REFERENCES.FchRef, REFERENCES.CodRef, REFERENCES.RazonRef, COMMISSIONS.TipoMovim, '
            .'COMMISSIONS.Glosa, COMMISSIONS.TasaComision, COMMISSIONS.ValComNeto, '
            .'COMMISSIONS.ValComExe, COMMISSIONS.ValComIVA, EMISSION_GEOREF.LatitudEmision, '
            .'EMISSION_GEOREF.LongitudEmision, EMISSION_GEOREF.SistemaReferencia, '
            .'TIMBER_HANDLING.ComunaRolOrigen, TIMBER_HANDLING.MnzRolOrigen, '
            .'TIMBER_HANDLING.PrdRolOrigen, TIMBER_HANDLING.ComunaRolDestino, '
            .'TIMBER_HANDLING.MnzRolDestino, TIMBER_HANDLING.PrdRolDestino, '
            .'TIMBER_HANDLING.CodPlanConaf, TIMBER_HANDLING.AvisoEjecucionFaena, '
            .'TIMBER_HANDLING.LatitudOrigenMadera, TIMBER_HANDLING.LongitudOrigenMadera, '
            .'TIMBER_HANDLING.LatitudDestinoMadera, TIMBER_HANDLING.LongitudDestinoMadera, '
            .'TIMBER_HANDLING.SistemareferenciaMadera',
        );

        $this->generator($parser, $mapper)->generate($this->root);
    }

    /**
     * Guard the injected mapper: swapping it must change the emitted rules.
     */
    public function test_generate_uses_the_injected_mapper(): void
    {
        $mapper = Mockery::mock(RuleMapper::class);
        $mapper->expects('map')->zeroOrMoreTimes()->andReturn(['required', 'injected']);

        $source = $this->generator(null, $mapper)->generate($this->root);

        static::assertStringContainsString("'required|injected'", $source);
        static::assertStringContainsString("'required|sii_rut'", $source, 'Corrections bypass the mapper.');
    }

    /**
     * Guard the injected emitter: swapping it must change the returned source.
     */
    public function test_generate_returns_what_the_injected_emitter_produced(): void
    {
        $emitter = Mockery::mock(ClassEmitter::class);
        $emitter->expects('emit')->once()->andReturn('from the double');

        static::assertSame(
            'from the double',
            $this->generator(null, null, $emitter)->generate($this->root),
        );
    }
}
