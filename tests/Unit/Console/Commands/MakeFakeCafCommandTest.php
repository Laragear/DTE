<?php

namespace Tests\Unit\Console\Commands;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\SiiCaf;
use Laragear\Dte\Proxies\OpenSslProxy;
use Mockery;
use Mockery\MockInterface;
use OpenSSLAsymmetricKey;
use RuntimeException;
use Tests\DatabaseTestCase;

class MakeFakeCafCommandTest extends DatabaseTestCase
{
    use RefreshDatabase;

    public ?string $path = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = $this->app->basePath('test-fake-caf.xml');

        $this->app->make(Filesystem::class)->delete($this->path);
    }

    protected function tearDown(): void
    {
        $this->app->make(Filesystem::class)->delete($this->path);

        parent::tearDown();
    }

    public function test_generates_fake_caf_using_company_issuer(): void
    {
        $this->mock(ConfigurationManager::class, static function (MockInterface $mock): void {
            $issuer = IssuerData::make(
                '76.111.222-3',
                'Test Company',
                'Software',
                ['620100'],
                'Test Address 123',
                'Santiago',
                '2025-01-01',
                76000,
                'Santiago',
                '+56212345678',
                'test@example.com',
                'Casa Matriz',
            );

            $mock->expects('hasIssuerResolver')->andReturnTrue();
            $mock->expects('getIssuer')->andReturn($issuer);
        });

        $this
            ->artisan('dte:make-fake-caf')
            ->expectsOutputToContain('76111222-3')
            ->assertSuccessful();
    }

    public function test_generates_fake_caf_and_writes_to_file(): void
    {
        $this
            ->artisan('dte:make-fake-caf', [
                '--rut' => '76.123.456-7',
                '--type' => '33',
                '--from' => '100',
                '--to' => '200',
                '--file' => $this->path,
            ])
            ->expectsOutput('Generating dummy CAF for RUT 76.123.456-7 (Type: 33, Folios: 100-200)...')
            ->expectsOutput("Successfully created fake CAF at: {$this->path}")
            ->assertSuccessful();

        static::assertTrue($this->app->make(Filesystem::class)->exists($this->path));

        $xml = $this->app->make(Filesystem::class)->get($this->path);

        static::assertStringContainsString('<RE>76123456-7</RE>', $xml);
        static::assertStringContainsString('<TD>33</TD>', $xml);
        static::assertStringContainsString('<D>100</D>', $xml);
        static::assertStringContainsString('<H>200</H>', $xml);

        $this->app->make(Filesystem::class)->delete($this->path);
    }

    public function test_generates_fake_caf_and_inserts_to_db(): void
    {
        $this
            ->artisan('dte:make-fake-caf', [
                '--rut' => '76.123.456-7',
                '--type' => '33',
                '--from' => '100',
                '--to' => '200',
                '--db' => true,
            ])
            ->expectsOutput('Generating dummy CAF for RUT 76.123.456-7 (Type: 33, Folios: 100-200)...')
            ->expectsOutput('Successfully inserted fake CAF into the database.')
            ->assertSuccessful();

        $caf = SiiCaf::first();

        static::assertNotNull($caf);
        static::assertSame(76123456, $caf->rut->num);
        static::assertSame('7', $caf->rut->vd);
        static::assertSame(DteType::Invoice, $caf->document_type);
        static::assertSame(100, $caf->folio_from);
        static::assertSame(200, $caf->folio_to);
        static::assertSame(100, $caf->folio_current);
        static::assertStringContainsString('<RE>76123456-7</RE>', $caf->xml);
    }

    public function test_outputs_xml_if_no_file_or_db(): void
    {
        $this->travelTo(Carbon::parse('2025-01-01 00:00:00', 'America/Santiago'));

        $fakeCaf = static::getStub('FakeCaf.xml');

        $this->partialMock(OpenSslProxy::class, static function (MockInterface $mock): void {
            $mock->expects('pkeyExport')
                ->with(
                    Mockery::type(OpenSSLAsymmetricKey::class),
                    Mockery::on(function (string &$privateKey): bool {
                        $privateKey = 'test-private-key';

                        return true;
                    })
                );

            $mock->expects('pkeyGetDetails')->andReturn([
                'rsa' => [
                    'n' => 'test-n-value',
                    'e' => 'test-e-value',
                ],
                'key' => 'test-public-key',
            ]);

        });

        $this
            ->artisan('dte:make-fake-caf')
            ->expectsOutputToContain($fakeCaf)
            ->assertSuccessful();
    }

    public function test_fails_on_invalid_type(): void
    {
        $this
            ->artisan('dte:make-fake-caf', [
                '--type' => 'invalid',
            ])
            ->expectsOutputToContain('Invalid DTE type provided: invalid.')
            ->assertFailed();
    }

    public function test_fails_when_openssl_fails(): void
    {
        $this->mock(OpenSslProxy::class)->expects('pkeyNew')->andReturn(false);

        $this
            ->artisan('dte:make-fake-caf')
            ->expectsOutputToContain('Failed to generate RSA key pair.')
            ->assertFailed();
    }

    public function test_fails_if_file_cannot_be_written(): void
    {

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Failed to write CAF to /does/not/exist/test-fake-caf.xml.');

        $this
            ->artisan('dte:make-fake-caf', [
                '--rut' => '76.123.456-7',
                '--file' => '/does/not/exist/test-fake-caf.xml',
            ])
            ->assertFailed();
    }
}
