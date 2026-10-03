<?php

namespace Tests\Unit\Actions\RcvParsing;

use Laragear\Dte\Actions\RcvParsing\Parse;
use Laragear\Dte\Actions\RcvParsing\Pipes\ApplyEncodingStreamFilter;
use Laragear\Dte\Actions\RcvParsing\Pipes\NormalizeToStreamResource;
use Laragear\Dte\Actions\RcvParsing\Pipes\YieldLazyCollection;
use Laragear\Dte\Enums\RcvType;
use Laragear\MetaTesting\Pipeline\InteractsWithPipelines;
use Laragear\Rut\Rut;
use Tests\TestCase;

class ParseTest extends TestCase
{
    use InteractsWithPipelines;

    public function test_pipes_order(): void
    {
        $this->pipeline(Parse::class)
            ->assertPipes([
                NormalizeToStreamResource::class,
                ApplyEncodingStreamFilter::class,
                YieldLazyCollection::class,
            ]);
    }

    public function test_receives_batch_and_returns_context(): void
    {
        $parser = $this->app->make(Parse::class);
        $rut = Rut::parse('76111222-3');

        $context = $parser->through([])->forBatch('test', RcvType::Purchases, $rut);

        static::assertSame('test', $context->source);
        static::assertSame(RcvType::Purchases, $context->type);
        static::assertSame($rut, $context->companyRut);
    }
}
