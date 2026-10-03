<?php

namespace Tests\Unit\Console\Commands;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\DateFactory;
use Illuminate\Support\Facades\Http;
use Laragear\Dte\Actions\CreateEnvelope\CreateEnvelope;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Dte\Console\Commands\ProcessAndSendEnvelopeCommand;
use Laragear\Dte\Contracts\CertificateResolverInterface;
use Laragear\Dte\Enums\DteEnvironment;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\EnvelopeStatus;
use Laragear\Dte\Environment\EnvironmentResolver;
use Laragear\Dte\Gateways\UploadGateway;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Models\SiiDteEnvelopePayload;
use Laragear\Dte\Xml\XmlSigner;
use LogicException;
use Mockery\MockInterface;
use Tests\DatabaseTestCase;

class ProcessAndSendEnvelopeCommandTest extends DatabaseTestCase
{
    /*
     |--------------------------------------------------------------------------
     | Happy paths
     |--------------------------------------------------------------------------
     */

    public function test_processes_and_sends_envelope(): void
    {
        $now = $this->freezeSecond();

        $this->config('dte.environment', DteEnvironment::Local->value);
        $this->app->make(EnvironmentResolver::class)->flush();

        $envelope = SiiDteEnvelope::factory()
            ->has(SiiDte::factory()->state(['status' => DteStatus::Packed]), 'dtes')
            ->create();
        $otherDocument = SiiDte::factory()->create(['sii_dte_envelope_id' => 999]);

        $this->mock(CreateEnvelope::class, function (MockInterface $mock) use ($envelope): void {
            $mock
                ->expects('forEnvelope')
                ->withArgs(function (SiiDteEnvelope $sent) use ($envelope): bool {
                    return $sent->is($envelope);
                })
                ->andReturnUsing(function () use ($envelope) {
                    $payload = SiiDteEnvelopePayload::factory()->make(['xml' => 'signed-xml']);
                    $envelope->setRelation('payload', $payload);

                    return $envelope;
                });
        });

        $this
            ->artisan('dte:process-envelope', ['envelope_id' => $envelope->getKey()])
            ->expectsOutput(
                "Envelope [{$envelope->getKey()}] processed and sent with Track ID: fake-track-id-{$envelope->getKey()}",
            )
            ->assertSuccessful();

        $this->assertDatabaseHas('sii_dte_envelopes', [
            'id' => $envelope->getKey(),
            'track_id' => "fake-track-id-{$envelope->getKey()}",
            'updated_at' => $now,
        ]);

        $this->assertDatabaseHas('sii_dtes', [
            'id' => $envelope->dtes()->value('id'),
            'status' => DteStatus::Sent->value,
            'updated_at' => $now,
        ]);

        $this->assertDatabaseHas('sii_dtes', [
            'id' => $otherDocument->getKey(),
            'status' => DteStatus::Draft->value,
        ]);
    }

    public function test_processes_and_sends_boleta_envelope(): void
    {
        $now = $this->freezeSecond();

        $this->config([
            'dte.environment' => 'production',
            'app.env' => 'production',
        ]);

        $this->app['env'] = 'production';
        $this->app->make(EnvironmentResolver::class)->flush();

        $envelope = SiiDteEnvelope::factory()->create(['type' => 'boleta']);

        Http::fake([
            'https://api.sii.cl/recursos/v1/boleta.electronica.semilla' => Http::response(
                '<RESPUESTA><RESP_BODY><SEMILLA>030530912644</SEMILLA></RESP_BODY></RESPUESTA>',
                200,
            ),
            'https://api.sii.cl/recursos/v1/boleta.electronica.token' => Http::response(
                '<RESPUESTATOKEN><TOKEN>P7VQKYLDNHJGP</TOKEN></RESPUESTATOKEN>',
                200,
            ),
            'https://rahue.sii.cl/recursos/v1/boleta.electronica.envio' => Http::response(
                ['trackid' => 'boleta-track-id', 'estado' => 'REC', 'codigo' => 0],
                200,
            ),
        ]);

        $this
            ->mock(CertificateResolverInterface::class)
            ->expects('resolve')
            ->andReturn(new DigitalCertificate('fake', 'fake'));

        $this
            ->mock(XmlSigner::class)
            ->expects('sign')
            ->andReturnUsing(function ($target) {
                return $target;
            });

        $this->mock(CreateEnvelope::class)
            ->expects('forEnvelope')
            ->withArgs(fn($s) => $s->is($envelope))
            ->andReturnUsing(function () use ($envelope) {
                $payload = SiiDteEnvelopePayload::factory()->make(['xml' => 'signed-xml']);
                $envelope->setRelation('payload', $payload);

                return $envelope;
            });

        $this
            ->artisan('dte:process-envelope', ['envelope_id' => $envelope->getKey()])
            ->expectsOutput("Envelope [{$envelope->getKey()}] processed and sent with Track ID: boleta-track-id")
            ->assertSuccessful();

        $this->assertDatabaseHas('sii_dte_envelopes', [
            'id' => $envelope->getKey(),
            'track_id' => 'boleta-track-id',
            'uploaded_at' => $now,
        ]);
    }

