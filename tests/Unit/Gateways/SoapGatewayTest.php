<?php

namespace Tests\Unit\Gateways;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Sleep;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Dte\Contracts\CertificateResolverInterface;
use Laragear\Dte\Data\Token;
use Laragear\Dte\Enums\DteEnvironment;
use Laragear\Dte\Environment\EnvironmentResolver;
use Laragear\Dte\Gateways\Exceptions\SiiAuthenticationException;
use Laragear\Dte\Gateways\Exceptions\SiiSeedUnavailableException;
use Laragear\Dte\Gateways\SoapClientFactory;
use Laragear\Dte\Gateways\SoapGateway;
use Laragear\Dte\Proxies\OpenSslProxy;
use Laragear\Dte\Proxies\SoapProxy;
use Laragear\Dte\Support\XmlDomFactory;
use Laragear\Dte\Xml\XmlSigner;
use Laragear\Rut\Rut;
use Mockery;
use Mockery\MockInterface;
use ReflectionMethod;
use RuntimeException;
use SoapClient;
use SoapFault;
use SoapHeader;
use Tests\TestCase;

class SoapGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
    }

    protected function tearDown(): void
    {
        Sleep::fake(false);

        parent::tearDown();
    }

    /**
     * Build a gateway whose retry sleeping is disabled, for auth/backoff tests.
     */
    protected function makeAuthGateway(): SoapGateway
    {
        $certificate = new DigitalCertificate('fake', 'secret');

        $this
            ->mock(CertificateResolverInterface::class)
            ->expects('resolve')
            ->zeroOrMoreTimes()
            ->andReturn($certificate);

        $this->instance(EnvironmentResolver::class, $this->makeEnvironmentResolver(DteEnvironment::Production));

        return $this->app->make(SoapGateway::class);
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

        return new EnvironmentResolver(new Repository([
            'dte' => ['environment' => $environment->value],
        ]), $app);
    }

    /**
     * Mock a distinct SOAP client per WSDL (seed and token).
     *
     * @param  callable(SoapClient):void  $seedSetup
     * @param  callable(SoapClient):void  $tokenSetup
     * @param  list<string>  $buildCalls
     */
    protected function mockSoapFlow(callable $seedSetup, callable $tokenSetup, array &$buildCalls = []): void
    {
        $this->mock(SoapProxy::class,
            static function (MockInterface $mock) use ($seedSetup, $tokenSetup, &$buildCalls): void {
                $currentWsdl = null;

                $mock->expects('withWsdl')->zeroOrMoreTimes()->andReturnUsing(
                    static function (string $wsdl) use (&$currentWsdl, $mock) {
                        $currentWsdl = $wsdl;

                        return $mock;
                    }
                );

                $mock->expects('build')->zeroOrMoreTimes()->andReturnUsing(
                    static function () use (&$currentWsdl, &$buildCalls, $seedSetup, $tokenSetup) {
                        $buildCalls[] = $currentWsdl;

                        $client = Mockery::mock(SoapClient::class);

                        if (str_contains($currentWsdl, 'CrSeed')) {
                            $seedSetup($client);
                        } else {
                            $tokenSetup($client);
                        }

                        return $client;
                    }
                );
            });
    }

    /**
     * Configure the OpenSslProxy used to sign the getToken XML.
     */
    protected function mockOpenSsl(): void
    {
        $this->mock(OpenSslProxy::class, static function (MockInterface $mock): void {
            $mock->expects('readPkcs12String')
                ->zeroOrMoreTimes()
                ->andReturn(['pkey' => 'private-key', 'cert' => 'certificate']);
            $mock->expects('sign')
                ->zeroOrMoreTimes()
                ->andReturn('signature-b64');
            $mock->expects('privateKeyDetails')
                ->zeroOrMoreTimes()
                ->andReturn(['rsa' => ['n' => 'modulus', 'e' => 'AQAB']]);
        });
    }

    public function test_soap_client_uses_wsdl_cache_memory(): void
    {
        $this->mock(SoapProxy::class, static function (MockInterface $mock): void {
            $mock->expects('withWsdl')->zeroOrMoreTimes()->andReturnSelf();
            $mock->expects('build')->zeroOrMoreTimes()->andReturn(Mockery::mock(SoapClient::class));
        });

        $gateway = new class($this->app->make(Filesystem::class), $this->app->make(CertificateResolverInterface::class), $this->app->make(EnvironmentResolver::class), $this->app->make(SoapProxy::class), $this->app->make(OpenSslProxy::class), $this->app->make(XmlDomFactory::class), $this->app->make(XmlSigner::class), $this->app->make(SoapClientFactory::class)) extends SoapGateway {
            public function exposeNewSoapClient(string $wsdl): SoapClient
            {
                return $this->newSoapClient($wsdl);
            }
        };

        $gateway->exposeNewSoapClient('https://example.test/DTEWS/CrSeed.jws?WSDL');
    }

    public function test_authenticate_calls_cr_seed_then_get_token_from_seed(): void
    {
        $issuer = Rut::parse('76.123.456-7');

        $seedResponse = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>00</SII:ESTADO></SII:RESP_HDR>'
            .'<SII:RESP_BODY><SEMILLA>000000001042</SEMILLA></SII:RESP_BODY>'
            .'</SII:RESPUESTA>';

        $tokenResponse = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>00</SII:ESTADO></SII:RESP_HDR>'
            .'<SII:RESP_BODY><TOKEN>sii-token</TOKEN></SII:RESP_BODY>'
            .'</SII:RESPUESTA>';

        $buildCalls = [];

        $this->makeAuthGateway();

        $this->mockSoapFlow(
            static function (SoapClient $client) use ($seedResponse): void {
                $client->expects('getSeed')->andReturn($seedResponse);
            },
            static function (SoapClient $client) use ($tokenResponse): void {
                $client->expects('getToken')->andReturn($tokenResponse);
            },
            $buildCalls
        );

        $this->mockOpenSsl();

        $gateway = $this->app->make(SoapGateway::class);

        $token = $gateway->authenticate($issuer);

        static::assertSame('sii-token', $token);

        static::assertSame([
            'https://palena.sii.cl/DTEWS/CrSeed.jws?WSDL',
            'https://palena.sii.cl/DTEWS/GetTokenFromSeed.jws?WSDL',
        ], $buildCalls);
    }

    public function test_authenticate_builds_valid_xmldsig_envelope_for_seed(): void
    {
        $issuer = Rut::parse('76.123.456-7');

        $seedResponse = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>00</SII:ESTADO></SII:RESP_HDR>'
            .'<SII:RESP_BODY><SEMILLA>000000001042</SEMILLA></SII:RESP_BODY>'
            .'</SII:RESPUESTA>';

        $tokenResponse = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>00</SII:ESTADO></SII:RESP_HDR>'
            .'<SII:RESP_BODY><TOKEN>sii-token</TOKEN></SII:RESP_BODY>'
            .'</SII:RESPUESTA>';

        $signedXml = null;

        $this->makeAuthGateway();

        $this->mockSoapFlow(
            static function (SoapClient $client) use ($seedResponse): void {
                $client->expects('getSeed')->andReturn($seedResponse);
            },
            static function (SoapClient $client) use ($tokenResponse, &$signedXml): void {
                $client->expects('getToken')->with(Mockery::on(
                    static function ($xml) use (&$signedXml) {
                        $signedXml = $xml;

                        return true;
                    }
                ))->andReturn($tokenResponse);
            }
        );

        $this->mockOpenSsl();

        $gateway = $this->app->make(SoapGateway::class);

        $token = $gateway->authenticate($issuer);

        static::assertSame('sii-token', $token);
        static::assertNotNull($signedXml);
        static::assertStringContainsString('<getToken>', $signedXml);
        static::assertStringContainsString('<Semilla>000000001042</Semilla>', $signedXml);
        static::assertStringContainsString(
            'http://www.w3.org/2000/09/xmldsig#enveloped-signature',
            $signedXml
        );
        static::assertStringContainsString('<SignedInfo', $signedXml);
        static::assertStringContainsString('<SignatureValue>signature-b64</SignatureValue>', $signedXml);
        static::assertStringContainsString('<X509Certificate>certificate</X509Certificate>', $signedXml);
        static::assertStringContainsString('<Modulus>'.base64_encode('modulus').'</Modulus>', $signedXml);
    }

    public function test_authenticate_retries_seed_on_minus_one(): void
    {
        $issuer = Rut::parse('76.123.456-7');

        $seedNegative = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>-1</SII:ESTADO><SII:GLOSA>Error no genera Semilla</SII:GLOSA></SII:RESP_HDR>'
            .'</SII:RESPUESTA>';

        $seedOk = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>00</SII:ESTADO></SII:RESP_HDR>'
            .'<SII:RESP_BODY><SEMILLA>000000001042</SEMILLA></SII:RESP_BODY>'
            .'</SII:RESPUESTA>';

        $tokenResponse = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>00</SII:ESTADO></SII:RESP_HDR>'
            .'<SII:RESP_BODY><TOKEN>sii-token</TOKEN></SII:RESP_BODY>'
            .'</SII:RESPUESTA>';

        $seedCalls = 0;

        $this->makeAuthGateway();

        $this->mockSoapFlow(
            static function (SoapClient $client) use ($seedNegative, $seedOk, &$seedCalls): void {
                $client->expects('getSeed')->twice()->andReturnUsing(
                    static function () use (&$seedCalls, $seedNegative, $seedOk) {
                        $seedCalls++;

                        return $seedCalls === 1 ? $seedNegative : $seedOk;
                    }
                );
            },
            static function (SoapClient $client) use ($tokenResponse): void {
                $client->expects('getToken')->andReturn($tokenResponse);
            }
        );

        $this->mockOpenSsl();

        $gateway = $this->app->make(SoapGateway::class);

        static::assertSame('sii-token', $gateway->authenticate($issuer));
        static::assertSame(2, $seedCalls);
    }

    public function test_authenticate_gives_up_after_minus_one_retry(): void
    {
        $issuer = Rut::parse('76.123.456-7');

        $seedNegative = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>-1</SII:ESTADO></SII:RESP_HDR>'
            .'</SII:RESPUESTA>';

        $seedCalls = 0;

        $this->makeAuthGateway();

        $this->mockSoapFlow(
            static function (SoapClient $client) use ($seedNegative, &$seedCalls): void {
                $client->expects('getSeed')->twice()->andReturnUsing(
                    static function () use (&$seedCalls, $seedNegative) {
                        $seedCalls++;

                        return $seedNegative;
                    }
                );
            },
            static function (SoapClient $client): void {
                $client->expects('getToken')->never();
            }
        );

        $this->mockOpenSsl();

        $gateway = $this->app->make(SoapGateway::class);

        $this->expectException(RuntimeException::class);

        $gateway->authenticate($issuer);
    }

    public function test_authenticate_retries_seed_on_minus_two_with_backoff(): void
    {
        $issuer = Rut::parse('76.123.456-7');

        $seedBackoff = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>-2</SII:ESTADO></SII:RESP_HDR>'
            .'</SII:RESPUESTA>';

        // Always -2: the backoff is exhausted and the exception is thrown.
        $seedCalls = 0;

        $this->makeAuthGateway();

        $this->mockSoapFlow(
            static function (SoapClient $client) use ($seedBackoff, &$seedCalls): void {
                $client->expects('getSeed')->times(5)->andReturnUsing(
                    static function () use (&$seedCalls, $seedBackoff) {
                        $seedCalls++;

                        return $seedBackoff;
                    }
                );
            },
            static function (SoapClient $client): void {
                $client->expects('getToken')->never();
            }
        );

        $this->mockOpenSsl();

        $gateway = $this->app->make(SoapGateway::class);

        $this->expectException(SiiSeedUnavailableException::class);

        $gateway->authenticate($issuer);

        static::assertSame(5, $seedCalls);
    }

    public function test_authenticate_throws_on_token_client_error_codes(): void
    {
        $issuer = Rut::parse('76.123.456-7');

        $seedResponse = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>00</SII:ESTADO></SII:RESP_HDR>'
            .'<SII:RESP_BODY><SEMILLA>000000001042</SEMILLA></SII:RESP_BODY>'
            .'</SII:RESPUESTA>';

        foreach (['01', '02', '04', '05', '06', '11'] as $code) {
            $tokenError = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
                .'<SII:RESP_HDR><SII:ESTADO>'.$code.'</SII:ESTADO><SII:GLOSA>Error</SII:GLOSA></SII:RESP_HDR>'
                .'</SII:RESPUESTA>';

            $this->makeAuthGateway();
            $this->mockSoapFlow(
                static function (SoapClient $client) use ($seedResponse): void {
                    $client->expects('getSeed')->andReturn($seedResponse);
                },
                static function (SoapClient $client) use ($tokenError): void {
                    $client->expects('getToken')->andReturn($tokenError);
                }
            );
            $this->mockOpenSsl();

            $gateway = $this->app->make(SoapGateway::class);

            try {
                $gateway->authenticate($issuer);
                static::fail("Expected RuntimeException for code $code.");
            } catch (RuntimeException $e) {
                static::assertInstanceOf(RuntimeException::class, $e);
                static::assertNotInstanceOf(SiiAuthenticationException::class, $e);
                static::assertStringContainsString($code, $e->getMessage());
            }
        }
    }

    public function test_authenticate_retries_token_on_code_12_with_backoff(): void
    {
        $issuer = Rut::parse('76.123.456-7');

        $seedResponse = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>00</SII:ESTADO></SII:RESP_HDR>'
            .'<SII:RESP_BODY><SEMILLA>000000001042</SEMILLA></SII:RESP_BODY>'
            .'</SII:RESPUESTA>';

        $tokenError = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>12</SII:ESTADO><SII:GLOSA>Error interno SII</SII:GLOSA></SII:RESP_HDR>'
            .'</SII:RESPUESTA>';

        $tokenOk = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>00</SII:ESTADO></SII:RESP_HDR>'
            .'<SII:RESP_BODY><TOKEN>sii-token</TOKEN></SII:RESP_BODY>'
            .'</SII:RESPUESTA>';

        $tokenCalls = 0;

        $this->makeAuthGateway();

        $this->mockSoapFlow(
            static function (SoapClient $client) use ($seedResponse): void {
                $client->expects('getSeed')->andReturn($seedResponse);
            },
            static function (SoapClient $client) use ($tokenError, $tokenOk, &$tokenCalls): void {
                $client->expects('getToken')->times(2)->andReturnUsing(
                    static function () use (&$tokenCalls, $tokenError, $tokenOk) {
                        $tokenCalls++;

                        // 1st call returns 12, 2nd call returns success.
                        return $tokenCalls === 1 ? $tokenError : $tokenOk;
                    }
                );
            }
        );

        $this->mockOpenSsl();

        $gateway = $this->app->make(SoapGateway::class);

        static::assertSame('sii-token', $gateway->authenticate($issuer));
        static::assertGreaterThanOrEqual(2, $tokenCalls);
    }

    public function test_authenticate_throws_sii_auth_exception_on_minus_3(): void
    {
        $issuer = Rut::parse('76.123.456-7');

        $seedResponse = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>00</SII:ESTADO></SII:RESP_HDR>'
            .'<SII:RESP_BODY><SEMILLA>000000001042</SEMILLA></SII:RESP_BODY>'
            .'</SII:RESPUESTA>';

        $tokenError = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>-3</SII:ESTADO><SII:GLOSA>Error en Autenticación</SII:GLOSA></SII:RESP_HDR>'
            .'</SII:RESPUESTA>';

        $this->makeAuthGateway();

        $this->mockSoapFlow(
            static function (SoapClient $client) use ($seedResponse): void {
                $client->expects('getSeed')->andReturn($seedResponse);
            },
            static function (SoapClient $client) use ($tokenError): void {
                $client->expects('getToken')->andReturn($tokenError);
            }
        );

        $this->mockOpenSsl();

        $gateway = $this->app->make(SoapGateway::class);

        $this->expectException(SiiAuthenticationException::class);

        $gateway->authenticate($issuer);
    }

    public function test_resolve_wsdl_url_uses_jws_extension(): void
    {
        $gateway = $this->makeAuthGateway();

        $method = new ReflectionMethod(SoapGateway::class, 'resolveWsdlUrl');
        $method->setAccessible(true);

        static::assertSame(
            'https://palena.sii.cl/DTEWS/CrSeed.jws?WSDL',
            $method->invoke($gateway, 'https://palena.sii.cl', 'CrSeed')
        );

        static::assertSame(
            'https://palena.sii.cl/DTEWS/GetTokenFromSeed.jws?WSDL',
            $method->invoke($gateway, 'https://palena.sii.cl', 'GetTokenFromSeed')
        );
    }

    public function test_query_sends_token_header_and_dispatches_action(): void
    {
        $client = Mockery::mock(SoapClient::class);

        $client->expects('__setSoapHeaders')->with(Mockery::on(
            static function (SoapHeader $header): bool {
                return $header->name === 'Token'
                    && $header->data === 'query-token';
            }
        ));

        $client->expects('__soapCall')
            ->with('getEstUp', ['TrackId' => '1'])
            ->andReturn('sii-response');

        $this->mock(SoapProxy::class, static function (MockInterface $mock) use ($client): void {
            $mock->expects('withWsdl')
                ->with('https://palena.sii.cl/DTEWS/QueryEstUp.jws?WSDL')
                ->andReturnSelf();
            $mock->expects('build')->andReturn($client);
        });

        $this->instance(EnvironmentResolver::class, $this->makeEnvironmentResolver(DteEnvironment::Production));

        $gateway = $this->app->make(SoapGateway::class);
        $token = new Token('query-token', time() + 3600);

        static::assertSame(
            'sii-response',
            $gateway->query($token, 'QueryEstUp', 'getEstUp', ['TrackId' => '1'])
        );
    }

    public function test_throws_when_no_certificate_resolved(): void
    {
        $issuer = Rut::parse('76.123.456-7');

        $this->mock(CertificateResolverInterface::class, static function (MockInterface $mock) use ($issuer): void {
            $mock->expects('resolve')->zeroOrMoreTimes()->with($issuer)->andReturnNull();
        });

        $this->instance(EnvironmentResolver::class, $this->makeEnvironmentResolver(DteEnvironment::Production));

        $this->mock(SoapProxy::class);
        $this->mock(OpenSslProxy::class);

        $gateway = $this->app->make(SoapGateway::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('No digital certificate resolved for issuer 76123456-7');

        $gateway->authenticate($issuer);
    }

    public function test_throws_when_environment_has_no_base_url(): void
    {
        $issuer = Rut::parse('76.123.456-7');

        $this->mock(CertificateResolverInterface::class)->shouldNotReceive('resolve');

        $this->instance(EnvironmentResolver::class, $this->makeEnvironmentResolver(DteEnvironment::Local));

        $this->mock(SoapProxy::class);
        $this->mock(OpenSslProxy::class);

        $gateway = $this->app->make(SoapGateway::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Cannot authenticate because no SOAP Base URL is available in this environment.');

        $gateway->authenticate($issuer);
    }

    public function test_throws_when_seed_signing_fails(): void
    {
        $issuer = Rut::parse('76.123.456-7');

        $seedResponse = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>00</SII:ESTADO></SII:RESP_HDR>'
            .'<SII:RESP_BODY><SEMILLA>000000001042</SEMILLA></SII:RESP_BODY>'
            .'</SII:RESPUESTA>';

        $this->makeAuthGateway();

        $this->mockSoapFlow(
            static function (SoapClient $client) use ($seedResponse): void {
                $client->expects('getSeed')->andReturn($seedResponse);
            },
            static function (SoapClient $client): void {
                $client->expects('getToken')->never();
            }
        );

        $this->mock(OpenSslProxy::class, static function (MockInterface $mock): void {
            $mock->expects('readPkcs12String')
                ->andReturn(['pkey' => 'private-key', 'cert' => 'certificate']);
            $mock->expects('privateKeyDetails')
                ->andReturn(['rsa' => ['n' => 'modulus', 'e' => 'AQAB']]);
            $mock->expects('sign')
                ->andThrow(new RuntimeException('Failed to sign data with private key.'));
        });

        $gateway = $this->app->make(SoapGateway::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Failed to sign data with private key.');

        $gateway->authenticate($issuer);
    }

    public function test_authenticate_throws_on_unexpected_seed_state(): void
    {
        $issuer = Rut::parse('76.123.456-7');

        // -3 is CertificateRejected — valid SiiAuthState but not expected for seeds.
        $seedUnexpected = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>-3</SII:ESTADO></SII:RESP_HDR>'
            .'</SII:RESPUESTA>';

        $this->makeAuthGateway();

        $this->mockSoapFlow(
            static function (SoapClient $client) use ($seedUnexpected): void {
                $client->expects('getSeed')->andReturn($seedUnexpected);
            },
            static function (SoapClient $client): void {
                $client->expects('getToken')->never();
            }
        );

        $this->mockOpenSsl();

        $gateway = $this->app->make(SoapGateway::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs("SII returned an unexpected seed state '-3'.");

        $gateway->authenticate($issuer);
    }

    public function test_seed_backoff_succeeds_after_retries(): void
    {
        $issuer = Rut::parse('76.123.456-7');

        $seedBackoff = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>-2</SII:ESTADO></SII:RESP_HDR>'
            .'</SII:RESPUESTA>';

        $seedOk = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>00</SII:ESTADO></SII:RESP_HDR>'
            .'<SII:RESP_BODY><SEMILLA>000000001042</SEMILLA></SII:RESP_BODY>'
            .'</SII:RESPUESTA>';

        $tokenOk = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>00</SII:ESTADO></SII:RESP_HDR>'
            .'<SII:RESP_BODY><TOKEN>sii-token</TOKEN></SII:RESP_BODY>'
            .'</SII:RESPUESTA>';

        $seedCalls = 0;

        $this->makeAuthGateway();

        $this->mockSoapFlow(
            static function (SoapClient $client) use ($seedBackoff, $seedOk, &$seedCalls): void {
                $client->expects('getSeed')->times(3)->andReturnUsing(
                    static function () use (&$seedCalls, $seedBackoff, $seedOk) {
                        $seedCalls++;

                        return $seedCalls === 3 ? $seedOk : $seedBackoff;
                    }
                );
            },
            static function (SoapClient $client) use ($tokenOk): void {
                $client->expects('getToken')->andReturn($tokenOk);
            }
        );

        $this->mockOpenSsl();

        $gateway = $this->app->make(SoapGateway::class);

        static::assertSame('sii-token', $gateway->authenticate($issuer));
        static::assertSame(3, $seedCalls);
    }

    public function test_seed_backoff_breaks_on_non_database_error(): void
    {
        $issuer = Rut::parse('76.123.456-7');

        $seedBackoff = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>-2</SII:ESTADO></SII:RESP_HDR>'
            .'</SII:RESPUESTA>';

        $seedOtherError = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>-1</SII:ESTADO></SII:RESP_HDR>'
            .'</SII:RESPUESTA>';

        $seedCalls = 0;

        $this->makeAuthGateway();

        $this->mockSoapFlow(
            static function (SoapClient $client) use ($seedBackoff, $seedOtherError, &$seedCalls): void {
                $client->expects('getSeed')->times(2)->andReturnUsing(
                    static function () use (&$seedCalls, $seedBackoff, $seedOtherError) {
                        $seedCalls++;

                        return $seedCalls === 1 ? $seedBackoff : $seedOtherError;
                    }
                );
            },
            static function (SoapClient $client): void {
                $client->expects('getToken')->never();
            }
        );

        $this->mockOpenSsl();

        $gateway = $this->app->make(SoapGateway::class);

        $this->expectException(RuntimeException::class);

        $gateway->authenticate($issuer);
    }

    public function test_token_backoff_exhausted_throws(): void
    {
        $issuer = Rut::parse('76.123.456-7');

        $seedResponse = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>00</SII:ESTADO></SII:RESP_HDR>'
            .'<SII:RESP_BODY><SEMILLA>000000001042</SEMILLA></SII:RESP_BODY>'
            .'</SII:RESPUESTA>';

        $tokenPending = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>12</SII:ESTADO></SII:RESP_HDR>'
            .'</SII:RESPUESTA>';

        $this->makeAuthGateway();

        $this->mockSoapFlow(
            static function (SoapClient $client) use ($seedResponse): void {
                $client->expects('getSeed')->andReturn($seedResponse);
            },
            static function (SoapClient $client) use ($tokenPending): void {
                $client->expects('getToken')->times(5)->andReturn($tokenPending);
            }
        );

        $this->mockOpenSsl();

        $gateway = $this->app->make(SoapGateway::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('SII could not exchange the seed for a token after multiple retries.');

        $gateway->authenticate($issuer);
    }

    public function test_throws_on_unexpected_token_state(): void
    {
        $issuer = Rut::parse('76.123.456-7');

        $seedResponse = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>00</SII:ESTADO></SII:RESP_HDR>'
            .'<SII:RESP_BODY><SEMILLA>000000001042</SEMILLA></SII:RESP_BODY>'
            .'</SII:RESPUESTA>';

        $tokenUnknown = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>99</SII:ESTADO></SII:RESP_HDR>'
            .'</SII:RESPUESTA>';

        $this->makeAuthGateway();

        $this->mockSoapFlow(
            static function (SoapClient $client) use ($seedResponse): void {
                $client->expects('getSeed')->andReturn($seedResponse);
            },
            static function (SoapClient $client) use ($tokenUnknown): void {
                $client->expects('getToken')->andReturn($tokenUnknown);
            }
        );

        $this->mockOpenSsl();

        $gateway = $this->app->make(SoapGateway::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs("SII returned an unexpected token state '99'.");

        $gateway->authenticate($issuer);
    }

    public function test_call_seed_throws_when_estado_is_null(): void
    {
        $issuer = Rut::parse('76.123.456-7');

        $invalidXml = 'not valid xml at all';

        $this->makeAuthGateway();

        $this->mockSoapFlow(
            static function (SoapClient $client) use ($invalidXml): void {
                $client->expects('getSeed')->andReturn($invalidXml);
            },
            static function (SoapClient $client): void {
                $client->expects('getToken')->never();
            }
        );

        $this->mockOpenSsl();

        $gateway = $this->app->make(SoapGateway::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Unable to parse the SII seed response.');

        $gateway->authenticate($issuer);
    }

    public function test_call_token_throws_when_estado_is_null(): void
    {
        $issuer = Rut::parse('76.123.456-7');

        $seedResponse = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>00</SII:ESTADO></SII:RESP_HDR>'
            .'<SII:RESP_BODY><SEMILLA>000000001042</SEMILLA></SII:RESP_BODY>'
            .'</SII:RESPUESTA>';

        $invalidToken = 'not valid xml at all';

        $this->makeAuthGateway();

        $this->mockSoapFlow(
            static function (SoapClient $client) use ($seedResponse): void {
                $client->expects('getSeed')->andReturn($seedResponse);
            },
            static function (SoapClient $client) use ($invalidToken): void {
                $client->expects('getToken')->andReturn($invalidToken);
            }
        );

        $this->mockOpenSsl();

        $gateway = $this->app->make(SoapGateway::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Unable to parse the SII token response.');

        $gateway->authenticate($issuer);
    }

    public function test_normalize_soap_return_reads_get_seed_return_property(): void
    {
        $issuer = Rut::parse('76.123.456-7');

        $seedResponse = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>00</SII:ESTADO></SII:RESP_HDR>'
            .'<SII:RESP_BODY><SEMILLA>000000001042</SEMILLA></SII:RESP_BODY>'
            .'</SII:RESPUESTA>';

        $tokenOk = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>00</SII:ESTADO></SII:RESP_HDR>'
            .'<SII:RESP_BODY><TOKEN>sii-token</TOKEN></SII:RESP_BODY>'
            .'</SII:RESPUESTA>';

        $seedObject = (object) ['getSeedReturn' => $seedResponse];

        $this->makeAuthGateway();

        $this->mockSoapFlow(
            static function (SoapClient $client) use ($seedObject): void {
                $client->expects('getSeed')->andReturn($seedObject);
            },
            static function (SoapClient $client) use ($tokenOk): void {
                $client->expects('getToken')->andReturn($tokenOk);
            }
        );

        $this->mockOpenSsl();

        $gateway = $this->app->make(SoapGateway::class);

        static::assertSame('sii-token', $gateway->authenticate($issuer));
    }

    public function test_normalize_soap_return_falls_back_to_string_cast(): void
    {
        $issuer = Rut::parse('76.123.456-7');

        $seedResponse = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>00</SII:ESTADO></SII:RESP_HDR>'
            .'<SII:RESP_BODY><SEMILLA>000000001042</SEMILLA></SII:RESP_BODY>'
            .'</SII:RESPUESTA>';

        $tokenOk = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>00</SII:ESTADO></SII:RESP_HDR>'
            .'<SII:RESP_BODY><TOKEN>sii-token</TOKEN></SII:RESP_BODY>'
            .'</SII:RESPUESTA>';

        $seedObject = new class {
            public function __toString(): string
            {
                return '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
                    .'<SII:RESP_HDR><SII:ESTADO>00</SII:ESTADO></SII:RESP_HDR>'
                    .'<SII:RESP_BODY><SEMILLA>000000001042</SEMILLA></SII:RESP_BODY>'
                    .'</SII:RESPUESTA>';
            }
        };

        $this->makeAuthGateway();

        $this->mockSoapFlow(
            static function (SoapClient $client) use ($seedObject): void {
                $client->expects('getSeed')->andReturn($seedObject);
            },
            static function (SoapClient $client) use ($tokenOk): void {
                $client->expects('getToken')->andReturn($tokenOk);
            }
        );

        $this->mockOpenSsl();

        $gateway = $this->app->make(SoapGateway::class);

        static::assertSame('sii-token', $gateway->authenticate($issuer));
    }

    public function test_status_element_returns_null_on_invalid_xml(): void
    {
        $issuer = Rut::parse('76.123.456-7');

        $invalidSeed = 'not valid xml at all';

        $this->makeAuthGateway();

        $this->mockSoapFlow(
            static function (SoapClient $client) use ($invalidSeed): void {
                $client->expects('getSeed')->andReturn($invalidSeed);
            },
            static function (SoapClient $client): void {
                $client->expects('getToken')->never();
            }
        );

        $this->mockOpenSsl();

        $gateway = $this->app->make(SoapGateway::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Unable to parse the SII seed response.');

        $gateway->authenticate($issuer);
    }

    public function test_resolve_wsdl_url_throws_on_null_base_url(): void
    {
        $issuer = Rut::parse('76.123.456-7');

        $this->makeAuthGateway();

        $this->mock(SoapProxy::class);
        $this->mock(OpenSslProxy::class);

        $this->instance(EnvironmentResolver::class, $this->makeEnvironmentResolver(DteEnvironment::Local));

        $gateway = $this->app->make(SoapGateway::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Cannot resolve the WSDL URL without a SOAP base URL.');

        $gateway->query(new Token('token', time() + 3600), 'QueryEstUp', 'getEstUp');
    }

    public function test_authenticate_retries_fetch_seed_on_soap_fault(): void
    {
        $issuer = Rut::parse('76.123.456-7');

        $seedOk = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>00</SII:ESTADO></SII:RESP_HDR>'
            .'<SII:RESP_BODY><SEMILLA>000000001042</SEMILLA></SII:RESP_BODY>'
            .'</SII:RESPUESTA>';

        $tokenOk = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>00</SII:ESTADO></SII:RESP_HDR>'
            .'<SII:RESP_BODY><TOKEN>sii-token</TOKEN></SII:RESP_BODY>'
            .'</SII:RESPUESTA>';

        $seedCalls = 0;

        $this->makeAuthGateway();

        $this->mockSoapFlow(
            static function (SoapClient $client) use ($seedOk, &$seedCalls): void {
                // No count constraint: each retry creates a new SoapClient mock.
                $client->expects('getSeed')->andReturnUsing(
                    static function () use (&$seedCalls, $seedOk) {
                        $seedCalls++;

                        return $seedCalls === 1
                            ? throw new SoapFault('Server', 'Connection failed')
                            : $seedOk;
                    }
                );
            },
            static function (SoapClient $client) use ($tokenOk): void {
                $client->expects('getToken')->andReturn($tokenOk);
            }
        );

        $this->mockOpenSsl();

        $gateway = $this->app->make(SoapGateway::class);

        static::assertSame('sii-token', $gateway->authenticate($issuer));
        static::assertSame(2, $seedCalls);
    }

    public function test_authenticate_retries_exchange_token_on_soap_fault(): void
    {
        $issuer = Rut::parse('76.123.456-7');

        $seedOk = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>00</SII:ESTADO></SII:RESP_HDR>'
            .'<SII:RESP_BODY><SEMILLA>000000001042</SEMILLA></SII:RESP_BODY>'
            .'</SII:RESPUESTA>';

        $tokenOk = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/SiiDte">'
            .'<SII:RESP_HDR><SII:ESTADO>00</SII:ESTADO></SII:RESP_HDR>'
            .'<SII:RESP_BODY><TOKEN>sii-token</TOKEN></SII:RESP_BODY>'
            .'</SII:RESPUESTA>';

        $tokenCalls = 0;

        $this->makeAuthGateway();

        $this->mockSoapFlow(
            static function (SoapClient $client) use ($seedOk): void {
                $client->expects('getSeed')->andReturn($seedOk);
            },
            static function (SoapClient $client) use ($tokenOk, &$tokenCalls): void {
                $client->expects('getToken')->andReturnUsing(
                    static function () use (&$tokenCalls, $tokenOk) {
                        $tokenCalls++;

                        return $tokenCalls === 1
                            ? throw new SoapFault('Server', 'Connection failed')
                            : $tokenOk;
                    }
                );
            }
        );

        $this->mockOpenSsl();

        $gateway = $this->app->make(SoapGateway::class);

        static::assertSame('sii-token', $gateway->authenticate($issuer));
        static::assertSame(2, $tokenCalls);
    }

    public function test_query_retries_on_soap_fault(): void
    {
        $calls = 0;

        $this->mock(SoapProxy::class, static function (MockInterface $mock) use (&$calls): void {
            $mock->expects('withWsdl')
                ->zeroOrMoreTimes()
                ->andReturnSelf();

            $mock->expects('build')
                ->zeroOrMoreTimes()
                ->andReturnUsing(function () use (&$calls) {
                    $client = Mockery::mock(SoapClient::class);

                    $client->expects('__setSoapHeaders')
                        ->zeroOrMoreTimes();

                    $client->expects('__soapCall')
                        ->andReturnUsing(
                            static function () use (&$calls) {
                                $calls++;

                                return $calls === 1
                                    ? throw new SoapFault('Server', 'Connection failed')
                                    : 'sii-response';
                            }
                        );

                    return $client;
                });
        });

        $this->instance(EnvironmentResolver::class, $this->makeEnvironmentResolver(DteEnvironment::Production));

        $gateway = $this->app->make(SoapGateway::class);
        $token = new Token('query-token', time() + 3600);

        static::assertSame(
            'sii-response',
            $gateway->query($token, 'QueryEstUp', 'getEstUp', ['TrackId' => '1'])
        );

        static::assertSame(2, $calls);
    }
}
