<?php

namespace Tests\Unit\Actions\Cuadratura;

use Laragear\Dte\Actions\Cuadratura\Pipes\DowngradeOrphanedDocuments;
use Laragear\Dte\Actions\Cuadratura\Pipes\ReconcileRcvStream;
use Laragear\Dte\Actions\Cuadratura\Sync;
use Laragear\Dte\Actions\RcvParsing\ParsingContext;
use Laragear\Dte\Enums\RcvType;
use Laragear\MetaTesting\Pipeline\InteractsWithPipelines;
use Laragear\Rut\Rut;
use Tests\TestCase;

class SyncTest extends TestCase
{
    use InteractsWithPipelines;

    public function test_pipes_order(): void
    {
        $this->pipeline(Sync::class)
            ->assertPipes([
                ReconcileRcvStream::class,
                DowngradeOrphanedDocuments::class,
            ]);
    }

    public function test_receives_parsing_context_and_returns_metrics(): void
    {
        $parsingContext = new ParsingContext('fake', RcvType::Purchases, Rut::parse('76111222-3'));

        $sync = $this->app->make(Sync::class);

        $metrics = $sync->through([])->forParsing($parsingContext);

        static::assertSame(
            [
                'matched' => 0,
                'phantoms' => 0,
                'discrepancies' => 0,
                'orphans' => 0,
            ],
            $metrics,
        );
    }
}
