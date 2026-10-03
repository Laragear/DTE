<?php

namespace Tests\Unit\Actions\CreateEnvelope;

use Closure;
use InvalidArgumentException;
use Laragear\Dte\Actions\CreateEnvelope\Assembly;
use Laragear\Dte\Actions\CreateEnvelope\CreateEnvelope;
use Laragear\Dte\Actions\CreateEnvelope\Pipes\ApplyEnvelopeSignature;
use Laragear\Dte\Actions\CreateEnvelope\Pipes\BuildCaratulaHeader;
use Laragear\Dte\Actions\CreateEnvelope\Pipes\CanonicalizeEnvelope;
use Laragear\Dte\Actions\CreateEnvelope\Pipes\EmbedDteNodes;
use Laragear\Dte\Actions\CreateEnvelope\Pipes\InitializeEnvelope;
use Laragear\Dte\Actions\CreateEnvelope\Pipes\PersistEnvelopePayload;
use Laragear\Dte\Actions\CreateEnvelope\Pipes\ValidateEnvelopeDocuments;
use Laragear\Dte\Enums\EnvelopeStatus;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\MetaTesting\Pipeline\InteractsWithPipelines;
use Laragear\Rut\Rut;
use LogicException;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Tests\DatabaseTestCase as TestCase;
use XMLWriter;

class CreateEnvelopeTest extends TestCase
{
    use InteractsWithPipelines;

    public function test_pipes_order(): void
    {
        $this->pipeline(CreateEnvelope::class)
            ->assertPipes([
                InitializeEnvelope::class,
                ValidateEnvelopeDocuments::class,
                BuildCaratulaHeader::class,
                EmbedDteNodes::class,
                CanonicalizeEnvelope::class,
                ApplyEnvelopeSignature::class,
                PersistEnvelopePayload::class,
            ]);
    }

    public function test_receives_dte_and_returns_compilation(): void
    {
        $envelope = SiiDteEnvelope::factory()->make();

        $result = $this->app
            ->make(CreateEnvelope::class)
            ->through([])
            ->forEnvelope($envelope);

        static::assertSame($envelope, $result);
    }

    public function test_receives_dte_and_compiles_envelope_without_persisting(): void
    {
        $envelope = SiiDteEnvelope::factory()->make();

        $result = $this->app
            ->make(CreateEnvelope::class)
            ->through([])
            ->forSharing($envelope, new Rut(76123456, 0));

        static::assertSame($envelope, $result);
    }

    /*
     |--------------------------------------------------------------------------
     | Interchange Sharing Happy paths
     |--------------------------------------------------------------------------
     */

    public function test_compiles_envelope_without_persisting(): void
    {
        $envelope = SiiDteEnvelope::factory()->create();
        $receiverRut = Rut::parse('76123456-0');

        $action = $this->app->make(CreateEnvelope::class);
        $action->through([Closure::class.'@handle']);

        $this->app->bind(Closure::class.'@handle', function () {
            return function (Assembly $assembly) {
                // Assert target is set
                static::assertNotNull($assembly->targetReceiverRut);
                static::assertTrue($assembly->ephemeral);

                return $assembly;
            };
        });

        $result = $action->forSharing($envelope, $receiverRut);

        static::assertInstanceOf(SiiDteEnvelope::class, $result);
    }

    public function test_embeds_target_receiver_in_caratula(): void
    {
        $envelope = SiiDteEnvelope::factory()->create();
        $receiverRut = Rut::parse('76123456-0');

        $action = $this->app->make(CreateEnvelope::class);
        $action->through([Closure::class.'@handle']);

        $this->app->bind(Closure::class.'@handle', function () use ($receiverRut) {
            return function (Assembly $assembly, Closure $next) use ($receiverRut) {
                static::assertEquals($assembly->targetReceiverRut->formatRaw(), $receiverRut->formatRaw());

                return $assembly;
            };
        });

        $action->forSharing($envelope, $receiverRut);
    }

    /*
     |--------------------------------------------------------------------------
     | Interchange Sharing Sad paths
     |--------------------------------------------------------------------------
     */

    public function test_aborts_if_receiver_rut_invalid(): void
    {
        $envelope = SiiDteEnvelope::factory()->create();
        $receiverRut = new Rut(0, '0');

        $this->expectException(InvalidArgumentException::class);

        $action = $this->app->make(CreateEnvelope::class);

        // Let's assume validation is mocked
        $action->through([Closure::class.'@handle']);

        $this->app->bind(Closure::class.'@handle', function () {
            return function (Assembly $assembly, Closure $next) {
                if ($assembly->targetReceiverRut->num === 0) {
                    throw new InvalidArgumentException('Invalid RUT');
                }

                return $assembly;
            };
        });

        $action->forSharing($envelope, $receiverRut);
    }

    /*
     |--------------------------------------------------------------------------
     | Interchange Sharing Angry paths
     |--------------------------------------------------------------------------
     */

    public function test_throws_when_xml_writer_fails(): void
    {
        $envelope = SiiDteEnvelope::factory()->create();
        $receiverRut = Rut::parse('76123456-0');

        $action = $this->app->make(CreateEnvelope::class);
        $action->through([Closure::class.'@handle']);

        $this->app->bind(Closure::class.'@handle', function () {
            return function (Assembly $assembly) {
                // Mock writer and temporary dir existing to see cleanup
                $assembly->temporary = Mockery::mock(TemporaryDirectory::class, function (MockInterface $mock) {
                    $mock->expects('delete');
                });
                $assembly->writer = Mockery::mock(XMLWriter::class, function (MockInterface $mock) {
                    $mock->expects('flush');
                });

                throw new RuntimeException('Disk Failure');
            };
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Disk Failure');

        $action->forSharing($envelope, $receiverRut);
    }

    public function test_throws_when_envelope_is_not_pending(): void
    {
        $envelope = SiiDteEnvelope::factory()->create(['status' => EnvelopeStatus::Signed]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('Only pending DTE envelopes may be assembled.');

        $this->app->make(CreateEnvelope::class)->through([])->forEnvelope($envelope);
    }

    public function test_restores_status_when_assembly_fails(): void
    {
        $envelope = SiiDteEnvelope::factory()->create(['status' => EnvelopeStatus::Pending]);

        $action = $this->app->make(CreateEnvelope::class);
        $action->through([Closure::class.'@handle']);

        $this->app->bind(Closure::class.'@handle', function () {
            return function (Assembly $assembly) {
                throw new RuntimeException('Assembly failure.');
            };
        });

        try {
            $action->forEnvelope($envelope);

            static::fail('Expected assembly to fail.');
        } catch (RuntimeException $e) {
            static::assertSame('Assembly failure.', $e->getMessage());
        }

        static::assertSame(EnvelopeStatus::Pending, $envelope->fresh()->status);
    }
}
