<?php

namespace Tests\Unit\Gateways;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Dte\Contracts\CertificateResolverInterface;
use Laragear\Dte\Enums\DteEnvironment;
use Laragear\Dte\Environment\EnvironmentResolver;
use Laragear\Dte\Gateways\RestAuthGateway;
use Laragear\Dte\Proxies\OpenSslProxy;
use Laragear\Rut\Rut;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class RestAuthGatewayTest extends TestCase
{
    /**
     * Mock OpenSSL to avoid real certificate operations across all tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(OpenSslProxy::class, static function (MockInterface $mock): void {
            $mock->expects('readPkcs12String')
                ->between(0, 1)
                ->andReturn([
                    'pkey' => '-----BEGIN PRIVATE KEY-----fake-----END PRIVATE KEY-----', 'cert' => 'fake-cert-pem',
                ]);
            $mock->expects('privateKeyDetails')
                ->between(0, 1)
                ->andReturn(['rsa' => ['n' => 'modulus-base64', 'e' => 'AQAB']]);
            $mock->expects('sign')
                ->between(0, 1)->andReturn('signature-b64');
        });
    }

    protected function tearDown(): void
    {
        Http::fake([]);

        parent::tearDown();
    }

    /**
     * Build an environment resolver for the given DTE environment.
     */
    protected function makeEnvironmentResolver(DteEnvironment $environment): EnvironmentResolver
    {
        $app = Mockery::mock(
            Application::class,
            static function (MockInterface $mock) use ($environment): void {
                $mock->allows('environment')
                    ->with(DteEnvironment::Production->value)
                    ->andReturn($environment === DteEnvironment::Production);
                $mock->allows('environment')
                    ->withNoArgs()
                    ->andReturn($environment->value);
            }
        );

        return new EnvironmentResolver(new Repository([
            'dte' => ['environment' => $environment->value],
        ]), $app);
    }

    public function test_returns_fake_token_when_no_base_url(): void
    {
        $this->instance(EnvironmentResolver::class, $this->makeEnvironmentResolver(DteEnvironment::Local));

        $gateway = $this->app->make(RestAuthGateway::class);

        static::assertSame('fake-token', $gateway->fetchToken(Rut::parse('76123456-0')));
    }

    public function test_fetches_seed_and_exchanges_for_token(): void
    {
        $issuer = Rut::parse('76.123.456-7');
        $authUrl = 'https://palena.sii.cl';
        $seed = '000000001042';
        $token = 'sii-rest-token-xyz';

        // Seed response XML and Token response XML.
        $seedXml = '<SEMILLA>'.$seed.'</SEMILLA>';
        $tokenXml = '<TOKEN>'.$token.'</TOKEN>';

        $certificate = new DigitalCertificate('fake-pkcs12', 'secret');

        // Setup certificate resolver.
        $this->mock(CertificateResolverInterface::class,
            static function (MockInterface $mock) use ($certificate): void {
                $mock->expects('resolve')->andReturn($certificate);
            });

        $this->instance(
            EnvironmentResolver::class,
            $this->makeEnvironmentResolver(DteEnvironment::Production)
        );

        Http::fake([
            '*semilla*' => Http::response($seedXml),
            '*token*' => Http::response($tokenXml),
        ]);

        $result = $this->app->make(RestAuthGateway::class)->fetchToken($issuer, $authUrl);

        static::assertSame($token, $result);
    }

    public function test_throws_on_failed_seed_request(): void
    {
        $issuer = Rut::parse('76.123.456-7');
        $authUrl = 'https://palena.sii.cl';

        $this->instance(
            EnvironmentResolver::class,
            $this->makeEnvironmentResolver(DteEnvironment::Production)
        );

        Http::fake(['*' => Http::response('error', 500)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Failed to get seed from SII.');

        $this->app->make(RestAuthGateway::class)->fetchToken($issuer, $authUrl);
    }

    public function test_throws_on_invalid_seed_response(): void
    {
        $issuer = Rut::parse('76.123.456-7');
        $authUrl = 'https://palena.sii.cl';

        $this->instance(
            EnvironmentResolver::class,
            $this->makeEnvironmentResolver(DteEnvironment::Production)
        );

        // Valid HTTP but no SEMILLA element.
        Http::fake(['*semilla*' => Http::response('<ROOT><DATA>none</DATA></ROOT>')]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid seed response from SII.');

        $this->app->make(RestAuthGateway::class)->fetchToken($issuer, $authUrl);
    }

    public function test_throws_on_failed_token_request(): void
    {
        $issuer = Rut::parse('76.123.456-7');
        $authUrl = 'https://palena.sii.cl';

        $this->instance(
            EnvironmentResolver::class,
            $this->makeEnvironmentResolver(DteEnvironment::Production)
        );

        Http::fake([
            '*semilla*' => Http::response('<SEMILLA>123</SEMILLA>'),
            '*token*' => Http::response('bad gateway', 502),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Failed to get token from SII.');

        $this->app->make(RestAuthGateway::class)->fetchToken($issuer, $authUrl);
    }

    public function test_throws_on_invalid_token_response(): void
    {
        $issuer = Rut::parse('76.123.456-7');
        $authUrl = 'https://palena.sii.cl';

        $this->instance(
            EnvironmentResolver::class,
            $this->makeEnvironmentResolver(DteEnvironment::Production)
        );

        // Valid HTTP but no TOKEN element.
        Http::fake([
            '*semilla*' => Http::response('<SEMILLA>123</SEMILLA>'),
            '*token*' => Http::response('<ROOT><DATA>none</DATA></ROOT>'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid token response from SII.');

        $this->app->make(RestAuthGateway::class)->fetchToken($issuer, $authUrl);
    }

    public function test_throws_when_no_certificate_resolved(): void
    {
        $issuer = Rut::parse('76.123.456-7');
        $authUrl = 'https://palena.sii.cl';

        $this->instance(
            EnvironmentResolver::class,
            $this->makeEnvironmentResolver(DteEnvironment::Production)
        );

        $this->mock(CertificateResolverInterface::class, static function (MockInterface $mock) use ($issuer): void {
            $mock->expects('resolve')->with($issuer)->andReturnNull();
        });

        Http::fake(['*semilla*' => Http::response('<SEMILLA>123</SEMILLA>')]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('No digital certificate resolved for issuer 76123456-7');

        $this->app->make(RestAuthGateway::class)->fetchToken($issuer, $authUrl);
    }

    public function test_sends_correct_http_requests(): void
    {
        $issuer = Rut::parse('76.123.456-7');
        $authUrl = 'https://palena.sii.cl';
        $seed = '000000001042';
        $token = 'sii-rest-token-xyz';

        $certificate = new DigitalCertificate('fake-pkcs12', 'secret');

        $this->mock(CertificateResolverInterface::class,
            static function (MockInterface $mock) use ($certificate): void {
                $mock->expects('resolve')->andReturn($certificate);
            });

        $this->instance(
            EnvironmentResolver::class,
            $this->makeEnvironmentResolver(DteEnvironment::Production)
        );

        Http::fake([
            '*semilla*' => Http::response('<SEMILLA>'.$seed.'</SEMILLA>'),
            '*token*' => Http::response('<TOKEN>'.$token.'</TOKEN>'),
        ]);

        $this->app->make(RestAuthGateway::class)->fetchToken($issuer, $authUrl);

        // Verify both requests were sent to the correct URLs.
        Http::assertSent(function (Request $request): bool {
            return str_contains($request->url(), 'boleta.electronica.semilla');
        });
        Http::assertSent(function (Request $request): bool {
            return str_contains($request->url(), 'boleta.electronica.token');
        });
    }
}
