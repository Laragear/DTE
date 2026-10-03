<?php

namespace Tests\Unit\Actions\CreateEnvelope\Pipes;

use Laragear\Dte\Actions\CreateEnvelope\Assembly;
use Laragear\Dte\Actions\CreateEnvelope\CreateEnvelope;
use Laragear\Dte\Actions\CreateEnvelope\Pipes\CanonicalizeEnvelope;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Support\XmlDomFactory;
use Laragear\MetaTesting\Pipeline\InteractsWithPipelines;
use RuntimeException;
use Tests\DatabaseTestCase;

class CanonicalizeEnvelopeTest extends DatabaseTestCase
{
    use InteractsWithPipelines;

    /*
     |--------------------------------------------------------------------------
     | Happy paths
     |--------------------------------------------------------------------------
     */

    public function test_canonicalize_envelope_reads_and_parses_xml(): void
    {
        $envelope = SiiDteEnvelope::factory()->create();
        $assembly = new Assembly($envelope);

        $path = tempnam(sys_get_temp_dir(), 'dte_');
        $this->app->make('files')->put($path, '<EnvioDTE><SetDTE></SetDTE></EnvioDTE>');
        $assembly->path = $path;

        $dom = $this->app->make(XmlDomFactory::class)->document();

        $xmlDomFactory = $this->mock(XmlDomFactory::class);
        $xmlDomFactory
            ->expects('document')
            ->with('1.0', 'ISO-8859-1')
            ->andReturn($dom);

        try {
            $this
                ->pipeline(CreateEnvelope::class)
                ->isolatePipe(CanonicalizeEnvelope::class)
                ->send($assembly)
                ->assertPassable(function (Assembly $result) use ($dom) {
                    static::assertSame($dom, $result->document);
                    static::assertEquals('ISO-8859-1', $result->document->encoding);

                    return true;
                });
        } finally {
            unlink($path);
        }
    }

    /*
     |--------------------------------------------------------------------------
     | Sad paths
     |--------------------------------------------------------------------------
     */

    public function test_throws_if_cannot_read_xml(): void
    {
        $envelope = SiiDteEnvelope::factory()->create();
        $assembly = new Assembly($envelope);
        $assembly->path = '/non/existent/path/file.xml';

        $this->mock(XmlDomFactory::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Unable to read the temporary envelope XML.');

        @$this
            ->pipeline(CreateEnvelope::class)
            ->isolatePipe(CanonicalizeEnvelope::class)
            ->send($assembly);
    }

    /*
     |--------------------------------------------------------------------------
     | Angry paths
     |--------------------------------------------------------------------------
     */

    public function test_throws_if_invalid_xml_parsed(): void
    {
        $envelope = SiiDteEnvelope::factory()->create();
        $assembly = new Assembly($envelope);

        $path = tempnam(sys_get_temp_dir(), 'dte_');
        file_put_contents($path, 'bad-xml');
        $assembly->path = $path;

        $dom = $this->app->make(XmlDomFactory::class)->document();

        $xmlDomFactory = $this->mock(XmlDomFactory::class);
        $xmlDomFactory->expects('document')->andReturn($dom);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Unable to parse the envelope XML.');

        @$this
            ->pipeline(CreateEnvelope::class)
            ->isolatePipe(CanonicalizeEnvelope::class)
            ->send($assembly); // Supress DOMDocument warning

        unlink($path);
    }
}
