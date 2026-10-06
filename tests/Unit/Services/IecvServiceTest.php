<?php

namespace Tests\Unit\Services;

use Illuminate\Contracts\Config\Repository as ConfigContract;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\DateFactory;
use Laragear\Dte\Builders\Iecv\IecvPurchaseData;
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Data\CompanyData;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Enums\IecvStatus;
use Laragear\Dte\Enums\IecvType;
use Laragear\Dte\Gateways\IecvUploadGateway;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiIecv;
use Laragear\Dte\Services\IecvGenerator;
use Laragear\Dte\Services\IecvService;
use Laragear\Rut\Rut;
use LogicException;
use RuntimeException;
use Tests\DatabaseTestCase;

class IecvServiceTest extends DatabaseTestCase
{
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

    protected function makeService(
        ?IecvGenerator $generator = null,
        ?IecvUploadGateway $upload = null,
    ): IecvService {
        if ($generator === null) {
            $generator = $this->mock(IecvGenerator::class);
            $generator->expects('generateSales')->zeroOrMoreTimes()
                ->andReturn('<LibroCompraVenta/>');
        }

        if ($upload === null) {
            $upload = $this->mock(IecvUploadGateway::class);
            $upload->expects('upload')->zeroOrMoreTimes()->andReturn('123456789');
        }

        return new IecvService(
            $generator,
            $upload,
            $this->app->make(ConfigurationManager::class),
            $this->app->make(ConfigContract::class),
            $this->app->make(DispatcherContract::class),
            $this->app->make(DateFactory::class),
        );
    }

    public function test_send_sales_persists_and_uploads_the_book(): void
    {
        $dte = SiiDte::factory()->create();

        $book = $this->makeService()->sendSales(
            $dte->issuer_rut, collect([$dte]), '2026-09', '2026-08-24', 0, $dte->issuer_rut
        );

        static::assertSame(IecvStatus::Uploaded, $book->status);
        static::assertSame('123456789', $book->track_id);
        static::assertNotNull($book->uploaded_at);
        static::assertNotNull($book->poll_at);
        static::assertTrue($dte->fresh()->iecv->is($book));
    }

    public function test_send_sales_marks_the_book_as_failed_when_upload_throws(): void
    {
        $dte = SiiDte::factory()->create();

        $upload = $this->mock(IecvUploadGateway::class);
        $upload->expects('upload')->andThrow(new RuntimeException('SII connection failed'));

        $generator = $this->mock(IecvGenerator::class);
        $generator->expects('generateSales')->once()->andReturn('<LibroCompraVenta/>');

        $service = new IecvService(
            $generator,
            $upload,
            $this->app->make(ConfigurationManager::class),
            $this->app->make(ConfigContract::class),
            $this->app->make(DispatcherContract::class),
            $this->app->make(DateFactory::class),
        );

        try {
            $service->sendSales($dte->issuer_rut, collect([$dte]), '2026-09', '2026-08-24', 0, $dte->issuer_rut);
        } catch (RuntimeException) {
            // Expected: the upload failed.
        }

        static::assertSame(IecvStatus::Failed, SiiIecv::query()->sole()->status);
    }

    public function test_send_purchases_persists_and_uploads_the_book(): void
    {
        $issuer = Rut::parse('76123456-0');
        $dte = SiiDte::factory()->create(['issuer_rut' => $issuer]);

        $generator = $this->mock(IecvGenerator::class);
        $generator->expects('generatePurchases')->once()->andReturn('<LibroCompraVenta/>');

        $book = $this->makeService($generator)->sendPurchases(
            $issuer,
            [new IecvPurchaseData(33, 1, '2026-09-15', $issuer)],
            '2026-09',
            '2026-08-24',
            0,
            $issuer,
        );

        static::assertSame(IecvStatus::Uploaded, $book->status);
        static::assertSame(IecvType::Purchases, $book->type);
    }

    public function test_falls_back_to_the_issuer_resolution_when_omitted(): void
    {
        $dte = SiiDte::factory()->create();

        $generator = $this->mock(IecvGenerator::class);
        $generator->expects('generateSales')
            ->withArgs(function (mixed $issuer, mixed $dtes, string $period, string $date, int $number): bool {
                static::assertSame('2025-01-01', $date);
                static::assertSame(76000, $number);

                return true;
            })
            ->once()
            ->andReturn('<LibroCompraVenta/>');

        $book = $this->makeService($generator)->sendSales(
            $dte->issuer_rut, collect([$dte]), '2026-09'
        );

        static::assertSame('2025-01-01', $book->resolution_date->format('Y-m-d'));
        static::assertSame(76000, $book->resolution_number);
    }

