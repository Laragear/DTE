<?php

namespace Tests\Unit\Actions\CompileDte\Pipes;

use Laragear\Dte\Actions\CompileDte\Compilation;
use Laragear\Dte\Actions\CompileDte\Compile;
use Laragear\Dte\Actions\CompileDte\Pipes\AcquireFolio;
use Laragear\Dte\Caf\CafManager;
use Laragear\Dte\Caf\Exceptions\DepletionException;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Models\SiiCaf;
use Laragear\Dte\Models\SiiDte;
use Laragear\MetaTesting\Pipeline\InteractsWithPipelines;
use Tests\DatabaseTestCase;

class AcquireFolioTest extends DatabaseTestCase
{
    use InteractsWithPipelines;

    /*
     |--------------------------------------------------------------------------
     | Happy paths
     |--------------------------------------------------------------------------
     */

    public function test_skips_if_folio_and_caf_are_already_present(): void
    {
        $caf = SiiCaf::factory()->has(SiiDte::factory([
            'folio' => 123,
        ]), 'dtes')->create();

        $this->mock(CafManager::class)->expects('allocate')->never();

        $this
            ->pipeline(Compile::class)
            ->isolatePipe(AcquireFolio::class)
            ->send(new Compilation($caf->dtes->first()))
            ->assertPassable(function (Compilation $result) use ($caf) {
                return $result->dte->is($caf->dtes->first());
            });
    }

    public function test_allocates_folio_and_saves_to_dte(): void
    {
        $dte = SiiDte::factory()->create([
            'folio' => null,
            'sii_caf_id' => null,
        ]);

        $caf = SiiCaf::factory()->create();

        $this
            ->mock(CafManager::class)
            ->expects('allocate')
            ->withArgs(function ($rut, $type, $callback) use ($dte, $caf) {
                if ((string) $rut !== (string) $dte->issuer_rut || $type !== $dte->document_type) {
                    return false;
                }

                $callback($caf, 456);

                return true;
            });

        $this
            ->pipeline(Compile::class)
            ->isolatePipe(AcquireFolio::class)
            ->send(new Compilation($dte))
            ->assertPassable(function (Compilation $result) use ($dte, $caf) {
                return
                    $result->dte->is($dte)
                    && $result->dte->folio === 456
                    && $result->dte->caf()->getParentKey() === $caf->id
                    && $result->dte->relationLoaded('caf')
                    && $result->dte->caf->is($caf);
            });

        $this->assertDatabaseHas(SiiDte::class, [
            'id' => $dte->id,
            'folio' => 456,
            'sii_caf_id' => $caf->id,
        ]);
    }

    public function test_transitions_to_requires_caf_on_depletion(): void
    {
        $dte = SiiDte::factory()->create([
            'status' => DteStatus::Building,
            'folio' => null,
            'sii_caf_id' => null,
        ]);

        $this->mock(CafManager::class)->expects('allocate')->andThrow(new DepletionException('No folios'));

        $this
            ->pipeline(Compile::class)
            ->isolatePipe(AcquireFolio::class)
            ->send(new Compilation($dte))
            ->assertPassable(function (Compilation $result) use ($dte) {
                return $result->dte->is($dte) && $result->dte->status === DteStatus::RequiresCaf;
            });

        $this->assertDatabaseHas('sii_dtes', [
            'id' => $dte->id,
            'status' => DteStatus::RequiresCaf->value,
        ]);
    }
}
