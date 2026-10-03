<?php

namespace Tests\Unit\Gateways;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http as HttpFacade;
use Illuminate\Support\Sleep;
use Laragear\Dte\Data\Token;
use Laragear\Dte\Enums\DteEnvironment;
use Laragear\Dte\Enums\EnvelopeStatus;
use Laragear\Dte\Environment\EnvironmentResolver;
use Laragear\Dte\Gateways\UploadGateway;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Support\TokenAuthenticator;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\DatabaseTestCase;

class UploadGatewayTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();

        HttpFacade::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Sleep::fake(false);

        parent::tearDown();
    }

    protected function makeGateway(DteEnvironment $environment = DteEnvironment::Production): UploadGateway
    {
        $token = new Token('sii-token', time() + 3600);

        $this->mock(TokenAuthenticator::class, static function (MockInterface $mock) use ($token): void {
            $mock->expects('token')->zeroOrMoreTimes()->andReturn($token);
            $mock->expects('retryWithFreshToken')->zeroOrMoreTimes()
                ->andReturnUsing(fn($request, $issuer) => $request());
        });

        $this->instance(EnvironmentResolver::class, $this->makeEnvironmentResolver($environment));

        return $this->app->make(UploadGateway::class);
    }

    protected function makeEnvironmentResolver(DteEnvironment $environment): EnvironmentResolver
    {
        $app = Mockery::mock(Application::class);
        $app->allows('environment')->with(DteEnvironment::Production->value)->andReturn($environment === DteEnvironment::Production);
        $app->allows('environment')->withNoArgs()->andReturn($environment->value);

        return new EnvironmentResolver(new Repository([
            'dte' => ['environment' => $environment->value],
        ]), $app);
    }

    protected function makeEnvelope(): SiiDteEnvelope
    {
        return SiiDteEnvelope::factory()->create([
            'status' => EnvelopeStatus::Signed,
        ]);
    }

    public function test_uploads_envelope_and_returns_track_id(): void
    {
        $envelope = $this->makeEnvelope();

        HttpFacade::fake([
            'https://palena.sii.cl/cgi_dte/UPL/DTEUpload*' => HttpFacade::response(
                '<RESPONSE><STATUS>0</STATUS><TRACKID>987654</TRACKID></RESPONSE>',
                200,
            ),
        ]);

        $gateway = $this->makeGateway();
        $trackId = $gateway->upload($envelope, '<EnvioDTE>...</EnvioDTE>');

        static::assertSame('987654', $trackId);
    }

    public function test_returns_fake_track_id_in_local_environment(): void
    {
        $envelope = $this->makeEnvelope();
        $gateway = $this->makeGateway(DteEnvironment::Local);

        $trackId = $gateway->upload($envelope, '<EnvioDTE>...</EnvioDTE>');

        static::assertStringStartsWith('fake-track-id-', $trackId);

        HttpFacade::assertNothingSent();
    }

    public function test_sends_issuer_and_sender_rut_in_request(): void
    {
        $envelope = $this->makeEnvelope();

        HttpFacade::fake([
            'https://palena.sii.cl/cgi_dte/UPL/DTEUpload*' => HttpFacade::response(
                '<RESPONSE><STATUS>0</STATUS><TRACKID>111</TRACKID></RESPONSE>',
            ),
        ]);

        $gateway = $this->makeGateway();
        $gateway->upload($envelope, '<EnvioDTE/>');

        HttpFacade::assertSent(function (Request $request) use ($envelope): bool {
            static::assertStringContainsString($envelope->issuer_rut->num, $request->body());

            return true;
        });
    }

    public function test_upload_sends_prog_user_agent_header(): void
    {
        $envelope = $this->makeEnvelope();

        HttpFacade::fake([
            'https://palena.sii.cl/cgi_dte/UPL/DTEUpload*' => HttpFacade::response(
                '<RESPONSE><STATUS>0</STATUS><TRACKID>123</TRACKID></RESPONSE>',
                200,
            ),
        ]);

        $gateway = $this->makeGateway();
        $gateway->upload($envelope, '<EnvioDTE/>');

        HttpFacade::assertSent(function (Request $request): bool {
            static::assertStringContainsString('PROG 1.0', implode(', ', $request->header('User-Agent', [])));

            return true;
        });
    }

    public function test_throws_on_unauthorized_response(): void
    {
        $envelope = $this->makeEnvelope();

        HttpFacade::fake([
            'https://palena.sii.cl/cgi_dte/UPL/DTEUpload*' => HttpFacade::response(null, 401),
        ]);

        $gateway = $this->makeGateway();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('SII Upload rejected the authentication token (401).');

        $gateway->upload($envelope, '<EnvioDTE/>');
    }

    public function test_does_not_retry_on_unauthorized_response(): void
    {
        $envelope = $this->makeEnvelope();

        HttpFacade::fake([
            'https://palena.sii.cl/cgi_dte/UPL/DTEUpload*' => HttpFacade::response(null, 401),
        ]);

        try {
            $this->makeGateway()->upload($envelope, '<EnvioDTE/>');

            static::fail('The upload was expected to fail.');
        } catch (RuntimeException) {
            // A rejected token is never accepted on retry, so it must cost a single attempt.
            HttpFacade::assertSentCount(1);
        }
    }

    public function test_retries_on_server_error_response(): void
    {
        $envelope = $this->makeEnvelope();

        HttpFacade::fake([
            'https://palena.sii.cl/cgi_dte/UPL/DTEUpload*' => HttpFacade::response(null, 500),
        ]);

        try {
            $this->makeGateway()->upload($envelope, '<EnvioDTE/>');

            static::fail('The upload was expected to fail.');
        } catch (RuntimeException) {
            // Transient 5xx keeps the full backoff: 1 initial attempt plus 5 retries.
            HttpFacade::assertSentCount(6);
        }
    }

    public function test_throws_on_failed_response(): void
    {
        $envelope = $this->makeEnvelope();

        HttpFacade::fake([
            'https://palena.sii.cl/cgi_dte/UPL/DTEUpload*' => HttpFacade::response(null, 500),
        ]);

        $gateway = $this->makeGateway();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('SII Upload request failed with status 500.');

        $gateway->upload($envelope, '<EnvioDTE/>');
    }

    public function test_throws_on_non_zero_status_in_response(): void
    {
        $envelope = $this->makeEnvelope();

        HttpFacade::fake([
            'https://palena.sii.cl/cgi_dte/UPL/DTEUpload*' => HttpFacade::response(
                '<RESPONSE><STATUS>ERROR: invalid structure</STATUS></RESPONSE>',
                200,
            ),
        ]);

        $gateway = $this->makeGateway();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('SII Upload rejected the envelope: ERROR: invalid structure');

        $gateway->upload($envelope, '<EnvioDTE/>');
    }

    public function test_throws_when_response_has_no_track_id(): void
    {
        $envelope = $this->makeEnvelope();

        HttpFacade::fake([
            'https://palena.sii.cl/cgi_dte/UPL/DTEUpload*' => HttpFacade::response(
                '<RESPONSE><STATUS>0</STATUS></RESPONSE>',
                200,
            ),
        ]);

        $gateway = $this->makeGateway();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('SII Upload response did not contain a valid TrackID.');

        $gateway->upload($envelope, '<EnvioDTE/>');
    }
}
