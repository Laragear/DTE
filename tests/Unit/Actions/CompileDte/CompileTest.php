<?php

namespace Tests\Unit\Actions\CompileDte;

use Laragear\Dte\Actions\CompileDte\Compile;
use Laragear\Dte\Actions\CompileDte\Pipes\AcquireFolio;
use Laragear\Dte\Actions\CompileDte\Pipes\ApplyDigitalSignature;
use Laragear\Dte\Actions\CompileDte\Pipes\ApplyTedToDom;
use Laragear\Dte\Actions\CompileDte\Pipes\BuildXml;
use Laragear\Dte\Actions\CompileDte\Pipes\FireDteBuildingEvent;
use Laragear\Dte\Actions\CompileDte\Pipes\FireDteBuiltEvent;
use Laragear\Dte\Actions\CompileDte\Pipes\FireDteCompiledEvent;
use Laragear\Dte\Actions\CompileDte\Pipes\FireDteCompilingEvent;
use Laragear\Dte\Actions\CompileDte\Pipes\GenerateTed;
use Laragear\Dte\Actions\CompileDte\Pipes\ValidateState;
use Laragear\Dte\Actions\CompileDte\Pipes\XsdValidation;
use Laragear\Dte\Models\SiiDte;
use Laragear\MetaTesting\Pipeline\InteractsWithPipelines;
use Tests\TestCase;

class CompileTest extends TestCase
{
    use InteractsWithPipelines;

    public function test_pipes_order(): void
    {
        $this->pipeline(Compile::class)
            ->assertPipes([
                FireDteCompilingEvent::class,
                ValidateState::class,
                AcquireFolio::class,
                FireDteBuildingEvent::class,
                BuildXml::class,
                FireDteBuiltEvent::class,
                GenerateTed::class,
                ApplyTedToDom::class,
                ApplyDigitalSignature::class,
                XsdValidation::class,
                FireDteCompiledEvent::class,
            ]);
    }

    public function test_receives_dte_and_returns_compilation(): void
    {
        $dte = SiiDte::factory()->make();

        $result = $this->app
            ->make(Compile::class)
            ->through([])
            ->forDte($dte);

        static::assertInstanceOf(SiiDte::class, $result);
    }
}
