<?php

namespace Tests\Unit\Actions\CreateEnvelope\Pipes;

use Laragear\Dte\Actions\CreateEnvelope\Assembly;
use Laragear\Dte\Actions\CreateEnvelope\CreateEnvelope;
use Laragear\Dte\Actions\CreateEnvelope\Pipes\InitializeEnvelope;
use Laragear\Dte\Enums\EnvelopeStatus;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\MetaTesting\Pipeline\InteractsWithPipelines;
use LogicException;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Tests\DatabaseTestCase;

class InitializeEnvelopeTest extends DatabaseTestCase
{
    use InteractsWithPipelines;

    /*
    |--------------------------------------------------------------------------
    | Happy paths
    |--------------------------------------------------------------------------
    */

    public function test_initializes_envelope_and_creates_temp_dir(): void
    {
        $envelope = SiiDteEnvelope::factory()->create([
            'status' => EnvelopeStatus::Pending,
        ]);
        $assembly = new Assembly($envelope);

        $mockTemp = $this->mock(TemporaryDirectory::class);
        $mockTemp->expects('create')->andReturnSelf();
        $mockTemp->expects('path')->with('envelope.xml')->andReturn('/tmp/fake/envelope.xml');

        $this->pipeline(CreateEnvelope::class)
            ->isolatePipe(InitializeEnvelope::class)
            ->send($assembly)
            ->assertPassable(function (Assembly $result) use ($envelope, $mockTemp) {
                static::assertTrue($result->envelope->is($envelope));
                static::assertEquals(EnvelopeStatus::Assembling, $result->envelope->status);
                static::assertSame($mockTemp, $result->temporary);
                static::assertEquals('/tmp/fake/envelope.xml', $result->path);

                return true;
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Sad paths
    |--------------------------------------------------------------------------
    */

    public function test_accepts_already_claimed_assembling_envelope(): void
    {
        $envelope = SiiDteEnvelope::factory()->create([
            'status' => EnvelopeStatus::Assembling,
        ]);
        $assembly = new Assembly($envelope);

        $mockTemp = $this->mock(TemporaryDirectory::class);
        $mockTemp->expects('create')->andReturnSelf();
        $mockTemp->expects('path')->with('envelope.xml')->andReturn('/tmp/fake/envelope.xml');

        $this->pipeline(CreateEnvelope::class)
            ->isolatePipe(InitializeEnvelope::class)
            ->send($assembly)
            ->assertPassable(function (Assembly $result): bool {
                static::assertEquals(EnvelopeStatus::Assembling, $result->envelope->status);

                return true;
            });
    }

    public function test_throws_when_envelope_is_not_pending(): void
    {
        $envelope = SiiDteEnvelope::factory()->create([
            'status' => EnvelopeStatus::Signed,
        ]);

        $assembly = new Assembly($envelope);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('Only pending DTE envelopes may be assembled.');

        $this->pipeline(CreateEnvelope::class)
            ->isolatePipe(InitializeEnvelope::class)
            ->send($assembly);
    }
}
