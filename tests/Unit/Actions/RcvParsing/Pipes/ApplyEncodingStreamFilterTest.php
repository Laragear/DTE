<?php

namespace Tests\Unit\Actions\RcvParsing\Pipes;

use Laragear\Dte\Actions\RcvParsing\Parse;
use Laragear\Dte\Actions\RcvParsing\ParsingContext;
use Laragear\Dte\Actions\RcvParsing\Pipes\ApplyEncodingStreamFilter;
use Laragear\Dte\Enums\RcvType;
use Laragear\MetaTesting\Pipeline\InteractsWithPipelines;
use Laragear\Rut\Rut;
use Tests\TestCase;

class ApplyEncodingStreamFilterTest extends TestCase
{
    use InteractsWithPipelines;

    public function test_mitigates_mac_accents_mapping_utf8_effectively(): void
    {
        $context = new ParsingContext('source', RcvType::Sales, new Rut('11111111', '1'));
        $context->stream = tmpfile();

        $isoString = mb_convert_encoding('COMERCIALIZACIÓN', 'ISO-8859-1', 'UTF-8');

        fwrite($context->stream, $isoString);
        rewind($context->stream);

        $this
            ->pipeline(Parse::class)
            ->isolatePipe(ApplyEncodingStreamFilter::class)
            ->send($context);

        $contents = stream_get_contents($context->stream);
        static::assertSame('COMERCIALIZACIÓN', $contents);
    }
}
