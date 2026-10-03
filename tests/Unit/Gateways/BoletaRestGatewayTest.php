<?php

namespace Tests\Unit\Gateways;

use DOMElement;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http as HttpFacade;
use InvalidArgumentException;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Dte\Contracts\CertificateResolverInterface;
use Laragear\Dte\Data\Token;
use Laragear\Dte\Enums\DteEnvironment;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\EnvelopeStatus;
use Laragear\Dte\Environment\EnvironmentResolver;
use Laragear\Dte\Gateways\BoletaRestGateway;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Xml\XmlSigner;
use Laragear\Rut\Rut;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\DatabaseTestCase;

class BoletaRestGatewayTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make('cache')->flush();

        HttpFacade::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        $this->app->make('cache')->flush();

        parent::tearDown();
    }

    protected function makeGateway(DteEnvironment $environment = DteEnvironment::Production): BoletaRestGateway
    {
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

        $this->instance(EnvironmentResolver::class, $this->makeEnvironmentResolver($environment));

        return $this->app->make(BoletaRestGateway::class);
    }

    protected function makeEnvironmentResolver(DteEnvironment $environment): EnvironmentResolver
    {
        $app = Mockery::mock(Application::class, static function (MockInterface $mock) use ($environment): void {
            $mock->allows('environment')
                ->with(DteEnvironment::Production->value)
                ->andReturn($environment === DteEnvironment::Production);
            $mock->allows('environment')
                ->withNoArgs()
                ->andReturn($environment->value);
        });

        return new EnvironmentResolver(
            new Repository([
                'dte' => ['environment' => $environment->value],
            ]),
            $app
        );
    }

    protected function makeEnvelope(): SiiDteEnvelope
    {
        return SiiDteEnvelope::factory()->create([
            'status' => EnvelopeStatus::Signed,
        ]);
    }

    public function test_uploads_envelope_and_returns_track_id_in_certification(): void
    {
        $envelope = $this->makeEnvelope();

        HttpFacade::fake([
            'https://apicert.sii.cl/recursos/v1/boleta.electronica.semilla' => HttpFacade::response(
                '<RESPUESTA><RESP_BODY><SEMILLA>030530912644</SEMILLA></RESP_BODY></RESPUESTA>',
                200,
            ),
            'https://apicert.sii.cl/recursos/v1/boleta.electronica.token' => HttpFacade::response(
                '<RESPUESTATOKEN><TOKEN>P7VQKYLDNHJGP</TOKEN></RESPUESTATOKEN>',
                200,
            ),
            'https://pangal.sii.cl/recursos/v1/boleta.electronica.envio' => HttpFacade::response(
                ['trackid' => 1014, 'estado' => 'REC', 'codigo' => 0],
                200,
            ),
        ]);

        $gateway = $this->makeGateway(DteEnvironment::Certification);
        $trackId = $gateway->upload($envelope, '<EnvioBoleta>...</EnvioBoleta>');

        static::assertSame('1014', $trackId);
    }

    public function test_uploads_envelope_and_returns_track_id_in_production(): void
    {
        $envelope = $this->makeEnvelope();

        HttpFacade::fake([
            'https://api.sii.cl/recursos/v1/boleta.electronica.semilla' => HttpFacade::response(
                '<RESPUESTA><RESP_BODY><SEMILLA>12345</SEMILLA></RESP_BODY></RESPUESTA>',
                200,
            ),
            'https://api.sii.cl/recursos/v1/boleta.electronica.token' => HttpFacade::response(
                '<RESPUESTATOKEN><TOKEN>TOKEN_PROD</TOKEN></RESPUESTATOKEN>',
                200,
            ),
            'https://rahue.sii.cl/recursos/v1/boleta.electronica.envio' => HttpFacade::response(
                ['trackid' => 9999, 'estado' => 'REC', 'codigo' => 0],
                200,
            ),
        ]);

        $gateway = $this->makeGateway(DteEnvironment::Production);
        $trackId = $gateway->upload($envelope, '<EnvioBoleta>...</EnvioBoleta>');

        static::assertSame('9999', $trackId);
    }

    public function test_sends_issuer_and_sender_rut_in_upload_request(): void
    {
        $envelope = $this->makeEnvelope();

        HttpFacade::fake([
            'https://api.sii.cl/recursos/v1/boleta.electronica.semilla' => HttpFacade::response(
                '<RESPUESTA><RESP_BODY><SEMILLA>030530912644</SEMILLA></RESP_BODY></RESPUESTA>',
                200,
            ),
            'https://api.sii.cl/recursos/v1/boleta.electronica.token' => HttpFacade::response(
                '<RESPUESTATOKEN><TOKEN>P7VQKYLDNHJGP</TOKEN></RESPUESTATOKEN>',
                200,
            ),
            'https://rahue.sii.cl/recursos/v1/boleta.electronica.envio' => HttpFacade::response(
                ['trackid' => 111, 'estado' => 'REC', 'codigo' => 0],
                200,
            ),
        ]);

        $gateway = $this->makeGateway();
        $gateway->upload($envelope, '<EnvioBoleta/>');

        HttpFacade::assertSent(function (Request $request) use ($envelope): bool {
            if ($request->url() === 'https://rahue.sii.cl/recursos/v1/boleta.electronica.envio') {
                static::assertStringContainsString($envelope->issuer_rut->num, $request->body());
                static::assertStringContainsString($envelope->sender_rut->num, $request->body());
            }

            return true;
        });
    }

    public function test_sends_exactly_two_lines_for_token_xml(): void
    {
        $envelope = $this->makeEnvelope();

        HttpFacade::fake([
            'https://api.sii.cl/recursos/v1/boleta.electronica.semilla' => HttpFacade::response(
                '<RESPUESTA><RESP_BODY><SEMILLA>030530912644</SEMILLA></RESP_BODY></RESPUESTA>',
                200,
            ),
            'https://api.sii.cl/recursos/v1/boleta.electronica.token' => HttpFacade::response(
                '<RESPUESTATOKEN><TOKEN>P7VQKYLDNHJGP</TOKEN></RESPUESTATOKEN>',
                200,
            ),
            'https://rahue.sii.cl/recursos/v1/boleta.electronica.envio' => HttpFacade::response(
                ['trackid' => 111, 'estado' => 'REC', 'codigo' => 0],
                200,
            ),
        ]);

        $gateway = $this->makeGateway();
        $gateway->upload($envelope, '<EnvioBoleta/>');

        HttpFacade::assertSent(function (Request $request): bool {
            if ($request->url() === 'https://api.sii.cl/recursos/v1/boleta.electronica.token') {
                $body = $request->body();
                static::assertStringStartsWith(
                    '<?xml version="1.0" encoding="UTF-8"?>'
                    ."\n"
                    .'<getToken ID="GetToken" signed="true"><item><Semilla>030530912644</Semilla></item></getToken>',
                    $body,
                );
                // No trailing newlines or extra spaces
                static::assertSame(1, substr_count($body, "\n"));
                static::assertSame(0, substr_count($body, "\r"));
            }

            return true;
        });
    }

    public function test_throws_when_seed_request_fails(): void
    {
        $envelope = $this->makeEnvelope();

        HttpFacade::fake([
            'https://api.sii.cl/recursos/v1/boleta.electronica.semilla' => HttpFacade::response(null, 500),
        ]);

        $gateway = $this->makeGateway();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Failed to get seed from SII.');

        $gateway->upload($envelope, '<EnvioBoleta/>');
    }

    public function test_throws_when_seed_response_is_invalid(): void
    {
        $envelope = $this->makeEnvelope();

        HttpFacade::fake([
            'https://api.sii.cl/recursos/v1/boleta.electronica.semilla' => HttpFacade::response(
                '<RESPUESTA><RESP_BODY></RESP_BODY></RESPUESTA>',
                200,
            ),
        ]);

        $gateway = $this->makeGateway();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid seed response from SII.');

        $gateway->upload($envelope, '<EnvioBoleta/>');
    }

    public function test_throws_when_token_request_fails(): void
    {
        $envelope = $this->makeEnvelope();

        HttpFacade::fake([
            'https://api.sii.cl/recursos/v1/boleta.electronica.semilla' => HttpFacade::response(
                '<RESPUESTA><RESP_BODY><SEMILLA>030530912644</SEMILLA></RESP_BODY></RESPUESTA>',
                200,
            ),
            'https://api.sii.cl/recursos/v1/boleta.electronica.token' => HttpFacade::response(null, 500),
        ]);

        $gateway = $this->makeGateway();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Failed to get token from SII.');

        $gateway->upload($envelope, '<EnvioBoleta/>');
    }

    public function test_throws_when_token_response_is_invalid(): void
    {
        $envelope = $this->makeEnvelope();

        HttpFacade::fake([
            'https://api.sii.cl/recursos/v1/boleta.electronica.semilla' => HttpFacade::response(
                '<RESPUESTA><RESP_BODY><SEMILLA>030530912644</SEMILLA></RESP_BODY></RESPUESTA>',
                200,
            ),
            'https://api.sii.cl/recursos/v1/boleta.electronica.token' => HttpFacade::response(
                '<RESPUESTATOKEN></RESPUESTATOKEN>',
                200,
            ),
        ]);

        $gateway = $this->makeGateway();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid token response from SII.');

        $gateway->upload($envelope, '<EnvioBoleta/>');
    }

    public function test_throws_on_unauthorized_upload_response(): void
    {
        $envelope = $this->makeEnvelope();

        HttpFacade::fake([
            'https://api.sii.cl/recursos/v1/boleta.electronica.semilla' => HttpFacade::response(
                '<RESPUESTA><RESP_BODY><SEMILLA>030530912644</SEMILLA></RESP_BODY></RESPUESTA>',
                200,
            ),
            'https://api.sii.cl/recursos/v1/boleta.electronica.token' => HttpFacade::response(
                '<RESPUESTATOKEN><TOKEN>P7VQKYLDNHJGP</TOKEN></RESPUESTATOKEN>',
                200,
            ),
            'https://rahue.sii.cl/recursos/v1/boleta.electronica.envio' => HttpFacade::response(null, 401),
        ]);

        $gateway = $this->makeGateway();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('SII Upload rejected the authentication token (401).');

        $gateway->upload($envelope, '<EnvioBoleta/>');
    }

    public function test_throws_on_failed_upload_response(): void
    {
        $envelope = $this->makeEnvelope();

        HttpFacade::fake([
            'https://api.sii.cl/recursos/v1/boleta.electronica.semilla' => HttpFacade::response(
                '<RESPUESTA><RESP_BODY><SEMILLA>030530912644</SEMILLA></RESP_BODY></RESPUESTA>',
                200,
            ),
            'https://api.sii.cl/recursos/v1/boleta.electronica.token' => HttpFacade::response(
                '<RESPUESTATOKEN><TOKEN>P7VQKYLDNHJGP</TOKEN></RESPUESTATOKEN>',
                200,
            ),
            'https://rahue.sii.cl/recursos/v1/boleta.electronica.envio' => HttpFacade::response(null, 500),
        ]);

        $gateway = $this->makeGateway();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('SII Upload request failed with status 500.');

        $gateway->upload($envelope, '<EnvioBoleta/>');
    }

    public function test_throws_when_upload_response_has_no_track_id(): void
    {
        $envelope = $this->makeEnvelope();

        HttpFacade::fake([
            'https://api.sii.cl/recursos/v1/boleta.electronica.semilla' => HttpFacade::response(
                '<RESPUESTA><RESP_BODY><SEMILLA>030530912644</SEMILLA></RESP_BODY></RESPUESTA>',
                200,
            ),
            'https://api.sii.cl/recursos/v1/boleta.electronica.token' => HttpFacade::response(
                '<RESPUESTATOKEN><TOKEN>P7VQKYLDNHJGP</TOKEN></RESPUESTATOKEN>',
                200,
            ),
            'https://rahue.sii.cl/recursos/v1/boleta.electronica.envio' => HttpFacade::response(
                ['estado' => 'REC', 'codigo' => 0], // no trackid
                200,
            ),
        ]);

        $gateway = $this->makeGateway();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('SII Upload response did not contain a valid TrackID.');

        $gateway->upload($envelope, '<EnvioBoleta/>');
    }

    public function test_throws_when_certificate_cannot_be_resolved(): void
    {
        $envelope = $this->makeEnvelope();

        HttpFacade::fake([
            'https://api.sii.cl/recursos/v1/boleta.electronica.semilla' => HttpFacade::response(
                '<RESPUESTA><RESP_BODY><SEMILLA>030530912644</SEMILLA></RESP_BODY></RESPUESTA>',
                200,
            ),
        ]);

        $this->mock(CertificateResolverInterface::class)->expects('resolve')->andReturn(null);

        $this->instance(EnvironmentResolver::class, $this->makeEnvironmentResolver(DteEnvironment::Production));
        $gateway = $this->app->make(BoletaRestGateway::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs(
            'No digital certificate resolved for issuer '.$envelope->issuer_rut->formatBasic(),
        );

        $gateway->upload($envelope, '<EnvioBoleta/>');
    }

    public function test_gets_track_status(): void
    {
        $envelope = $this->makeEnvelope();
        $envelope->track_id = '12345';

        HttpFacade::fake([
            'https://api.sii.cl/recursos/v1/boleta.electronica.semilla' => HttpFacade::response(
                '<RESPUESTA><RESP_BODY><SEMILLA>030530912644</SEMILLA></RESP_BODY></RESPUESTA>',
                200,
            ),
            'https://api.sii.cl/recursos/v1/boleta.electronica.token' => HttpFacade::response(
                '<RESPUESTATOKEN><TOKEN>P7VQKYLDNHJGP</TOKEN></RESPUESTATOKEN>',
                200,
            ),
            "https://api.sii.cl/recursos/v1/boleta.electronica.envio/{$envelope->issuer_rut->num}-{$envelope->issuer_rut->vd}-12345" => HttpFacade::response(
                ['trackid' => 12345, 'estado' => 'EPR'],
                200,
            ),
        ]);

        $gateway = $this->makeGateway();
        $status = $gateway->trackStatus($envelope);

        static::assertSame('EPR', $status->status);
        static::assertSame(['trackid' => 12345, 'estado' => 'EPR'], $status->raw);
    }

    public function test_gets_document_status(): void
    {
        $dte = SiiDte::factory()->create([
            'issuer_rut' => '76.543.210-K',
            'receiver_rut' => '66.666.666-6',
            'document_type' => DteType::Receipt,
            'folio' => 500,
            'amount_total' => 1500,
            'created_at' => '2026-05-01 10:00:00',
        ]);

        HttpFacade::fake([
            'https://api.sii.cl/recursos/v1/boleta.electronica.semilla' => HttpFacade::response(
                '<RESPUESTA><RESP_BODY><SEMILLA>030530912644</SEMILLA></RESP_BODY></RESPUESTA>',
                200,
            ),
            'https://api.sii.cl/recursos/v1/boleta.electronica.token' => HttpFacade::response(
                '<RESPUESTATOKEN><TOKEN>P7VQKYLDNHJGP</TOKEN></RESPUESTATOKEN>',
                200,
            ),
            'https://api.sii.cl/recursos/v1/boleta.electronica/76543210-K-39-500/estado*' => HttpFacade::response(
                ['estado' => 'DOK'],
                200,
            ),
        ]);

        $gateway = $this->makeGateway();
        $status = $gateway->documentStatus($dte);

        static::assertEquals(['estado' => 'DOK'], $status);
    }

    public function test_get_token_caches_result(): void
    {
        $gateway = $this->makeGateway(DteEnvironment::Production);

        HttpFacade::fake([
            'https://api.sii.cl/recursos/v1/boleta.electronica.semilla' => HttpFacade::response(
                '<RESPUESTA><RESP_BODY><SEMILLA>030530912644</SEMILLA></RESP_BODY></RESPUESTA>',
                200,
            ),
            'https://api.sii.cl/recursos/v1/boleta.electronica.token' => HttpFacade::response(
                '<RESPUESTATOKEN><TOKEN>P7VQKYLDNHJGP</TOKEN></RESPUESTATOKEN>',
                200,
            ),
        ]);

        $issuer = $this->makeEnvelope()->issuer_rut;

        $first = $gateway->getToken($issuer);
        $second = $gateway->getToken($issuer);

        static::assertSame('P7VQKYLDNHJGP', $first);
        static::assertSame('P7VQKYLDNHJGP', $second);

        // Only one seed round-trip: the second call is served from cache.
        HttpFacade::assertSentCount(2);
        HttpFacade::assertSent(function (Request $request): bool {
            return str_contains($request->url(), 'boleta.electronica.semilla');
        });
    }

    public function test_get_token_re_authenticates_when_cached_token_expired(): void
    {
        $gateway = $this->makeGateway(DteEnvironment::Production);

        HttpFacade::fake([
            'https://api.sii.cl/recursos/v1/boleta.electronica.semilla' => HttpFacade::response(
                '<RESPUESTA><RESP_BODY><SEMILLA>030530912644</SEMILLA></RESP_BODY></RESPUESTA>',
                200,
            ),
            'https://api.sii.cl/recursos/v1/boleta.electronica.token' => HttpFacade::response(
                '<RESPUESTATOKEN><TOKEN>P7VQKYLDNHJGP</TOKEN></RESPUESTATOKEN>',
                200,
            ),
        ]);

        $issuer = $this->makeEnvelope()->issuer_rut;

        // Seed a short-lived expired token so the gateway must re-authenticate.
        $this->app->make('cache')->put(
            'dte|rest_token|business:'.$issuer->formatRaw(),
            Token::make('STALE', time() - 3600)->toJson(),
        );

        $token = $gateway->getToken($issuer);

        static::assertSame('P7VQKYLDNHJGP', $token);
    }

    public function test_get_token_touches_cache_on_reuse(): void
    {
        $issuer = $this->makeEnvelope()->issuer_rut;
        $cacheKey = 'dte|rest_token|business:'.$issuer->formatRaw();

        $cache = Mockery::mock(CacheRepository::class);
        $cache->expects('get')->with($cacheKey, '{"value":"","expires_at":0}')->andReturn(Token::make('CACHED',
            time() + 3600)->toJson());
        $cache->expects('touch')->with($cacheKey, 3600);

        $this->instance(CacheRepository::class, $cache);

        $gateway = $this->makeGateway(DteEnvironment::Production);

        static::assertSame('CACHED', $gateway->getToken($issuer));
    }

    public function test_get_token_throws_if_ttl_exceeds_3600(): void
    {
        $gateway = $this->makeGateway(DteEnvironment::Production);

        $this->config('dte.soap.token_ttl', 7200);

        HttpFacade::fake([
            'https://api.sii.cl/recursos/v1/boleta.electronica.semilla' => HttpFacade::response(
                '<RESPUESTA><RESP_BODY><SEMILLA>030530912644</SEMILLA></RESP_BODY></RESPUESTA>',
                200,
            ),
            'https://api.sii.cl/recursos/v1/boleta.electronica.token' => HttpFacade::response(
                '<RESPUESTATOKEN><TOKEN>P7VQKYLDNHJGP</TOKEN></RESPUESTATOKEN>',
                200,
            ),
        ]);

        $this->expectException(InvalidArgumentException::class);

        $gateway->getToken($this->makeEnvelope()->issuer_rut);
    }

    public function test_cache_key_uses_rest_prefix(): void
    {
        $issuer = $this->makeEnvelope()->issuer_rut;

        $gateway = $this->makeGateway(DteEnvironment::Production);

        HttpFacade::fake([
            'https://api.sii.cl/recursos/v1/boleta.electronica.semilla' => HttpFacade::response(
                '<RESPUESTA><RESP_BODY><SEMILLA>030530912644</SEMILLA></RESP_BODY></RESPUESTA>',
                200,
            ),
            'https://api.sii.cl/recursos/v1/boleta.electronica.token' => HttpFacade::response(
                '<RESPUESTATOKEN><TOKEN>P7VQKYLDNHJGP</TOKEN></RESPUESTATOKEN>',
                200,
            ),
        ]);

        $gateway->getToken($issuer);

        static::assertSame(
            'P7VQKYLDNHJGP',
            Token::fromJson(
                $this->app->make('cache')->get('dte|rest_token|business:'.$issuer->formatRaw())
            )->value
        );
    }

    public function test_document_status_uses_issued_on_not_created_at(): void
    {
        $dte = SiiDte::factory()->create([
            'issuer_rut' => '76.543.210-K',
            'receiver_rut' => '66.666.666-6',
            'document_type' => DteType::Receipt,
            'folio' => 900,
            'amount_total' => 1500,
            'issued_on' => '2026-05-02 10:00:00',
            'created_at' => '2026-05-01 10:00:00',
        ]);

        HttpFacade::fake([
            'https://api.sii.cl/recursos/v1/boleta.electronica.semilla' => HttpFacade::response(
                '<RESPUESTA><RESP_BODY><SEMILLA>030530912644</SEMILLA></RESP_BODY></RESPUESTA>',
                200,
            ),
            'https://api.sii.cl/recursos/v1/boleta.electronica.token' => HttpFacade::response(
                '<RESPUESTATOKEN><TOKEN>P7VQKYLDNHJGP</TOKEN></RESPUESTATOKEN>',
                200,
            ),
            'https://api.sii.cl/recursos/v1/boleta.electronica*' => HttpFacade::response(['estado' => 'DOK'], 200),
        ]);

        $gateway = $this->makeGateway(DteEnvironment::Production);
        $gateway->documentStatus($dte);

        HttpFacade::assertSent(function (Request $request): bool {
            if (str_contains($request->url(), '/estado')) {
                static::assertStringContainsString('fechaEmision=02-05-2026', $request->url());
            }

            return true;
        });
    }

    /*
     |--------------------------------------------------------------------------
     | Early Returns for Missing Base URL (Non-production environments)
     |--------------------------------------------------------------------------
     */

    public function test_returns_early_without_base_url_on_get_token(): void
    {
        $gateway = $this->makeGateway(DteEnvironment::Local);
        static::assertSame('fake-token', $gateway->getToken(Mockery::mock(Rut::class)));
    }

    public function test_returns_early_without_base_url_on_upload(): void
    {
        $gateway = $this->makeGateway(DteEnvironment::Local);
        $envelope = SiiDteEnvelope::factory()->create();
        static::assertSame('fake-track-id-'.$envelope->getKey(), $gateway->upload($envelope, 'xml'));
    }

    public function test_returns_early_without_base_url_on_track_status(): void
    {
        $gateway = $this->makeGateway(DteEnvironment::Local);
        $envelope = SiiDteEnvelope::factory()->create();
        $response = $gateway->trackStatus($envelope);
        static::assertSame('REC', $response->status);
        static::assertSame('Faked status', $response->raw['glosa']);
    }

    public function test_returns_early_without_base_url_on_document_status(): void
    {
        $gateway = $this->makeGateway(DteEnvironment::Local);
        $dte = SiiDte::factory()->create();
        $response = $gateway->documentStatus($dte);
        static::assertSame('REC', $response['estado']);
        static::assertSame('Faked status', $response['glosa']);
    }

    /*
     |--------------------------------------------------------------------------
     | Http Error Exceptions
     |--------------------------------------------------------------------------
     */

    public function test_throws_exception_on_track_status_401(): void
    {
        $gateway = $this->makeGateway(DteEnvironment::Production);
        $envelope = SiiDteEnvelope::factory()->create();

        HttpFacade::fake([
            'https://api.sii.cl/recursos/v1/boleta.electronica.semilla' => HttpFacade::response('<RESPUESTA><RESP_BODY><SEMILLA>030530912644</SEMILLA></RESP_BODY></RESPUESTA>'),
            'https://api.sii.cl/recursos/v1/boleta.electronica.token' => HttpFacade::response('<RESPUESTA><RESP_BODY><TOKEN>random-token</TOKEN></RESP_BODY></RESPUESTA>'),
            'https://api.sii.cl/recursos/v1/boleta.electronica.envio/*' => HttpFacade::response('', 401),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectexceptionMessageIs('SII Status Query rejected the authentication token (401).');

        $gateway->trackStatus($envelope);
    }

    public function test_throws_exception_on_track_status_500(): void
    {
        $gateway = $this->makeGateway(DteEnvironment::Production);
        $envelope = SiiDteEnvelope::factory()->create();

        HttpFacade::fake([
            'https://api.sii.cl/recursos/v1/boleta.electronica.semilla' => HttpFacade::response('<RESPUESTA><RESP_BODY><SEMILLA>030530912644</SEMILLA></RESP_BODY></RESPUESTA>'),
            'https://api.sii.cl/recursos/v1/boleta.electronica.token' => HttpFacade::response('<RESPUESTA><RESP_BODY><TOKEN>random-token</TOKEN></RESP_BODY></RESPUESTA>'),
            'https://api.sii.cl/recursos/v1/boleta.electronica.envio/*' => HttpFacade::response('', 500),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectexceptionMessageIs('SII Status Query failed with status 500.');

        $gateway->trackStatus($envelope);
    }

    public function test_throws_exception_on_document_status_401(): void
    {
        $gateway = $this->makeGateway(DteEnvironment::Production);
        $dte = SiiDte::factory()->create(['document_type' => DteType::Receipt]);

        HttpFacade::fake([
            'https://api.sii.cl/recursos/v1/boleta.electronica.semilla' => HttpFacade::response('<RESPUESTA><RESP_BODY><SEMILLA>030530912644</SEMILLA></RESP_BODY></RESPUESTA>'),
            'https://api.sii.cl/recursos/v1/boleta.electronica.token' => HttpFacade::response('<RESPUESTA><RESP_BODY><TOKEN>random-token</TOKEN></RESP_BODY></RESPUESTA>'),
            'https://api.sii.cl/recursos/v1/boleta.electronica*' => HttpFacade::response('', 401),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectexceptionMessageIs('SII Document Status Query rejected the authentication token (401).');

        $gateway->documentStatus($dte);
    }

    public function test_throws_exception_on_document_status_500(): void
    {
        $gateway = $this->makeGateway(DteEnvironment::Production);
        $dte = SiiDte::factory()->create(['document_type' => DteType::Receipt]);

        HttpFacade::fake([
            'https://api.sii.cl/recursos/v1/boleta.electronica.semilla' => HttpFacade::response('<RESPUESTA><RESP_BODY><SEMILLA>030530912644</SEMILLA></RESP_BODY></RESPUESTA>'),
            'https://api.sii.cl/recursos/v1/boleta.electronica.token' => HttpFacade::response('<RESPUESTA><RESP_BODY><TOKEN>random-token</TOKEN></RESP_BODY></RESPUESTA>'),
            'https://api.sii.cl/recursos/v1/boleta.electronica*' => HttpFacade::response('', 500),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectexceptionMessageIs('SII Document Status Query failed with status 500.');

        $gateway->documentStatus($dte);
    }
}