    /*
     |--------------------------------------------------------------------------
     | Sad paths
     |--------------------------------------------------------------------------
     */

    public function test_sends_signed_envelope_without_rebuilding(): void
    {
        $this->config('dte.environment', DteEnvironment::Local->value);
        $this->app->make(EnvironmentResolver::class)->flush();

        $envelope = SiiDteEnvelope::factory()
            ->has(SiiDte::factory()->state(['status' => DteStatus::Packed]), 'dtes')
            ->has(SiiDteEnvelopePayload::factory()->state(['xml' => 'signed-xml']), 'payload')
            ->create(['status' => EnvelopeStatus::Signed]);

        $this->mock(CreateEnvelope::class, function (MockInterface $mock): void {
            $mock->expects('forEnvelope')->never();
        });

        $this
            ->artisan('dte:process-envelope', ['envelope_id' => $envelope->getKey()])
            ->expectsOutput(
                "Envelope [{$envelope->getKey()}] processed and sent with Track ID: fake-track-id-{$envelope->getKey()}",
            )
            ->assertSuccessful();

        $this->assertDatabaseHas('sii_dte_envelopes', [
            'id' => $envelope->getKey(),
            'track_id' => "fake-track-id-{$envelope->getKey()}",
            'status' => EnvelopeStatus::Uploaded->value,
        ]);

        $this->assertDatabaseHas('sii_dtes', [
            'id' => $envelope->dtes()->value('id'),
            'status' => DteStatus::Sent->value,
        ]);
    }

    public function test_rebuilds_signed_envelope_without_payload(): void
    {
        $this->config('dte.environment', DteEnvironment::Local->value);
        $this->app->make(EnvironmentResolver::class)->flush();

        $envelope = SiiDteEnvelope::factory()
            ->has(SiiDte::factory()->state(['status' => DteStatus::Packed]), 'dtes')
            ->create(['status' => EnvelopeStatus::Signed]);

        $this->mock(CreateEnvelope::class, function (MockInterface $mock) use ($envelope): void {
            $mock
                ->expects('forEnvelope')
                ->withArgs(function (SiiDteEnvelope $sent) use ($envelope): bool {
                    return $sent->is($envelope);
                })
                ->andReturnUsing(function () use ($envelope) {
                    $payload = SiiDteEnvelopePayload::factory()->make(['xml' => 'signed-xml']);
                    $envelope->setRelation('payload', $payload);

                    return $envelope;
                });
        });

        $this
            ->artisan('dte:process-envelope', ['envelope_id' => $envelope->getKey()])
            ->assertSuccessful();

        $this->assertDatabaseHas('sii_dte_envelopes', [
            'id' => $envelope->getKey(),
            'track_id' => "fake-track-id-{$envelope->getKey()}",
        ]);
    }

    public function test_fails_when_envelope_does_not_exist(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessageIs('No query results for model [Laragear\Dte\Models\SiiDteEnvelope] 999');

        $this->artisan('dte:process-envelope', ['envelope_id' => 999]);
    }

