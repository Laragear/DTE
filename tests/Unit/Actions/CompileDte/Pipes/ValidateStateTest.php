<?php

namespace Tests\Unit\Actions\CompileDte\Pipes;

use Laragear\Dte\Actions\CompileDte\Compilation;
use Laragear\Dte\Actions\CompileDte\Compile;
use Laragear\Dte\Actions\CompileDte\Pipes\ValidateState;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Models\SiiDte;
use Laragear\MetaTesting\Pipeline\InteractsWithPipelines;
use LogicException;
use Tests\DatabaseTestCase;

class ValidateStateTest extends DatabaseTestCase
{
    use InteractsWithPipelines;

    /*
     |--------------------------------------------------------------------------
     | Happy Paths
     |--------------------------------------------------------------------------
     */

    public function test_transitions_pending_to_building(): void
    {
        $dte = SiiDte::factory()->create(['status' => DteStatus::Pending]);

        $compilation = new Compilation($dte);

        $this
            ->pipeline(Compile::class)
            ->isolatePipe(ValidateState::class)
            ->send($compilation)
            ->assertPassable(function (Compilation $result) use ($dte) {
                return $result->dte->is($dte) && $result->dte->status === DteStatus::Building;
            });

        $this->assertDatabaseHas('sii_dtes', [
            'id' => $dte->id,
            'status' => DteStatus::Building->value,
        ]);
    }

    public function test_allows_already_building_dte(): void
    {
        $dte = SiiDte::factory()->create(['status' => DteStatus::Building]);

        $compilation = new Compilation($dte);

        $this
            ->pipeline(Compile::class)
            ->isolatePipe(ValidateState::class)
            ->send($compilation)
            ->assertPassable(function (Compilation $result) use ($dte) {
                return $result->dte->is($dte);
            });
    }

    /*
     |--------------------------------------------------------------------------
     | Sad Paths
     |--------------------------------------------------------------------------
     */

    public function test_fails_if_already_being_processed_concurrently(): void
    {
        $dte = SiiDte::factory()->create(['status' => DteStatus::Pending]);

        $compilation = new Compilation($dte);

        // Simulate another process changed it first
        SiiDte::whereKey($dte->getKey())->update(['status' => DteStatus::Building]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The DTE document is already being processed.');

        $this
            ->pipeline(Compile::class)
            ->isolatePipe(ValidateState::class)
            ->send($compilation);
    }

    public function test_fails_if_status_is_not_pending_or_building(): void
    {
        $dte = SiiDte::factory()->create(['status' => DteStatus::Sent]);
        $compilation = new Compilation($dte);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('Only pending or building DTE documents may be compiled.');

        $this
            ->pipeline(Compile::class)
            ->isolatePipe(ValidateState::class)
            ->send($compilation);
    }
}
