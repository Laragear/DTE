<?php

namespace Tests\Unit\Actions\CompileDte\Pipes;

use Illuminate\Support\Facades\Event;
use Laragear\Dte\Actions\CompileDte\Compilation;
use Laragear\Dte\Actions\CompileDte\Compile;
use Laragear\Dte\Actions\CompileDte\Pipes\FireDteCompiledEvent;
use Laragear\Dte\Events\DteCompiled;
use Laragear\Dte\Models\SiiDte;
use Laragear\MetaTesting\Pipeline\InteractsWithPipelines;
use Tests\TestCase;

class FireDteCompiledEventTest extends TestCase
{
    use InteractsWithPipelines;

    public function test_fires_event(): void
    {
        $event = Event::fake();

        $dte = new SiiDte;
        $compilation = new Compilation($dte);

        $this
            ->pipeline(Compile::class)
            ->isolatePipe(FireDteCompiledEvent::class)
            ->send($compilation);

        $event->assertDispatched(DteCompiled::class, static function (DteCompiled $event) use ($compilation): bool {
            static::assertSame($compilation->dte, $event->dte);

            return true;
        });
    }
}
