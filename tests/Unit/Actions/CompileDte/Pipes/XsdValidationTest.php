<?php

namespace Tests\Unit\Actions\CompileDte\Pipes;

use DOMDocument;
use Laragear\Dte\Actions\CompileDte\Compilation;
use Laragear\Dte\Actions\CompileDte\Pipes\XsdValidation;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Xml\XsdValidator;
use Mockery\MockInterface;
use Tests\TestCase;

class XsdValidationTest extends TestCase
{
    public function test_skips_validation_when_disabled(): void
    {
        $compilation = $this->makeCompilation();

        $this->mock(XsdValidator::class, static function (MockInterface $mock): void {
            $mock->shouldNotReceive('validate');
        });

        $pipe = $this->app->make(XsdValidation::class);

        $result = $pipe->handle($compilation, static fn(Compilation $c) => $compilation);

        static::assertSame($compilation, $result);
    }

    public function test_validates_when_enabled_and_document_exists(): void
    {
        $this->config('dte.validation.xsd_enabled', true);

        $compilation = $this->makeCompilation();

        $schema = $compilation->dte->document_type->schemaXsd();

        $this->mock(XsdValidator::class, static function (MockInterface $mock) use ($compilation, $schema): void {
            $mock->expects('validate')->with(
                $compilation->document->saveXML(),
                $schema
            );
        });

        $pipe = $this->app->make(XsdValidation::class);

        $pipe->handle($compilation, static fn(Compilation $c) => $compilation);
    }

    public function test_skips_validation_when_enabled_without_document(): void
    {
        $this->config('dte.validation.xsd_enabled', true);

        $dte = SiiDte::factory()->make(['document_type' => DteType::Invoice]);
        $compilation = new Compilation($dte);

        $this->mock(XsdValidator::class, static function (MockInterface $mock): void {
            $mock->shouldNotReceive('validate');
        });

        $pipe = $this->app->make(XsdValidation::class);

        $result = $pipe->handle($compilation, static fn(Compilation $c) => $compilation);

        static::assertSame($compilation, $result);
    }

    private function makeCompilation(): Compilation
    {
        $dte = SiiDte::factory()->make(['document_type' => DteType::Invoice]);

        $document = new DOMDocument;
        $document->loadXML('<DTE/>');

        return new Compilation($dte, $document);
    }
}