    public function test_throws_when_envelope_already_claimed(): void
    {
        $envelope = SiiDteEnvelope::factory()->create(['status' => EnvelopeStatus::Uploaded]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('cannot be processed twice.');

        $this->artisan('dte:process-envelope', ['envelope_id' => $envelope->getKey()]);
    }

    public function test_reclaims_stale_assembling_envelopes(): void
    {
        $date = $this->app->make(DateFactory::class);

        $stale = SiiDteEnvelope::factory()->create(['status' => EnvelopeStatus::Assembling]);
        SiiDteEnvelope::query()->whereKey($stale->getKey())->update(['updated_at' => now()->subHour()]);

        $fresh = SiiDteEnvelope::factory()->create(['status' => EnvelopeStatus::Assembling]);

        $reclaimed = $this->app->make(ProcessAndSendEnvelopeCommand::class)->reclaimStaleEnvelopes($date);

        static::assertSame(1, $reclaimed);
        static::assertSame(EnvelopeStatus::Pending, $stale->fresh()->status);
        static::assertSame(EnvelopeStatus::Assembling, $fresh->fresh()->status);
    }

    public function test_reclaims_stale_sending_envelopes(): void
    {
        $date = $this->app->make(DateFactory::class);

        $stale = SiiDteEnvelope::factory()->create(['status' => EnvelopeStatus::Sending]);
        SiiDteEnvelope::query()->whereKey($stale->getKey())->update(['updated_at' => now()->subHour()]);

        $fresh = SiiDteEnvelope::factory()->create(['status' => EnvelopeStatus::Sending]);

        $reclaimed = $this->app->make(ProcessAndSendEnvelopeCommand::class)->reclaimStaleEnvelopes($date);

        static::assertSame(1, $reclaimed);
        static::assertSame(EnvelopeStatus::Pending, $stale->fresh()->status);
        static::assertSame(EnvelopeStatus::Sending, $fresh->fresh()->status);
    }

    public function test_reclaims_stale_envelopes_before_processing(): void
    {
        $this->config('dte.environment', DteEnvironment::Local->value);
        $this->app->make(EnvironmentResolver::class)->flush();

        $stale = SiiDteEnvelope::factory()->create(['status' => EnvelopeStatus::Sending]);
        SiiDteEnvelope::query()->whereKey($stale->getKey())->update(['updated_at' => now()->subHour()]);

        $envelope = SiiDteEnvelope::factory()
            ->has(SiiDte::factory()->state(['status' => DteStatus::Packed]), 'dtes')
            ->create();

        $this->mock(CreateEnvelope::class, function (MockInterface $mock) use ($envelope): void {
            $mock->expects('forEnvelope')->andReturnUsing(function () use ($envelope) {
                $payload = SiiDteEnvelopePayload::factory()->make(['xml' => 'signed-xml']);
                $envelope->setRelation('payload', $payload);

                return $envelope;
            });
        });

        $this
            ->artisan('dte:process-envelope', [
                'envelope_id' => $envelope->getKey(),
                '--reclaim-stale' => true,
            ])
            ->assertSuccessful();

        static::assertSame(EnvelopeStatus::Pending, $stale->fresh()->status);
        static::assertSame(EnvelopeStatus::Uploaded, $envelope->fresh()->status);
    }

    public function test_marks_envelope_as_failed_when_upload_fails(): void
    {
        $envelope = SiiDteEnvelope::factory()
            ->has(SiiDte::factory()->state(['status' => DteStatus::Packed]), 'dtes')
            ->create();

        // Captured upfront: the failure path detaches the DTE, so the relation is empty after.
        $dteId = $envelope->dtes()->value('id');

        $this->mock(CreateEnvelope::class, function (MockInterface $mock) use ($envelope): void {
            $mock->expects('forEnvelope')->andReturnUsing(function () use ($envelope) {
                $payload = SiiDteEnvelopePayload::factory()->make(['xml' => 'signed-xml']);
                $envelope->setRelation('payload', $payload);

                return $envelope;
            });
        });

        $this->mock(UploadGateway::class, function (MockInterface $mock): void {
            $mock->expects('upload')->andThrow(new LogicException('SII unreachable.'));
        });

        try {
            $this->artisan('dte:process-envelope', ['envelope_id' => $envelope->getKey()]);
            static::fail('The upload exception was not thrown.');
        } catch (LogicException $e) {
            static::assertSame('SII unreachable.', $e->getMessage());
        }

        static::assertSame(EnvelopeStatus::Failed, $envelope->fresh()->status);

        $dte = SiiDte::query()->findOrFail($dteId);

        // The failure path detaches the DTEs so they can repack into a new envelope.
        static::assertNull($dte->sii_dte_envelope_id);
        static::assertSame(DteStatus::Outbox, $dte->status);
    }
}
