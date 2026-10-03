<?php

namespace Tests\Unit\Actions\CompileDte\Pipes;

use Laragear\Dte\Actions\CompileDte\Compilation;
use Laragear\Dte\Actions\CompileDte\Compile;
use Laragear\Dte\Actions\CompileDte\Pipes\ApplyTedToDom;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Support\XmlDomFactory;
use Laragear\MetaTesting\Pipeline\InteractsWithPipelines;
use RuntimeException;
use Tests\DatabaseTestCase;

class ApplyTedToDomTest extends DatabaseTestCase
{
    use InteractsWithPipelines;

    /*
     |--------------------------------------------------------------------------
     | Happy paths
     |--------------------------------------------------------------------------
     */

    public function test_inserts_ted_before_timestamp(): void
    {
        $dte = SiiDte::factory()->create();

        $document = $this->app->make(XmlDomFactory::class)->document();
        $document->loadXML('<DTE><Documento><TmstFirma/></Documento></DTE>');

        $ted = $document->createElement('TED');

        $compilation = new Compilation($dte);
        $compilation->document = $document;
        $compilation->ted = $ted;

        $this
            ->pipeline(Compile::class)
            ->isolatePipe(ApplyTedToDom::class)
            ->send($compilation)
            ->assertPassable(function (Compilation $result) use ($dte) {
                $xml = $result->document->saveXML();

                return $result->dte->is($dte) && str_contains($xml, '<Documento><TED/><TmstFirma/></Documento>');
            });
    }

    /*
     |--------------------------------------------------------------------------
     | Angry paths
     |--------------------------------------------------------------------------
     */

    public function test_throws_exception_if_documento_is_missing(): void
    {
        $dte = SiiDte::factory()->create();

        $document = $this->app->make(XmlDomFactory::class)->document();
        $document->loadXML('<DTE><TmstFirma/></DTE>');

        $compilation = new Compilation($dte);
        $compilation->document = $document;
        $compilation->ted = $document->createElement('TED');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('The DTE XML document cannot receive its TED.');

        $this
            ->pipeline(Compile::class)
            ->isolatePipe(ApplyTedToDom::class)
            ->send($compilation);
    }

    public function test_throws_exception_if_timestamp_is_missing(): void
    {
        $dte = SiiDte::factory()->create();

        $document = $this->app->make(XmlDomFactory::class)->document();
        $document->loadXML('<DTE><Documento></Documento></DTE>');

        $compilation = new Compilation($dte);
        $compilation->document = $document;
        $compilation->ted = $document->createElement('TED');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('The DTE XML document cannot receive its TED.');

        $this
            ->pipeline(Compile::class)
            ->isolatePipe(ApplyTedToDom::class)
            ->send($compilation);
    }
}