    public function test_explicit_resolution_overrides_the_issuer(): void
    {
        $dte = SiiDte::factory()->create();

        $book = $this->makeService()->sendSales(
            $dte->issuer_rut, collect([$dte]), '2026-09', '2026-08-24', 123, $dte->issuer_rut
        );

        static::assertSame('2026-08-24', $book->resolution_date->format('Y-m-d'));
        static::assertSame(123, $book->resolution_number);
    }

    /*
    |--------------------------------------------------------------------------
    | Duplicate submission guard
    |--------------------------------------------------------------------------
    */

    public function test_throws_when_document_was_already_in_an_accepted_book(): void
    {
        $dte = SiiDte::factory()->create();

        $book = SiiIecv::factory()->create(['status' => IecvStatus::Accepted]);

        $dte->update(['sii_iecv_id' => $book->getKey()]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/already sent to the SII in book/');

        $this->makeService()->sendSales(
            $dte->issuer_rut, collect([$dte->fresh()]), '2026-10', '2026-08-24', 0, $dte->issuer_rut
        );
    }

    public function test_throws_when_document_was_already_in_a_rejected_book(): void
    {
        $dte = SiiDte::factory()->create();

        $book = SiiIecv::factory()->create(['status' => IecvStatus::Rejected]);
        $dte->update(['sii_iecv_id' => $book->getKey()]);

        $this->expectException(LogicException::class);

        $this->makeService()->sendSales(
            $dte->issuer_rut, collect([$dte->fresh()]), '2026-10', '2026-08-24', 0, $dte->issuer_rut
        );
    }

    public function test_allows_document_from_a_non_terminal_book(): void
    {
        $dte = SiiDte::factory()->create();

        $book = SiiIecv::factory()->create(['status' => IecvStatus::Uploaded]);
        $dte->update(['sii_iecv_id' => $book->getKey()]);

        $result = $this->makeService()->sendSales(
            $dte->issuer_rut, collect([$dte->fresh()]), '2026-11', '2026-08-24', 0, $dte->issuer_rut
        );

        static::assertSame(IecvStatus::Uploaded, $result->status);
    }

    public function test_throws_when_period_is_already_awaiting_a_verdict(): void
    {
        $dte = SiiDte::factory()->create();

        SiiIecv::factory()->create([
            'issuer_rut' => $dte->issuer_rut,
            'type' => IecvType::Sales,
            'period' => '2026-09',
            'status' => IecvStatus::Uploaded,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/already filed in book/');

        $this->makeService()->sendSales(
            $dte->issuer_rut, collect([$dte]), '2026-09', '2026-08-24', 0, $dte->issuer_rut
        );
    }

    public function test_allows_filing_a_period_again_after_the_previous_book_failed(): void
    {
        $dte = SiiDte::factory()->create();

        SiiIecv::factory()->create([
            'issuer_rut' => $dte->issuer_rut,
            'type' => IecvType::Sales,
            'period' => '2026-09',
            'status' => IecvStatus::Failed,
        ]);

        $result = $this->makeService()->sendSales(
            $dte->issuer_rut, collect([$dte]), '2026-09', '2026-08-24', 0, $dte->issuer_rut
        );

        static::assertSame(IecvStatus::Uploaded, $result->status);
    }

    public function test_links_purchase_documents_resolved_from_a_raw_rut_string(): void
    {
        $issuer = Rut::parse('76123456-0');
        $dte = SiiDte::factory()->create([
            'issuer_rut' => $issuer,
            'issued_on' => '2026-09-15',
        ]);

        $upload = $this->mock(IecvUploadGateway::class);
        $upload->expects('upload')->zeroOrMoreTimes()->andReturn('123456789');

        $generator = $this->mock(IecvGenerator::class);
        $generator->expects('generatePurchases')->once()->andReturn('<LibroCompraVenta/>');

        $book = $this->makeService($generator, $upload)->sendPurchases(
            $issuer,
            // A raw string RUT must be parsed to match the stored document.
            [new IecvPurchaseData(33, 1, '2026-09-15', '76123456-0')],
            '2026-09',
            '2026-08-24',
            0,
            $issuer,
        );

        static::assertSame($book->getKey(), SiiDte::query()->find($dte->getKey())->sii_iecv_id);
    }

    public function test_skips_purchase_entries_that_match_no_stored_document(): void
    {
        $issuer = Rut::parse('76123456-0');

        // A document from another issuer and another date must not be linked.
        SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('98999999-9'),
            'issued_on' => '2026-09-20',
        ]);

        $generator = $this->mock(IecvGenerator::class);
        $generator->expects('generatePurchases')->once()->andReturn('<LibroCompraVenta/>');

        $book = $this->makeService($generator)->sendPurchases(
            $issuer,
            [new IecvPurchaseData(33, 1, '2026-09-15', $issuer)],
            '2026-09',
            '2026-08-24',
            0,
            $issuer,
        );

        static::assertSame(0, SiiDte::query()->whereNotNull('sii_iecv_id')->count());
        static::assertSame(IecvStatus::Uploaded, $book->status);
    }

    public function test_links_every_document_of_the_issuer_issued_on_the_entry_date(): void
    {
        $issuer = Rut::parse('76123456-0');

        $first = SiiDte::factory()->create(['issuer_rut' => $issuer, 'issued_on' => '2026-09-15']);
        $second = SiiDte::factory()->create(['issuer_rut' => $issuer, 'issued_on' => '2026-09-15']);

        $generator = $this->mock(IecvGenerator::class);
        $generator->expects('generatePurchases')->once()->andReturn('<LibroCompraVenta/>');

        $book = $this->makeService($generator)->sendPurchases(
            $issuer,
            [new IecvPurchaseData(33, 1, '2026-09-15', $issuer)],
            '2026-09',
            '2026-08-24',
            0,
            $issuer,
        );

        static::assertSame($book->getKey(), $first->fresh()->sii_iecv_id);
        static::assertSame($book->getKey(), $second->fresh()->sii_iecv_id);
    }

    public function test_does_not_link_documents_outside_the_book_period(): void
    {
        $issuer = Rut::parse('76123456-0');

        // The entry date matches the stored document, but both fall outside the period.
        $outside = SiiDte::factory()->create(['issuer_rut' => $issuer, 'issued_on' => '2026-08-15']);

        $generator = $this->mock(IecvGenerator::class);
        $generator->expects('generatePurchases')->once()->andReturn('<LibroCompraVenta/>');

        $book = $this->makeService($generator)->sendPurchases(
            $issuer,
            [new IecvPurchaseData(33, 1, '2026-08-15', $issuer)],
            '2026-09',
            '2026-08-24',
            0,
            $issuer,
        );

        static::assertNull($outside->fresh()->sii_iecv_id);
        static::assertSame(IecvStatus::Uploaded, $book->status);
    }

    public function test_resolves_purchase_documents_with_a_single_query(): void
    {
        $issuer = Rut::parse('76123456-0');

        SiiDte::factory()->times(3)->create(['issuer_rut' => $issuer, 'issued_on' => '2026-09-15']);

        $entries = [];
        foreach ([10, 20, 30] as $folio) {
            $entries[] = new IecvPurchaseData(33, $folio, '2026-09-15', $issuer);
        }

        $generator = $this->mock(IecvGenerator::class);
        $generator->expects('generatePurchases')->once()->andReturn('<LibroCompraVenta/>');

        $connection = $this->app->make('db')->connection();
        $connection->enableQueryLog();

        $this->makeService($generator)->sendPurchases($issuer, $entries, '2026-09', '2026-08-24', 0, $issuer);

        static::assertCount(1, $this->dteSelects($connection));
    }

    /**
     * Filter the query log down to the SELECTs issued against the DTEs table.
     *
     * @return list<array<string, mixed>>
     */
    protected function dteSelects(ConnectionInterface $connection): array
    {
        return array_values(array_filter(
            $connection->getQueryLog(),
            static fn (array $entry): bool => str_starts_with(mb_strtolower($entry['query']), 'select')
                && str_contains($entry['query'], '"sii_dtes"'),
        ));
    }
}
