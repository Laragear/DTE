<?php

namespace Tests\Unit\Console\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Data\CompanyData;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\IecvType;
use Laragear\Dte\Gateways\IecvUploadGateway;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiIecv;
use Laragear\Dte\Services\IecvGenerator;
use Laragear\Dte\Services\IecvService;
use Laragear\Rut\Rut;
use Tests\DatabaseTestCase;

class SendIecvCommandTest extends DatabaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ConfigurationManager::setCompany(fn () => CompanyData::make(
            IssuerData::make(
                '76.123.456-0',
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
            ),
        ));
    }

    /**
     * Build an accepted document issued inside the given period.
     */
    protected function document(string $period, string $folio = '100'): SiiDte
    {
        return SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('76.123.456-0'),
            'document_type' => DteType::Invoice,
            'folio' => $folio,
            'status' => DteStatus::Accepted,
            'issued_on' => "$period-15",
        ]);
    }

    protected function mockService(IecvType $expected): IecvService
    {
        $generator = $this->mock(IecvGenerator::class);
        $generator->expects('generateSales')->zeroOrMoreTimes()->andReturn('<Libro/>');
        $generator->expects('generatePurchases')->zeroOrMoreTimes()->andReturn('<Libro/>');

        $upload = $this->mock(IecvUploadGateway::class);
        $upload->expects('upload')->zeroOrMoreTimes()->andReturn('123456789');

        $service = $this->mock(IecvService::class);

        $service->expects('sendSales')->zeroOrMoreTimes()->andReturnUsing(
            static fn () => SiiIecv::factory()->uploaded()->create(['type' => $expected])
        );
        $service->expects('sendPurchases')->zeroOrMoreTimes()->andReturnUsing(
            static fn () => SiiIecv::factory()->uploaded()->create(['type' => IecvType::Purchases])
        );

        $this->app->instance(IecvService::class, $service);

        return $service;
    }

    public function test_sends_a_sales_book_for_the_given_period(): void
    {
        $this->document('2026-07');

        $this->mockService(IecvType::Sales);

        $this->artisan('dte:send-iecv', [
            '--period' => '2026-07',
            '--issuer' => '76123456-0',
        ])->assertSuccessful();
    }

    public function test_defaults_the_period_to_last_month(): void
    {
        $period = now()->subMonth()->format('Y-m');

        $this->document($period);

        $this->mockService(IecvType::Sales);

        $this->artisan('dte:send-iecv', [
            '--issuer' => '76123456-0',
        ])->assertSuccessful();
    }

    public function test_sends_a_purchases_book(): void
    {
        $this->document('2026-07');

        $this->mockService(IecvType::Purchases);

        $this->artisan('dte:send-iecv', [
            '--period' => '2026-07',
            '--issuer' => '76123456-0',
            '--type' => 'purchases',
        ])->assertSuccessful();
    }

    public function test_falls_back_to_the_latest_document_issuer(): void
    {
        $this->document('2026-07');

        $this->mockService(IecvType::Sales);

        // No --issuer: the command must resolve it from a stored document.
        $this->artisan('dte:send-iecv', [
            '--period' => '2026-07',
        ])->assertSuccessful();
    }

    public function test_fails_when_no_issuer_can_be_resolved(): void
    {
        $this->mockService(IecvType::Sales);

        $this->artisan('dte:send-iecv', ['--period' => '2026-07'])
            ->expectsOutputToContain('No issuer RUT could be resolved.')
            ->assertFailed();
    }

    public function test_fails_when_no_documents_match_the_period(): void
    {
        $this->mockService(IecvType::Sales);

        $this->artisan('dte:send-iecv', [
            '--period' => '2026-07',
            '--issuer' => '76123456-0',
        ])
            ->expectsOutputToContain('No documents found to file for period [2026-07].')
            ->assertFailed();
    }

    public function test_uses_an_explicit_sender_rut(): void
    {
        $this->document('2026-07');

        $this->mockService(IecvType::Sales);

        $this->artisan('dte:send-iecv', [
            '--period' => '2026-07',
            '--issuer' => '76123456-0',
            '--sender' => '98999999-9',
        ])->assertSuccessful();
    }
}
