<?php

namespace Tests\Dev;

use Laragear\Dte\Dev\ClassEmitter;
use Tests\TestCase;

use function file_put_contents;
use function preg_match;
use function strpos;
use function tempnam;

class ClassEmitterTest extends TestCase
{
    protected ClassEmitter $emitter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->emitter = new ClassEmitter;
    }

    public function test_emit_renders_the_class_envelope(): void
    {
        $source = $this->emitter->emit(['DOCUMENT' => []], 'abc123', 'DTE_v10.xsd');

        static::assertStringContainsString('namespace Laragear\Dte\Validation;', $source);
        static::assertStringContainsString('final class DteRules', $source);
        static::assertStringContainsString('@generated from DTE_v10.xsd — DO NOT EDIT.', $source);
        static::assertStringContainsString("public const string SOURCE_HASH = 'abc123';", $source);
    }

    public function test_emit_sorts_sections_and_rules(): void
    {
        $source = $this->emitter->emit([
            'TYPEDOC' => ['b' => 'required|integer', 'a' => 'nullable'],
            'ADOCUMENT' => ['z' => 'required'],
        ], 'abc123', 'DTE_v10.xsd');

        static::assertSame(
            1,
            preg_match('/const array ADOCUMENT.*const array TYPEDOC/s', $source),
            'Sections must be emitted in alphabetical order.',
        );

        static::assertSame(
            1,
            preg_match("/'a' => 'nullable',.*'b' => 'required\|integer'/s", $source),
            'Rules must be emitted in alphabetical order.',
        );
    }

    public function test_emit_escapes_nothing_in_rule_values(): void
    {
        $source = $this->emitter->emit(
            ['DOCUMENT' => ['document_type' => 'required|integer|in:30,32,33']],
            'abc123',
            'DTE_v10.xsd',
        );

        static::assertStringContainsString("'document_type' => 'required|integer|in:30,32,33',", $source);
    }

    public function test_emit_renders_an_empty_section_map(): void
    {
        $source = $this->emitter->emit([], 'abc123', 'DTE_v10.xsd');

        static::assertStringNotContainsString('public const array', $source);
        static::assertStringContainsString("public const string SOURCE_HASH = 'abc123';", $source);
    }

    public function test_emit_produces_php_that_lints(): void
    {
        $source = $this->emitter->emit([
            'DOCUMENT' => ['document_type' => 'required|integer'],
            'ISSUER' => ['rut' => 'required|sii_rut'],
        ], 'abc123', 'DTE_v10.xsd');

        $file = tempnam(sys_get_temp_dir(), 'dte-rules-');

        file_put_contents($file, $source);

        exec('php -l '.escapeshellarg($file), $output, $status);

        unlink($file);

        static::assertSame(0, $status, 'Emitted source must be valid PHP. '.implode(PHP_EOL, $output));
    }

    public function test_emit_places_the_hash_before_the_section_constants(): void
    {
        $source = $this->emitter->emit(['DOCUMENT' => ['rut' => 'required']], 'abc123', 'DTE_v10.xsd');

        static::assertLessThan(
            strpos($source, 'public const array DOCUMENT'),
            strpos($source, 'SOURCE_HASH'),
            'The hash constant must be declared before the rule constants.',
        );
    }
}
