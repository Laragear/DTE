<?php

namespace Tests\Unit\Jobs;

use DOMElement;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Dte\Contracts\CertificateResolverInterface;
use Laragear\Dte\Data\Token;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\EnvelopeStatus;
use Laragear\Dte\Environment\EnvironmentResolver;
use Laragear\Dte\Events\DteAccepted;
use Laragear\Dte\Events\DteRejected;
use Laragear\Dte\Events\EnvelopeAccepted;
use Laragear\Dte\Events\EnvelopeRejected;
use Laragear\Dte\Gateways\Exceptions\TokenInvalidException;
use Laragear\Dte\Gateways\SoapGateway;
use Laragear\Dte\Jobs\PollDteStatusJob;
use Laragear\Dte\Jobs\PollEnvelopeTrackIdJob;
use Laragear\Dte\Jobs\SendInterchangeEnvelopeJob;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Models\SiiDteEnvelopePayload;
use Laragear\Dte\Support\TokenAuthenticator;
use Laragear\Dte\Xml\XmlSigner;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use Tests\DatabaseTestCase;
use Tests\Unit\Certificate\Fixtures\CertificateFixture;

class PollEnvelopeTrackIdJobTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['env'] = 'production';
        $this->config('app.env', 'production');
        $this->config('dte.environment', 'production');
        $this->app->make(EnvironmentResolver::class)->flush();
    }

    public function test_accepts_envelope_when_epr_and_dispatches_event(): void
    {
        $event = Event::fake([EnvelopeAccepted::class, EnvelopeRejected::class, DteRejected::class]);

        $envelope = SiiDteEnvelope::factory()->hasPayload(['sii_response' => null])->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '12345',
        ]);

        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock) use ($envelope): void {
            $mock->expects('token')
                ->withArgs(fn($rut) => $rut->num === $envelope->issuer_rut->num)
                ->zeroOrMoreTimes()
                ->andReturn(new Token('FAKE_TOKEN', time() + 3600));
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(fn($request, $issuer) => $request());
        });

        $this->mock(SoapGateway::class, static function (MockInterface $mock): void {
            $mock->expects('query')
                ->withArgs(fn($token, $service, $action, $args) => $args['TrackId'] === '12345')
                ->andReturn('<ESTADO>EPR</ESTADO>');
        });

        $job = new PollEnvelopeTrackIdJob($envelope);
        $this->app->call($job->handle(...));

        static::assertEquals(EnvelopeStatus::Accepted, $envelope->fresh()->status);
        static::assertNotNull($envelope->fresh()->accepted_at);

        $event->assertDispatched(EnvelopeAccepted::class, function ($event) use ($envelope) {
            return $event->envelope->id === $envelope->id;
        });

        $event->assertNotDispatched(EnvelopeRejected::class);
        $event->assertNotDispatched(DteRejected::class);
    }

    public function test_accepts_boleta_envelope_when_epr_and_dispatches_event(): void
    {
        $event = Event::fake([EnvelopeAccepted::class, EnvelopeRejected::class, DteRejected::class]);

        $envelope = SiiDteEnvelope::factory()->hasPayload(['sii_response' => null])->create([
            'type' => 'boleta',
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '12345',
            'type' => 'boleta',
        ]);

        Http::fake([
            '*/boleta.electronica.semilla' => Http::response('<RESP_BODY><SEMILLA>123</SEMILLA></RESP_BODY>',
                200, ['Content-Type' => 'application/xml']),
            '*/boleta.electronica.token' => Http::response('<RESP_BODY><TOKEN>FAKE_TOKEN</TOKEN></RESP_BODY>',
                200, ['Content-Type' => 'application/xml']),
            '*/boleta.electronica.envio/*' => Http::response(['estado' => 'EPR'], 200),
        ]);

        $this->mock(CertificateResolverInterface::class, function ($mock) {
            $fixture = CertificateFixture::create();
            $cert = new DigitalCertificate(file_get_contents($fixture->path),
                $fixture->password);
            $mock->expects('resolve')->andReturn($cert);
        });

        $job = new PollEnvelopeTrackIdJob($envelope);
        $this->app->call($job->handle(...));

        static::assertEquals(EnvelopeStatus::Accepted, $envelope->fresh()->status);
        static::assertNotNull($envelope->fresh()->accepted_at);
        $event->assertDispatched(EnvelopeAccepted::class);
    }

    public function test_rejects_boleta_envelope_when_rejected_status_and_dispatches_events(): void
    {
        $event = Event::fake([EnvelopeAccepted::class, EnvelopeRejected::class, DteRejected::class]);

        $envelope = SiiDteEnvelope::factory()->hasPayload(['sii_response' => null])->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '12345',
            'type' => 'boleta',
        ]);

        Http::fake([
            '*/boleta.electronica.semilla' => Http::response('<RESP_BODY><SEMILLA>123</SEMILLA></RESP_BODY>',
                200, ['Content-Type' => 'application/xml']),
            '*/boleta.electronica.token' => Http::response('<RESP_BODY><TOKEN>FAKE_TOKEN</TOKEN></RESP_BODY>',
                200, ['Content-Type' => 'application/xml']),
            '*/boleta.electronica.envio/*' => Http::response(['estado' => 'RCH'], 200),
        ]);

        $this->mock(CertificateResolverInterface::class, function ($mock) {
            $fixture = CertificateFixture::create();
            $cert = new DigitalCertificate(file_get_contents($fixture->path),
                $fixture->password);
            $mock->expects('resolve')->andReturn($cert);
        });

        $job = new PollEnvelopeTrackIdJob($envelope);
        $this->app->call($job->handle(...));

        static::assertEquals(EnvelopeStatus::Rejected, $envelope->fresh()->status);
        static::assertNotNull($envelope->fresh()->rejected_at);
        $event->assertDispatched(EnvelopeRejected::class);
    }

    public function test_keeps_boleta_envelope_uploaded_when_processing(): void
    {
        $event = Event::fake([EnvelopeAccepted::class, EnvelopeRejected::class, DteRejected::class]);

        $envelope = SiiDteEnvelope::factory()->hasPayload(['sii_response' => null])->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '12345',
            'type' => 'boleta',
        ]);

        Http::fake([
            '*/boleta.electronica.semilla' => Http::response('<RESP_BODY><SEMILLA>123</SEMILLA></RESP_BODY>',
                200, ['Content-Type' => 'application/xml']),
            '*/boleta.electronica.token' => Http::response('<RESP_BODY><TOKEN>FAKE_TOKEN</TOKEN></RESP_BODY>',
                200, ['Content-Type' => 'application/xml']),
            '*/boleta.electronica.envio/*' => Http::response(['estado' => 'PRD'], 200),
        ]);

        $this->mock(CertificateResolverInterface::class, function ($mock) {
            $fixture = CertificateFixture::create();
            $cert = new DigitalCertificate(file_get_contents($fixture->path),
                $fixture->password);
            $mock->expects('resolve')->andReturn($cert);
        });

        $queue = Queue::fake([PollEnvelopeTrackIdJob::class]);

        $job = new PollEnvelopeTrackIdJob($envelope);
        $this->app->call($job->handle(...));

        static::assertEquals(EnvelopeStatus::Uploaded, $envelope->fresh()->status);
        $event->assertNotDispatched(EnvelopeAccepted::class);
        $event->assertNotDispatched(EnvelopeRejected::class);

        $queue->assertPushed(PollEnvelopeTrackIdJob::class);
    }

    public function test_boleta_rec_status_is_treated_as_rejected(): void
    {
        $event = Event::fake([EnvelopeAccepted::class, EnvelopeRejected::class, DteRejected::class]);

        $envelope = SiiDteEnvelope::factory()->hasPayload(['sii_response' => null])->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '12345',
            'type' => 'boleta',
        ]);

        Http::fake([
            '*/boleta.electronica.semilla' => Http::response('<RESP_BODY><SEMILLA>123</SEMILLA></RESP_BODY>',
                200, ['Content-Type' => 'application/xml']),
            '*/boleta.electronica.token' => Http::response('<RESP_BODY><TOKEN>FAKE_TOKEN</TOKEN></RESP_BODY>',
                200, ['Content-Type' => 'application/xml']),
            '*/boleta.electronica.envio/*' => Http::response(['estado' => 'REC'], 200),
        ]);

        $this->mock(CertificateResolverInterface::class, function ($mock) {
            $fixture = CertificateFixture::create();
            $cert = new DigitalCertificate(file_get_contents($fixture->path),
                $fixture->password);
            $mock->expects('resolve')->andReturn($cert);
        });

        $job = new PollEnvelopeTrackIdJob($envelope);
        $this->app->call($job->handle(...));

        static::assertEquals(EnvelopeStatus::Rejected, $envelope->fresh()->status);
        static::assertNotNull($envelope->fresh()->rejected_at);
        $event->assertDispatched(EnvelopeRejected::class);
    }

    public function test_soap_rec_status_is_treated_as_rejected(): void
    {
        $event = Event::fake([EnvelopeAccepted::class, EnvelopeRejected::class, DteRejected::class]);

        $envelope = SiiDteEnvelope::factory()->hasPayload(['sii_response' => null])->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '12345',
        ]);

        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock) use ($envelope): void {
            $mock->expects('token')
                ->withArgs(fn($rut) => $rut->num === $envelope->issuer_rut->num)
                ->zeroOrMoreTimes()
                ->andReturn(new Token('FAKE_TOKEN', time() + 3600));
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(fn($request, $issuer) => $request());
        });

        $this->mock(SoapGateway::class, static function (MockInterface $mock): void {
            $mock->expects('query')
                ->withArgs(fn($token, $service, $action, $args) => $args['TrackId'] === '12345')
                ->andReturn('<ESTADO>REC</ESTADO>');
        });

        $job = new PollEnvelopeTrackIdJob($envelope);
        $this->app->call($job->handle(...));

        static::assertEquals(EnvelopeStatus::Rejected, $envelope->fresh()->status);
        static::assertNotNull($envelope->fresh()->rejected_at);

        $event->assertDispatched(EnvelopeRejected::class);
    }

    public function test_token_invalid_status_001_gives_up_after_three_attempts(): void
    {
        $this->assertTokenInvalidStatusTriggersFailcheck('001');
    }

    public function test_token_invalid_status_002_gives_up_after_three_attempts(): void
    {
        $this->assertTokenInvalidStatusTriggersFailcheck('002');
    }

    public function test_token_invalid_status_003_gives_up_after_three_attempts(): void
    {
        $this->assertTokenInvalidStatusTriggersFailcheck('003');
    }

    protected function assertTokenInvalidStatusTriggersFailcheck(string $code): void
    {
        $event = Event::fake([EnvelopeAccepted::class, EnvelopeRejected::class, DteRejected::class]);

        $envelope = SiiDteEnvelope::factory()->hasPayload(['sii_response' => null])->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '12345',
        ]);

        // The REAL TokenAuthenticator runs the refresh-and-retry loop: the
        // mocked transport always returns the invalid-token status, and every
        // re-authentication goes through the mocked auth flow (one per attempt
        // plus one per refresh).
        $this->mock(SoapGateway::class, static function (MockInterface $mock) use ($code): void {
            $mock->expects('authenticate')->times(3)->andReturn('REFRESHED_TOKEN');
            $mock->expects('query')->times(3)->andReturn('<ESTADO>'.$code.'</ESTADO>');
        });

        $this->mock(LoggerInterface::class)->expects('error')->zeroOrMoreTimes();

        Queue::fake([PollEnvelopeTrackIdJob::class]);

        $job = new PollEnvelopeTrackIdJob($envelope);

        try {
            $this->app->call($job->handle(...));
            static::fail('Expected TokenInvalidException after 3 attempts.');
        } catch (TokenInvalidException) {
            // Expected: SII kept rejecting the token after the refreshes.
        }

        // The envelope must remain Uploaded (no terminal state) and the job must
        // NOT self re-dispatch: the queue backoff handles the next attempt.
        static::assertEquals(EnvelopeStatus::Uploaded, $envelope->fresh()->status);
        $event->assertNotDispatched(EnvelopeAccepted::class);
        $event->assertNotDispatched(EnvelopeRejected::class);
        Queue::assertNotPushed(PollEnvelopeTrackIdJob::class);
    }

    public function test_logs_warning_on_unknown_status_for_boleta(): void
    {
        $envelope = SiiDteEnvelope::factory()->hasPayload(['sii_response' => null])->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => 'UNKNOWN_TRACK',
            'type' => 'boleta',
        ]);

        Http::fake([
            '*/boleta.electronica.semilla' => Http::response('<RESP_BODY><SEMILLA>12345</SEMILLA></RESP_BODY>',
                200, ['Content-Type' => 'application/xml']),
            '*/boleta.electronica.token' => Http::response('<RESP_BODY><TOKEN>FAKE_TOKEN</TOKEN></RESP_BODY>',
                200, ['Content-Type' => 'application/xml']),
            '*/boleta.electronica.envio/*' => Http::response(['estado' => 'UNKNOWN'], 200),
        ]);

        $this->mock(CertificateResolverInterface::class, function ($mock) {
            $fixture = CertificateFixture::create();
            $cert = new DigitalCertificate(file_get_contents($fixture->path),
                $fixture->password);
            $mock->expects('resolve')->andReturn($cert);
        });

        $this->mock(LoggerInterface::class)->expects('warning')->withArgs(function ($msg) use ($envelope) {
            return str_contains(
                $msg, "Unknown SII boleta track ID status received for track ID {$envelope->track_id}:"
            );
        });

        $job = new PollEnvelopeTrackIdJob($envelope);
        $this->app->call($job->handle(...));

        static::assertEquals(EnvelopeStatus::Uploaded, $envelope->fresh()->status);
    }

    public function test_keeps_envelope_uploaded_when_processing_and_no_events(): void
    {
        Event::fake([EnvelopeAccepted::class, EnvelopeRejected::class, DteRejected::class]);

        $envelope = SiiDteEnvelope::factory()->hasPayload(['sii_response' => null])->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '55555',
        ]);

        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock) use ($envelope): void {
            $mock->expects('token')
                ->withArgs(fn($rut) => $rut->num === $envelope->issuer_rut->num)
                ->zeroOrMoreTimes()
                ->andReturn(new Token('FAKE_TOKEN', time() + 3600));
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(fn($request, $issuer) => $request());
        });

        $this->mock(SoapGateway::class, static function (MockInterface $mock): void {
            $mock->expects('query')
                ->withArgs(fn($token, $service, $action, $args) => $args['TrackId'] === '55555')
                ->andReturn('<ESTADO>PRD</ESTADO>');
        });

        $queue = Queue::fake([PollEnvelopeTrackIdJob::class]);

        $job = new PollEnvelopeTrackIdJob($envelope);
        $this->app->call($job->handle(...));

        static::assertEquals(EnvelopeStatus::Uploaded, $envelope->fresh()->status);
        static::assertNull($envelope->fresh()->accepted_at);

        Event::assertNothingDispatched();

        $queue->assertPushed(PollEnvelopeTrackIdJob::class);
    }

    public function test_does_nothing_if_status_is_not_uploaded(): void
    {
        $event = Event::fake([EnvelopeAccepted::class, EnvelopeRejected::class, DteRejected::class]);

        $envelope = SiiDteEnvelope::factory()->hasPayload(['sii_response' => null])->create([
            'status' => EnvelopeStatus::Pending,
            'track_id' => '12345',
        ]);

        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock): void {
            $mock->expects('token')->never();
        });

        $this->mock(SoapGateway::class, static function (MockInterface $mock): void {
            $mock->expects('query')->never();
        });

        $job = new PollEnvelopeTrackIdJob($envelope);
        $this->app->call($job->handle(...));

        static::assertEquals(EnvelopeStatus::Pending, $envelope->fresh()->status);

        $event->assertNothingDispatched();
    }

    public function test_skips_decision_when_envelope_resolved_concurrently(): void
    {
        $event = Event::fake([EnvelopeAccepted::class, EnvelopeRejected::class, DteRejected::class]);

        $envelope = SiiDteEnvelope::factory()->hasPayload(['sii_response' => null])->create([
            'status' => EnvelopeStatus::Accepted,
            'track_id' => '12345',
        ]);

        // Stale in-memory state passes the outer guard, but the locked
        // re-check finds the already-resolved row.
        $envelope->forceFill(['status' => EnvelopeStatus::Uploaded]);

        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock) use ($envelope): void {
            $mock->expects('token')
                ->withArgs(fn($rut) => $rut->num === $envelope->issuer_rut->num)
                ->zeroOrMoreTimes()
                ->andReturn(new Token('FAKE_TOKEN', time() + 3600));
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(fn($request, $issuer) => $request());
        });

        $this->mock(SoapGateway::class, static function (MockInterface $mock): void {
            $mock->expects('query')->once()->andReturn('<ESTADO>EPR</ESTADO>');
        });

        $this->mock(LoggerInterface::class)
            ->expects('warning')
            ->once()
            ->with(
                'Skipping envelope status decision: envelope already resolved by a concurrent poll.',
                Mockery::on(static fn(array $context): bool => $context['flow'] === 'poll-track-id'
                    && $context['envelope_id'] === $envelope->getKey()),
            );

        $job = new PollEnvelopeTrackIdJob($envelope);
        $this->app->call($job->handle(...));

        static::assertEquals(EnvelopeStatus::Accepted, $envelope->fresh()->status);

        $event->assertNothingDispatched();
    }

    public function test_decision_failure_logs_and_rolls_back(): void
    {
        Event::fake([EnvelopeAccepted::class, EnvelopeRejected::class, DteRejected::class]);

        $envelope = SiiDteEnvelope::factory()->hasPayload(['sii_response' => null])->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '12345',
        ]);

        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock) use ($envelope): void {
            $mock->expects('token')
                ->withArgs(fn($rut) => $rut->num === $envelope->issuer_rut->num)
                ->zeroOrMoreTimes()
                ->andReturn(new Token('FAKE_TOKEN', time() + 3600));
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(fn($request, $issuer) => $request());
        });

        $this->mock(SoapGateway::class, static function (MockInterface $mock): void {
            $mock->expects('query')->once()->andReturn('<ESTADO>EPR</ESTADO>');
        });

        $this->mock(LoggerInterface::class)
            ->expects('error')
            ->twice()
            ->withArgs(static fn(string $message): bool => str_starts_with($message,
                    'Envelope status decision failed, rolling back to the pre-decision state.'
                ) || str_starts_with($message, 'Failed to poll TrackID 12345:'));

        SiiDteEnvelope::query()->whereKey($envelope->getKey())->delete();

        $job = new PollEnvelopeTrackIdJob($envelope);

        try {
            $this->app->call($job->handle(...));

            static::fail('Expected decision to fail.');
        } catch (ModelNotFoundException) {
            //
        }
    }

    public function test_logs_warning_on_unknown_status(): void
    {
        Event::fake([EnvelopeAccepted::class, EnvelopeRejected::class, DteRejected::class]);

        $envelope = SiiDteEnvelope::factory()->hasPayload(['sii_response' => null])->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => 'UNKNOWN_TRACK',
        ]);

        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock) use ($envelope): void {
            $mock->expects('token')
                ->withArgs(fn($rut) => $rut->num === $envelope->issuer_rut->num)
                ->zeroOrMoreTimes()
                ->andReturn(new Token('FAKE_TOKEN', time() + 3600));
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(fn($request, $issuer) => $request());
        });

        $this->mock(SoapGateway::class, static function (MockInterface $mock): void {
            $mock->expects('query')
                ->withArgs(fn($token, $service, $action, $args) => $args['TrackId'] === 'UNKNOWN_TRACK')
                ->andReturn('<ESTADO>UNKNOWN</ESTADO>');
        });

        $this->mock(LoggerInterface::class)
            ->expects('warning')
            ->with("Unknown SII track ID status received for track ID {$envelope->track_id}: <ESTADO>UNKNOWN</ESTADO>");

        $job = new PollEnvelopeTrackIdJob($envelope);
        $this->app->call($job->handle(...));

        static::assertEquals(EnvelopeStatus::Uploaded, $envelope->fresh()->status);
    }

    public function test_rejects_envelope_when_rejected_status_and_dispatches_events(): void
    {
        $event = Event::fake([EnvelopeAccepted::class, EnvelopeRejected::class, DteRejected::class]);

        $envelope = SiiDteEnvelope::factory()->hasPayload(['sii_response' => null])->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '98765',
        ]);

        $dte1 = SiiDte::factory()->create([
            'sii_dte_envelope_id' => $envelope->id, 'status' => DteStatus::Outbox,
        ]);
        $dte2 = SiiDte::factory()->create([
            'sii_dte_envelope_id' => $envelope->id, 'status' => DteStatus::Outbox,
        ]);

        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock) use ($envelope): void {
            $mock->expects('token')
                ->withArgs(fn($rut) => $rut->num === $envelope->issuer_rut->num)
                ->zeroOrMoreTimes()
                ->andReturn(new Token('FAKE_TOKEN', time() + 3600));
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(fn($request, $issuer) => $request());
        });

        $this->mock(SoapGateway::class, static function (MockInterface $mock): void {
            $mock->expects('query')
                ->withArgs(fn($token, $service, $action, $args) => $args['TrackId'] === '98765')
                ->andReturn('<ESTADO>RCH</ESTADO>');
        });

        $job = new PollEnvelopeTrackIdJob($envelope);
        $this->app->call($job->handle(...));

        static::assertEquals(EnvelopeStatus::Rejected, $envelope->fresh()->status);
        static::assertNotNull($envelope->fresh()->rejected_at);

        static::assertEquals(DteStatus::Outbox, $dte1->fresh()->status);
        static::assertNull($dte1->fresh()->sii_dte_envelope_id);
        static::assertEquals(1, $dte1->fresh()->pack_retries);

        static::assertEquals(DteStatus::Outbox, $dte2->fresh()->status);
        static::assertNull($dte2->fresh()->sii_dte_envelope_id);
        static::assertEquals(1, $dte2->fresh()->pack_retries);

        $event->assertDispatched(EnvelopeRejected::class, function ($event) use ($envelope) {
            return $event->envelope->id === $envelope->id;
        });

        $event->assertNotDispatched(DteRejected::class);
    }

    public function test_rejects_envelope_and_permanently_rejects_dtes_if_max_retries_reached(): void
    {
        $event = Event::fake([EnvelopeAccepted::class, EnvelopeRejected::class, DteRejected::class]);

        $envelope = SiiDteEnvelope::factory()->hasPayload(['sii_response' => null])->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '98765',
        ]);

        $dte1 = SiiDte::factory()->create([
            'sii_dte_envelope_id' => $envelope->id, 'status' => DteStatus::Outbox, 'pack_retries' => 3,
        ]);

        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock) use ($envelope): void {
            $mock->expects('token')
                ->withArgs(fn($rut) => $rut->num === $envelope->issuer_rut->num)
                ->zeroOrMoreTimes()
                ->andReturn(new Token('FAKE_TOKEN', time() + 3600));
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(fn($request, $issuer) => $request());
        });

        $this->mock(SoapGateway::class, static function (MockInterface $mock): void {
            $mock->expects('query')
                ->withArgs(fn($token, $service, $action, $args) => $args['TrackId'] === '98765')
                ->andReturn('<ESTADO>RCH</ESTADO>');
        });

        $job = new PollEnvelopeTrackIdJob($envelope);
        $this->app->call($job->handle(...));

        static::assertEquals(DteStatus::Rejected, $dte1->fresh()->status);
        static::assertNotNull($dte1->fresh()->rejected_at);
        static::assertEquals(3, $dte1->fresh()->pack_retries);

        $event->assertDispatched(DteRejected::class);
    }

    public function test_logs_error_and_throws_exception_on_failure(): void
    {
        $envelope = SiiDteEnvelope::factory()->hasPayload(['sii_response' => null])->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => 'ERROR_TRACK',
        ]);

        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock) use ($envelope): void {
            $mock->expects('token')
                ->withArgs(fn($rut) => $rut->num === $envelope->issuer_rut->num)
                ->andThrow(new Exception('Token error'));
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(fn($request, $issuer) => $request());
        });

        $this->mock(LoggerInterface::class)
            ->expects('error')
            ->with("Failed to poll TrackID {$envelope->track_id}: Token error");

        $job = new PollEnvelopeTrackIdJob($envelope);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageIs('Token error');

        $this->app->call($job->handle(...));
    }

    /*
     |--------------------------------------------------------------------------
     | Additional Envelope Inner DTE processing logic
     |--------------------------------------------------------------------------
     */

    public function test_accepts_boleta_envelope_and_accepts_inner_dtes_when_no_rejections(): void
    {
        $this->config('dte.dim.auto_send_interchange', false);
        $this->app['env'] = 'production';
        $this->config('dte.environment', 'production');
        $this->app->make(EnvironmentResolver::class)->flush();

        $envelope = SiiDteEnvelope::factory()->hasPayload(['sii_response' => null])->create([
            'type' => 'boleta',
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '12345',
        ]);

        $dte = SiiDte::factory()->create([
            'sii_dte_envelope_id' => $envelope->id, 'document_type' => DteType::Receipt,
            'status' => DteStatus::Pending,
        ]);

        Http::fake([
            'https://api.sii.cl/recursos/v1/boleta.electronica.semilla' => Http::response('<RESPUESTA><RESP_BODY><SEMILLA>123</SEMILLA></RESP_BODY></RESPUESTA>'),
            'https://api.sii.cl/recursos/v1/boleta.electronica.token' => Http::response('<RESPUESTA><RESP_BODY><TOKEN>tok</TOKEN></RESP_BODY></RESPUESTA>'),
            'https://api.sii.cl/recursos/v1/boleta.electronica.envio*' => Http::response([
                'estado' => 'EPR',
                'glosa' => 'Envio Procesado',
                'estadistica' => [['rechazados' => 0, 'reparos' => 0]],
            ]),
        ]);

        $this->mock(CertificateResolverInterface::class)
            ->expects('resolve')
            ->zeroOrMoreTimes()
            ->andReturn(new DigitalCertificate('fake', 'fake'));

        $this->mock(XmlSigner::class)
            ->expects('sign')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (DOMElement $target) {
                $target->setAttribute('signed', 'true');

                return $target;
            });

        $event = Event::fake([
            EnvelopeAccepted::class, DteAccepted::class,
        ]);

        Queue::fake([SendInterchangeEnvelopeJob::class]);

        $job = new PollEnvelopeTrackIdJob($envelope);
        $this->app->call($job->handle(...));

        $dte->refresh();

        static::assertEquals(DteStatus::Accepted, $dte->status);

        $event->assertDispatched(DteAccepted::class);
    }

    public function test_accepts_boleta_envelope_and_dispatches_poll_for_inner_dtes_when_rejections_present(): void
    {
        $this->config('dte.dim.auto_send_interchange', false);
        $this->app['env'] = 'production';
        $this->config('dte.environment', 'production');
        $this->app->make(EnvironmentResolver::class)->flush();

        $envelope = SiiDteEnvelope::factory()->hasPayload(['sii_response' => null])->create([
            'type' => 'boleta',
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '12345',
        ]);

        SiiDte::factory()->create([
            'sii_dte_envelope_id' => $envelope->id, 'document_type' => DteType::Receipt,
            'status' => DteStatus::Pending,
        ]);

        Http::fake([
            'https://api.sii.cl/recursos/v1/boleta.electronica.semilla' => Http::response('<RESPUESTA><RESP_BODY><SEMILLA>123</SEMILLA></RESP_BODY></RESPUESTA>'),
            'https://api.sii.cl/recursos/v1/boleta.electronica.token' => Http::response('<RESPUESTA><RESP_BODY><TOKEN>tok</TOKEN></RESP_BODY></RESPUESTA>'),
            'https://api.sii.cl/recursos/v1/boleta.electronica.envio*' => Http::response([
                'estado' => 'EPR',
                'glosa' => 'Envio Procesado',
                'estadistica' => [['rechazados' => 1, 'reparos' => 0]],
            ]),
        ]);

        $this->mock(CertificateResolverInterface::class)
            ->expects('resolve')
            ->zeroOrMoreTimes()
            ->andReturn(new DigitalCertificate('fake', 'fake'));

        $this->mock(XmlSigner::class)
            ->expects('sign')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (DOMElement $target) {
                $target->setAttribute('signed', 'true');

                return $target;
            });

        $queue = Queue::fake([
            PollDteStatusJob::class, SendInterchangeEnvelopeJob::class,
        ]);

        $job = new PollEnvelopeTrackIdJob($envelope);
        $this->app->call($job->handle(...));

        $queue->assertPushed(PollDteStatusJob::class);
    }

    public function test_accepts_normal_envelope_and_accepts_inner_dtes_when_no_rejections(): void
    {
        $envelope = SiiDteEnvelope::factory()->hasPayload(['sii_response' => null])->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '12345',
        ]);

        $dte = SiiDte::factory()->create([
            'sii_dte_envelope_id' => $envelope->id, 'document_type' => DteType::Invoice,
            'status' => DteStatus::Pending,
        ]);

        $foreign = SiiDte::factory()->create(['status' => DteStatus::Pending]);

        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock) use ($envelope): void {
            $mock->expects('token')
                ->withArgs(fn($rut) => $rut->num === $envelope->issuer_rut->num)
                ->zeroOrMoreTimes()
                ->andReturn(new Token('FAKE_TOKEN',
                    time() + 3600));
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(fn($request, $issuer) => $request());
        });

        $this->mock(SoapGateway::class,
            static function (MockInterface $mock): void {
                $mock->expects('query')
                    ->withArgs(fn($token, $service, $action, $args) => $args['TrackId'] === '12345')
                    ->andReturn('<SII:RESPUESTA xmlns:SII="http://www.sii.cl/XMLSchema"><SII:RESP_BODY><ESTADO>EPR</ESTADO><RECHAZADOS>0</RECHAZADOS><REPAROS>0</REPAROS></SII:RESP_BODY></SII:RESPUESTA>');
            });

        $event = Event::fake([
            EnvelopeAccepted::class, DteAccepted::class,
        ]);
        Queue::fake([SendInterchangeEnvelopeJob::class]);

        $job = new PollEnvelopeTrackIdJob($envelope);
        $this->app->call($job->handle(...));

        $dte->refresh();

        static::assertEquals(DteStatus::Accepted, $dte->status);
        static::assertEquals(DteStatus::Pending, $foreign->fresh()->status);

        $event->assertDispatched(DteAccepted::class);
    }

    public function test_accepts_normal_envelope_and_dispatches_poll_for_inner_dtes_when_rejections_present(): void
    {
        $envelope = SiiDteEnvelope::factory()->hasPayload(['sii_response' => null])->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '12345',
        ]);

        SiiDte::factory()->create([
            'sii_dte_envelope_id' => $envelope->id, 'document_type' => DteType::Invoice,
            'status' => DteStatus::Pending,
        ]);

        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock) use ($envelope): void {
            $mock->expects('token')
                ->withArgs(fn($rut) => $rut->num === $envelope->issuer_rut->num)
                ->zeroOrMoreTimes()
                ->andReturn(new Token('FAKE_TOKEN',
                    time() + 3600));
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(fn($request, $issuer) => $request());
        });

        $this->mock(SoapGateway::class,
            static function (MockInterface $mock): void {
                $mock->expects('query')
                    ->withArgs(fn($token, $service, $action, $args) => $args['TrackId'] === '12345')
                    ->andReturn('<SII:RESPUESTA xmlns:SII="http://www.sii.cl/XMLSchema"><SII:RESP_BODY><ESTADO>EPR</ESTADO><RECHAZADOS>1</RECHAZADOS><REPAROS>0</REPAROS></SII:RESP_BODY></SII:RESPUESTA>');
            });

        $queue = Queue::fake([
            PollDteStatusJob::class, SendInterchangeEnvelopeJob::class,
        ]);

        $job = new PollEnvelopeTrackIdJob($envelope);
        $this->app->call($job->handle(...));

        $queue->assertPushed(PollDteStatusJob::class);
    }

    public function test_accepts_normal_envelope_and_accepts_inner_dtes_when_no_resp_body(): void
    {
        $event = Event::fake([EnvelopeAccepted::class, DteAccepted::class]);

        $envelope = SiiDteEnvelope::factory()->hasPayload(['sii_response' => null])->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '12345',
        ]);

        $dte = SiiDte::factory()->create([
            'sii_dte_envelope_id' => $envelope->id, 'document_type' => DteType::Invoice,
            'status' => DteStatus::Sent,
        ]);
        $foreign = SiiDte::factory()->create(['status' => DteStatus::Sent]);

        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock) use ($envelope): void {
            $mock->expects('token')
                ->withArgs(fn($rut) => $rut->num === $envelope->issuer_rut->num)
                ->zeroOrMoreTimes()
                ->andReturn(new Token('FAKE_TOKEN', time() + 3600));
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(fn($request, $issuer) => $request());
        });

        $this->mock(SoapGateway::class, static function (MockInterface $mock): void {
            $mock->expects('query')
                ->withArgs(fn($token, $service, $action, $args) => $args['TrackId'] === '12345')
                ->andReturn('<ESTADO>EPR</ESTADO>');
        });

        Queue::fake([SendInterchangeEnvelopeJob::class, PollDteStatusJob::class]);

        $job = new PollEnvelopeTrackIdJob($envelope);
        $this->app->call($job->handle(...));

        static::assertEquals(EnvelopeStatus::Accepted, $envelope->fresh()->status);
        static::assertEquals(DteStatus::Accepted, $dte->fresh()->status);
        static::assertNotNull($dte->fresh()->accepted_at);
        static::assertEquals(DteStatus::Sent, $foreign->fresh()->status);

        $event->assertDispatched(DteAccepted::class, fn($fired) => $fired->dte->is($dte));
    }

    public function test_accepts_normal_envelope_without_touching_terminal_inner_dtes(): void
    {
        $event = Event::fake([EnvelopeAccepted::class, DteAccepted::class]);

        $envelope = SiiDteEnvelope::factory()->hasPayload(['sii_response' => null])->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '12345',
        ]);

        $sent = SiiDte::factory()->create([
            'sii_dte_envelope_id' => $envelope->id, 'document_type' => DteType::Invoice,
            'status' => DteStatus::Sent,
        ]);
        $rejected = SiiDte::factory()->create([
            'sii_dte_envelope_id' => $envelope->id, 'document_type' => DteType::Invoice,
            'status' => DteStatus::Rejected,
        ]);
        $accepted = SiiDte::factory()->create([
            'sii_dte_envelope_id' => $envelope->id, 'document_type' => DteType::Invoice,
            'status' => DteStatus::Accepted,
        ]);
        $foreign = SiiDte::factory()->create(['status' => DteStatus::Sent]);

        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock) use ($envelope): void {
            $mock->expects('token')
                ->withArgs(fn($rut) => $rut->num === $envelope->issuer_rut->num)
                ->zeroOrMoreTimes()
                ->andReturn(new Token('FAKE_TOKEN', time() + 3600));
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(fn($request, $issuer) => $request());
        });

        $this->mock(SoapGateway::class, static function (MockInterface $mock): void {
            $mock->expects('query')
                ->withArgs(fn($token, $service, $action, $args) => $args['TrackId'] === '12345')
                ->andReturn('<SII:RESPUESTA xmlns:SII="http://www.sii.cl/XMLSchema"><SII:RESP_BODY><ESTADO>EPR</ESTADO><RECHAZADOS>0</RECHAZADOS><REPAROS>0</REPAROS></SII:RESP_BODY></SII:RESPUESTA>');
        });

        Queue::fake([SendInterchangeEnvelopeJob::class]);

        $job = new PollEnvelopeTrackIdJob($envelope);
        $this->app->call($job->handle(...));

        static::assertEquals(EnvelopeStatus::Accepted, $envelope->fresh()->status);
        static::assertEquals(DteStatus::Accepted, $sent->fresh()->status);
        static::assertEquals(DteStatus::Rejected, $rejected->fresh()->status);
        static::assertNull($rejected->fresh()->accepted_at);
        static::assertEquals(DteStatus::Accepted, $accepted->fresh()->status);
        static::assertEquals(DteStatus::Sent, $foreign->fresh()->status);

        $event->assertDispatched(DteAccepted::class, fn($fired) => $fired->dte->is($sent));
        $event->assertDispatchedTimes(DteAccepted::class, 1);
    }

    public function test_all_dtes_accepted_uses_single_batch_update(): void
    {
        $this->config('dte.dim.auto_send_interchange', false);

        Event::fake([EnvelopeAccepted::class, DteAccepted::class]);

        $envelope = SiiDteEnvelope::factory()->hasPayload(['sii_response' => null])->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '12345',
        ]);

        foreach (range(1, 3) as $i) {
            SiiDte::factory()->create([
                'sii_dte_envelope_id' => $envelope->id,
                'document_type' => DteType::Invoice,
                'status' => DteStatus::Pending,
            ]);
        }

        $this->mock(TokenAuthenticator::class,
            static function (MockInterface $mock) use ($envelope): void {
                $mock->expects('token')
                    ->withArgs(fn($rut) => $rut->num === $envelope->issuer_rut->num)
                    ->zeroOrMoreTimes()
                    ->andReturn(new Token('FAKE_TOKEN', time() + 3600));
                $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                    ->andReturnUsing(fn($request, $issuer) => $request());
            });

        $this->mock(SoapGateway::class,
            static function (MockInterface $mock): void {
                $mock->expects('query')
                    ->withArgs(fn($token, $service, $action, $args) => $args['TrackId'] === '12345')
                    ->andReturn('<SII:RESPUESTA xmlns:SII="http://www.sii.cl/XMLSchema"><SII:RESP_BODY><ESTADO>EPR</ESTADO><RECHAZADOS>0</RECHAZADOS><REPAROS>0</REPAROS></SII:RESP_BODY></SII:RESPUESTA>');
            });

        $updatesOnSiiDtes = 0;

        DB::listen(static function ($query) use (&$updatesOnSiiDtes): void {
            $sql = strtolower($query->sql);

            if (str_contains($sql, 'update') && str_contains($sql, 'sii_dtes')) {
                $updatesOnSiiDtes++;
            }
        });

        $job = new PollEnvelopeTrackIdJob($envelope);
        $this->app->call($job->handle(...));

        static::assertSame(1, $updatesOnSiiDtes, 'Expected a single batch UPDATE over the envelope DTEs.');
    }

    public function test_all_dtes_accepted_dispatches_event_for_each_dte_with_attributes(): void
    {
        $this->config('dte.dim.auto_send_interchange', false);

        Event::fake([EnvelopeAccepted::class, DteAccepted::class]);

        $envelope = SiiDteEnvelope::factory()->hasPayload(['sii_response' => null])->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '12345',
        ]);

        $dtes = [];

        foreach (range(1, 3) as $i) {
            $dtes[] = SiiDte::factory()->create([
                'sii_dte_envelope_id' => $envelope->id,
                'document_type' => DteType::Invoice,
                'status' => DteStatus::Pending,
            ]);
        }

        $this->mock(TokenAuthenticator::class,
            static function (MockInterface $mock) use ($envelope): void {
                $mock->expects('token')
                    ->withArgs(fn($rut) => $rut->num === $envelope->issuer_rut->num)
                    ->zeroOrMoreTimes()
                    ->andReturn(new Token('FAKE_TOKEN', time() + 3600));
                $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                    ->andReturnUsing(fn($request, $issuer) => $request());
            });

        $this->mock(SoapGateway::class,
            static function (MockInterface $mock): void {
                $mock->expects('query')
                    ->withArgs(fn($token, $service, $action, $args) => $args['TrackId'] === '12345')
                    ->andReturn('<SII:RESPUESTA xmlns:SII="http://www.sii.cl/XMLSchema"><SII:RESP_BODY><ESTADO>EPR</ESTADO><RECHAZADOS>0</RECHAZADOS><REPAROS>0</REPAROS></SII:RESP_BODY></SII:RESPUESTA>');
            });

        $job = new PollEnvelopeTrackIdJob($envelope);
        $this->app->call($job->handle(...));

        // Verify a DteAccepted event was dispatched for every DTE and that the
        // event carries the model with correct in-memory attributes after forceFill.
        foreach ($dtes as $dte) {
            Event::assertDispatched(
                DteAccepted::class,
                static function (DteAccepted $event) use ($dte): bool {
                    if ($event->dte->getKey() !== $dte->getKey()) {
                        return false;
                    }

                    // The event model should reflect the fresh DB state via forceFill.
                    static::assertEquals(DteStatus::Accepted, $event->dte->status);
                    static::assertNotNull($event->dte->accepted_at);
                    static::assertNotNull($event->dte->updated_at);

                    return true;
                },
            );
        }

        // Verify DB state.
        foreach ($dtes as $dte) {
            $fresh = $dte->fresh();

            static::assertEquals(DteStatus::Accepted, $fresh->status);
            static::assertNotNull($fresh->accepted_at);
            static::assertNotNull($fresh->updated_at);
        }
    }

    public function test_processing_redispatch_respects_small_envelope_floor_plus_offset(): void
    {
        $this->config('dte.polling.delay_under_30kb', 30);

        $envelope = SiiDteEnvelope::factory()->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '55555',
        ]);

        $payload = SiiDteEnvelopePayload::factory()->create([
            'sii_dte_envelope_id' => $envelope->id,
            'xml' => str_repeat('a', 100), // well under 30 KB
        ]);
        $envelope->setRelation('payload', $payload);

        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock) use ($envelope): void {
            $mock->expects('token')
                ->withArgs(fn($rut) => $rut->num === $envelope->issuer_rut->num)
                ->zeroOrMoreTimes()
                ->andReturn(new Token('FAKE_TOKEN', time() + 3600));
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(fn($request, $issuer) => $request());
        });

        $this->mock(SoapGateway::class, static function (MockInterface $mock): void {
            $mock->expects('query')
                ->withArgs(fn($token, $service, $action, $args) => $args['TrackId'] === '55555')
                ->andReturn('<ESTADO>PRD</ESTADO>');
        });

        $queue = Queue::fake([PollEnvelopeTrackIdJob::class]);

        $job = new PollEnvelopeTrackIdJob($envelope);
        $this->app->call($job->handle(...));

        $queue->assertPushed(PollEnvelopeTrackIdJob::class, static function (PollEnvelopeTrackIdJob $redispatch): bool {
            return $redispatch->delay !== null
                && $redispatch->delay->getTimestamp() >= now()->addSeconds(120)->getTimestamp() + 25;
        });
    }

    public function test_processing_redispatch_respects_large_envelope_floor_plus_offset(): void
    {
        $this->config('dte.polling.delay_over_30kb', 90);

        $envelope = SiiDteEnvelope::factory()->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '55555',
        ]);

        $payload = SiiDteEnvelopePayload::factory()->create([
            'sii_dte_envelope_id' => $envelope->id,
            'xml' => str_repeat('b', 31 * 1024), // at/over 30 KB
        ]);
        $envelope->setRelation('payload', $payload);

        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock) use ($envelope): void {
            $mock->expects('token')
                ->withArgs(fn($rut) => $rut->num === $envelope->issuer_rut->num)
                ->zeroOrMoreTimes()
                ->andReturn(new Token('FAKE_TOKEN', time() + 3600));
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(fn($request, $issuer) => $request());
        });

        $this->mock(SoapGateway::class, static function (MockInterface $mock): void {
            $mock->expects('query')
                ->withArgs(fn($token, $service, $action, $args) => $args['TrackId'] === '55555')
                ->andReturn('<ESTADO>PRD</ESTADO>');
        });

        $queue = Queue::fake([PollEnvelopeTrackIdJob::class]);

        $job = new PollEnvelopeTrackIdJob($envelope);
        $this->app->call($job->handle(...));

        $queue->assertPushed(PollEnvelopeTrackIdJob::class, static function (PollEnvelopeTrackIdJob $redispatch): bool {
            return $redispatch->delay !== null
                && $redispatch->delay->getTimestamp() >= now()->addSeconds(360)->getTimestamp() + 85;
        });
    }

    public function test_default_delay_uses_conservative_large_floor_when_payload_unknown(): void
    {
        $this->config('dte.polling.delay_over_30kb', 0);

        $envelope = SiiDteEnvelope::factory()->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '55555',
        ]);

        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock) use ($envelope): void {
            $mock->expects('token')
                ->withArgs(fn($rut) => $rut->num === $envelope->issuer_rut->num)
                ->zeroOrMoreTimes()
                ->andReturn(new Token('FAKE_TOKEN', time() + 3600));
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(fn($request, $issuer) => $request());
        });

        $this->mock(SoapGateway::class, static function (MockInterface $mock): void {
            $mock->expects('query')
                ->withArgs(fn($token, $service, $action, $args) => $args['TrackId'] === '55555')
                ->andReturn('<ESTADO>PRD</ESTADO>');
        });

        $queue = Queue::fake([PollEnvelopeTrackIdJob::class]);

        $job = new PollEnvelopeTrackIdJob($envelope);
        $this->app->call($job->handle(...));

        $queue->assertPushed(PollEnvelopeTrackIdJob::class, static function (PollEnvelopeTrackIdJob $redispatch): bool {
            return $redispatch->delay !== null
                && $redispatch->delay->getTimestamp() >= now()->addSeconds(360)->getTimestamp() - 5;
        });
    }

    public function test_boleta_processing_uses_retry_after_header_value(): void
    {
        // Line 390: $retryAfter when X-Retry-After header is > 0
        $envelope = SiiDteEnvelope::factory()->hasPayload(['sii_response' => null])->create([
            'status' => EnvelopeStatus::Uploaded,
            'track_id' => '67890',
            'type' => 'boleta',
        ]);

        Http::fake([
            '*/boleta.electronica.semilla' => Http::response(
                '<RESP_BODY><SEMILLA>123</SEMILLA></RESP_BODY>',
                200,
                ['Content-Type' => 'application/xml']
            ),
            '*/boleta.electronica.token' => Http::response(
                '<RESP_BODY><TOKEN>FAKE_TOKEN</TOKEN></RESP_BODY>',
                200,
                ['Content-Type' => 'application/xml']
            ),
            '*/boleta.electronica.envio/*' => Http::response(
                ['estado' => 'PRD'],
                200,
                ['X-Retry-After' => '90']
            ),
        ]);

        $this->mock(CertificateResolverInterface::class, static function ($mock): void {
            $fixture = CertificateFixture::create();
            $cert = new DigitalCertificate(file_get_contents($fixture->path), $fixture->password);
            $mock->expects('resolve')->andReturn($cert);
        });

        $queue = Queue::fake([PollEnvelopeTrackIdJob::class]);

        $job = new PollEnvelopeTrackIdJob($envelope);
        $this->app->call($job->handle(...));

        // The X-Retry-After header value (90) should be used as the delay
        $queue->assertPushed(PollEnvelopeTrackIdJob::class, static function (PollEnvelopeTrackIdJob $redispatch): bool {
            return $redispatch->delay !== null
                && $redispatch->delay->getTimestamp() >= now()->addSeconds(90)->getTimestamp()
                && $redispatch->delay->getTimestamp() <= now()->addSeconds(91)->getTimestamp();
        });
    }
}
