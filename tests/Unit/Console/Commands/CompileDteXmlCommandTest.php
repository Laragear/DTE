<?php

namespace Tests\Unit\Console\Commands;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laragear\Dte\Actions\CompileDte\Compile;
use Laragear\Dte\Models\SiiDte;
use Tests\DatabaseTestCase;

class CompileDteXmlCommandTest extends DatabaseTestCase
{
    public function test_compiles_dte(): void
    {
        $dte = SiiDte::factory()->create();

        $this
            ->mock(Compile::class)
            ->expects('forDte')
            ->withArgs(function (SiiDte $sent) use ($dte): bool {
                return $sent->is($dte);
            })
            ->andReturn($dte);

        $this
            ->artisan('dte:compile', ['dte_id' => $dte->getKey()])
            ->expectsOutput("DTE [{$dte->getKey()}] compiled successfully.")
            ->assertSuccessful();
    }

    /*
     |--------------------------------------------------------------------------
     | Sad paths
     |--------------------------------------------------------------------------
     */

    public function test_fails_when_dte_does_not_exist(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessageIs('No query results for model [Laragear\Dte\Models\SiiDte] 999');

        $this->artisan('dte:compile', ['dte_id' => 999]);
    }
}
