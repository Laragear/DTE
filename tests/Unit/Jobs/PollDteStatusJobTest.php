<?php

namespace Tests\Unit\Jobs;

use DOMElement;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Dte\Contracts\CertificateResolverInterface;
use Laragear\Dte\Data\Token;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Environment\EnvironmentResolver;
use Laragear\Dte\Events\DteAccepted;
use Laragear\Dte\Events\DteRejected;
use Laragear\Dte\Gateways\Exceptions\TokenInvalidException;
use Laragear\Dte\Gateways\SoapGateway;
use Laragear\Dte\Jobs\PollDteStatusJob;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Support\TokenAuthenticator;
use Laragear\Dte\Xml\XmlSigner;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\DatabaseTestCase;
use Tests\Unit\Certificate\Fixtures\CertificateFixture;

class PollDteStatusJobTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['env'] = 'production';
        $this->config('app.env', 'production');
        $this->config('dte.environment', 'production');
        $this->app->make(EnvironmentResolver::class)->flush();
    }

    public function test_accepts_dte_when_sii_responds_dok(): void
    {
        Event::fake([DteAccepted::class]);

        $dte = SiiDte::factory()->hasPayload(['sii_response' => null])->create(['status' => DteStatus::Pending]);

        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock): void {
            $mock->expects('token')
                ->zeroOrMoreTimes()
                ->andReturn(new Token('FAKE_TOKEN', time() + 3600));
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(fn($request, $issuer) => $request());
        });

        $this->mock(SoapGateway::class, static function (MockInterface $mock): void {
            $mock->expects('query')
                ->withArgs(fn($token, $service, $action, $args) => $args['Token'] === 'FAKE_TOKEN')
                ->andReturn('<ESTADO>DOK</ESTADO>');
        });

        $job = new PollDteStatusJob($dte);
        $this->app->call($job->handle(...));

        static::assertEquals(DteStatus::Accepted, $dte->fresh()->status);
        static::assertNotNull($dte->fresh()->accepted_at);

        Event::assertDispatched(DteAccepted::class);
    }

    public function test_rejects_dte_when_sii_responds_fau(): void
    {
        Event::fake([DteRejected::class]);

        $dte = SiiDte::factory()->hasPayload(['sii_response' => null])->create(['status' => DteStatus::Pending]);

        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock): void {
            $mock->expects('token')
                ->zeroOrMoreTimes()
                ->andReturn(new Token('FAKE_TOKEN', time() + 3600));
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(fn($request, $issuer) => $request());
        });

        $this->mock(SoapGateway::class, static function (MockInterface $mock): void {
            $mock->expects('query')
                ->andReturn('<ESTADO>FAU</ESTADO>');
        });

        $job = new PollDteStatusJob($dte);
        $this->app->call($job->handle(...));

        static::assertEquals(DteStatus::Rejected, $dte->fresh()->status);
        static::assertNotNull($dte->fresh()->rejected_at);

        Event::assertDispatched(DteRejected::class);
    }

    public function test_logs_warning_on_unknown_sii_response(): void
    {
        $dte = SiiDte::factory()->hasPayload(['sii_response' => null])->create(['status' => DteStatus::Pending]);

        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock): void {
            $mock->expects('token')
                ->zeroOrMoreTimes()
                ->andReturn(new Token('FAKE_TOKEN', time() + 3600));
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(fn($request, $issuer) => $request());
        });

        $this->mock(SoapGateway::class, static function (MockInterface $mock): void {
            $mock->expects('query')
                ->andReturn('<ESTADO>UNKNOWN_STATUS</ESTADO>');
        });

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->expects('warning')->withArgs(function ($message) use ($dte) {
            return str_contains($message, "Unknown or non-terminal SII DTE status received for DTE {$dte->getKey()}");
        });
        $this->app->instance(LoggerInterface::class, $logger);

        $job = new PollDteStatusJob($dte);
        $this->app->call($job->handle(...));

        static::assertEquals(DteStatus::Pending, $dte->fresh()->status);
    }

    public function test_logs_warning_on_unknown_sii_boleta_response(): void
    {
        $dte = SiiDte::factory()->hasPayload(['sii_response' => null])->create([
            'status' => DteStatus::Pending, 'document_type' => DteType::Receipt, 'folio' => 123,
        ]);

        Http::fake([
            '*/boleta.electronica.semilla' => Http::response('<RESP_BODY><SEMILLA>12345</SEMILLA></RESP_BODY>',
                200, ['Content-Type' => 'application/xml']),
            '*/boleta.electronica.token' => Http::response('<RESP_BODY><TOKEN>FAKE_TOKEN</TOKEN></RESP_BODY>',
                200, ['Content-Type' => 'application/xml']),
            '*/boleta.electronica/*' => Http::response(['estado' => 'UNKNOWN_STATUS'], 200),
        ]);

        $this->mock(CertificateResolverInterface::class, function ($mock) {
            $fixture = CertificateFixture::create();
            $cert = new DigitalCertificate(file_get_contents($fixture->path),
                $fixture->password);
            $mock->expects('resolve')->andReturn($cert);
        });

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->expects('warning')->withArgs(function ($message) use ($dte) {
            return str_contains($message, "Unknown SII boleta DTE status received for DTE {$dte->getKey()}");
        });
        $this->app->instance(LoggerInterface::class, $logger);

        $job = new PollDteStatusJob($dte);
        $this->app->call($job->handle(...));

        static::assertEquals(DteStatus::Pending, $dte->fresh()->status);
    }

    public function test_does_not_poll_terminal_status(): void
    {
        Event::fake();

        $dte = SiiDte::factory()->hasPayload(['sii_response' => null])->create(['status' => DteStatus::Rejected]);

        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock): void {
            $mock->expects('token')->never();
        });

        $job = new PollDteStatusJob($dte);
        $this->app->call($job->handle(...));

        Event::assertNotDispatched(DteAccepted::class);
        Event::assertNotDispatched(DteRejected::class);
    }

    /*
     |--------------------------------------------------------------------------
     | Additional Boleta Status Tests
     |--------------------------------------------------------------------------
     */

    public function test_accepts_boleta_dte_when_dok_and_dispatches_event(): void
    {
        $this->app['env'] = 'production';
        $this->config('dte.environment', 'production');
        $this->app->make(EnvironmentResolver::class)->flush();

        $dte = SiiDte::factory()->hasPayload(['sii_response' => null])->create([
            'document_type' => DteType::Receipt,
            'status' => DteStatus::Pending,
        ]);

        Http::fake([
            'https://api.sii.cl/recursos/v1/boleta.electronica.semilla' => Http::response('<RESPUESTA><RESP_BODY><SEMILLA>030530912644</SEMILLA></RESP_BODY></RESPUESTA>'),
            'https://api.sii.cl/recursos/v1/boleta.electronica.token' => Http::response('<RESPUESTA><RESP_BODY><TOKEN>random-token</TOKEN></RESP_BODY></RESPUESTA>'),
            'https://api.sii.cl/recursos/v1/boleta.electronica*' => Http::response([
                'estado' => 'DOK', 'glosa' => 'Documento OK',
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

        Event::fake([DteAccepted::class]);

        $job = new PollDteStatusJob($dte);
        $this->app->call($job->handle(...));

        $dte->refresh();
        static::assertEquals(DteStatus::Accepted, $dte->status);
        static::assertNotNull($dte->accepted_at);

        Event::assertDispatched(DteAccepted::class,
            function ($event) use ($dte) {
                return $event->dte->is($dte);
            });
    }

    public function test_rejects_boleta_dte_when_rch_and_dispatches_event(): void
    {
        $this->app['env'] = 'production';
        $this->config('dte.environment', 'production');
        $this->app->make(EnvironmentResolver::class)->flush();

        $dte = SiiDte::factory()->hasPayload(['sii_response' => null])->create([
            'document_type' => DteType::Receipt,
            'status' => DteStatus::Pending,
        ]);

        Http::fake([
            'https://api.sii.cl/recursos/v1/boleta.electronica.semilla' => Http::response('<RESPUESTA><RESP_BODY><SEMILLA>030530912644</SEMILLA></RESP_BODY></RESPUESTA>'),
            'https://api.sii.cl/recursos/v1/boleta.electronica.token' => Http::response('<RESPUESTA><RESP_BODY><TOKEN>random-token</TOKEN></RESP_BODY></RESPUESTA>'),
            'https://api.sii.cl/recursos/v1/boleta.electronica*' => Http::response([
                'estado' => 'RCH', 'glosa' => 'Rechazado',
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

        Event::fake([DteRejected::class]);

        $job = new PollDteStatusJob($dte);
        $this->app->call($job->handle(...));

        $dte->refresh();
        static::assertEquals(DteStatus::Rejected, $dte->status);
        static::assertNotNull($dte->rejected_at);

        Event::assertDispatched(DteRejected::class,
            function ($event) use ($dte) {
                return $event->dte->is($dte);
            });
    }

    public function test_rethrows_exception_on_gateway_failure(): void
    {
        $dte = SiiDte::factory()->hasPayload(['sii_response' => null])->create([
            'document_type' => DteType::Invoice,
            'status' => DteStatus::Pending,
        ]);

        $authenticator = Mockery::mock(TokenAuthenticator::class);
        $authenticator->expects('retryWithFreshToken')->andThrow(new RuntimeException('Connection failed'));
        $this->app->instance(TokenAuthenticator::class, $authenticator);

        $this->mock(LoggerInterface::class)
            ->expects('error')
            ->with("Failed to poll DTE status for ID {$dte->getKey()}: Connection failed");

        $this->expectException(RuntimeException::class);
        $this->expectexceptionMessageIs('Connection failed');

        $job = new PollDteStatusJob($dte);
        $this->app->call($job->handle(...));
    }

    public function test_throws_token_invalid_exception_when_sii_returns_001(): void
    {
        $this->assertTokenInvalidResponseThrows('001');
    }

    public function test_throws_token_invalid_exception_when_sii_returns_002(): void
    {
        $this->assertTokenInvalidResponseThrows('002');
    }

    public function test_throws_token_invalid_exception_when_sii_returns_003(): void
    {
        $this->assertTokenInvalidResponseThrows('003');
    }

    protected function assertTokenInvalidResponseThrows(string $code): void
    {
        // Line 105: throw new TokenInvalidException when SII returns 001/002/003
        $dte = SiiDte::factory()->hasPayload(['sii_response' => null])->create([
            'status' => DteStatus::Pending,
        ]);

        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock): void {
            $mock->expects('token')
                ->zeroOrMoreTimes()
                ->andReturn(new Token('FAKE_TOKEN', time() + 3600));
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(fn($request, $issuer) => $request());
        });

        $this->mock(SoapGateway::class, static function (MockInterface $mock) use ($code): void {
            $mock->expects('query')->once()->andReturn('<ESTADO>'.$code.'</ESTADO>');
        });

        $this->mock(LoggerInterface::class)
            ->expects('error')
            ->withArgs(fn(string $msg): bool => str_contains($msg, 'SII SOAP token was invalidated'));

        $this->expectException(TokenInvalidException::class);

        $job = new PollDteStatusJob($dte);
        $this->app->call($job->handle(...));
    }
}
