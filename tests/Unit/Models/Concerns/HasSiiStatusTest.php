<?php

namespace Tests\Unit\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\EnvelopeStatus;
use Laragear\Dte\Models\Concerns\HasSiiStatus;
use LogicException;
use Tests\TestCase;
use Tests\Unit\Models\Concerns\Fixtures\DummySiiStatusModel;

class HasSiiStatusTest extends TestCase
{
    /*
     |--------------------------------------------------------------------------
     | Happy Paths
     |--------------------------------------------------------------------------
     */

    public function test_scopes_pending_records(): void
    {
        static::assertSame(
            'select * from "dummy_sii_status_models" where "status" = \'pending\'',
            DummySiiStatusModel::query()->pending()->toRawSql(),
        );
    }

    public function test_scopes_accepted_records(): void
    {
        static::assertSame(
            'select * from "dummy_sii_status_models" where "status" = \'accepted\'',
            DummySiiStatusModel::query()->accepted()->toRawSql(),
        );
    }

    public function test_transitions_to_another_status_and_saves(): void
    {
        $model = new DummySiiStatusModel;

        static::assertSame($model, $model->transitionTo(DteStatus::Building));
        static::assertSame(DteStatus::Building, $model->status);
        static::assertTrue($model->saved);
    }

    public function test_transitions_without_saving(): void
    {
        $model = new DummySiiStatusModel;

        $model->transitionTo(DteStatus::Building, false);

        static::assertFalse($model->saved);
    }

    public function test_allows_an_idempotent_terminal_transition(): void
    {
        $model = (new DummySiiStatusModel)->transitionTo(DteStatus::Accepted, false);

        static::assertSame($model, $model->transitionTo(DteStatus::Accepted, false));
        static::assertSame(DteStatus::Accepted, $model->status);
    }

    /*
     |--------------------------------------------------------------------------
     | Sad Paths
     |--------------------------------------------------------------------------
     */

    public function test_throws_when_status_enum_is_incompatible(): void
    {
        $model = new DummySiiStatusModel;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The next status must belong to the same SII status enum.');

        $model->transitionTo(EnvelopeStatus::Accepted, false);
    }

    public function test_throws_when_transitioning_from_a_terminal_status(): void
    {
        $model = (new DummySiiStatusModel)->transitionTo(DteStatus::Accepted, false);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('A terminal SII status cannot transition to another state.');

        $model->transitionTo(DteStatus::Rejected, false);
    }

    /*
     |--------------------------------------------------------------------------
     | Angry Paths
     |--------------------------------------------------------------------------
     */

    public function test_throws_when_status_is_not_an_enum(): void
    {
        $model = new class extends Model {
            use HasSiiStatus;
        };
        $model->status = 'pending';

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The model status must be cast to a supported SII status enum.');

        $model->transitionTo(DteStatus::Accepted, false);
    }
}
