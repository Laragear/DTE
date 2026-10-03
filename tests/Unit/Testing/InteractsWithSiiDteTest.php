<?php

namespace Tests\Unit\Testing;

use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Data\ReceiverData;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\SiiRut;
use Laragear\Dte\Facades\SiiInvoice;
use Laragear\Dte\Facades\SiiReceipt;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Testing\DteFake;
use Laragear\Dte\Testing\Fakes\FakeCreditNoteBuilder;
use Laragear\Dte\Testing\Fakes\FakeDebitNoteBuilder;
use Laragear\Dte\Testing\Fakes\FakeDispatchGuideBuilder;
use Laragear\Dte\Testing\Fakes\FakeInvoiceBuilder;
use Laragear\Dte\Testing\Fakes\FakeInvoiceLiquidationBuilder;
use Laragear\Dte\Testing\Fakes\FakePurchaseInvoiceBuilder;
use Laragear\Dte\Testing\Fakes\FakeReceiptBuilder;
use Laragear\Dte\Testing\InteractsWithSiiDte;
use LogicException;
use Tests\DatabaseTestCase;
use Tests\Unit\Builders\Fixtures\BuilderFixture;

class InteractsWithSiiDteTest extends DatabaseTestCase
{
    use InteractsWithSiiDte;

    /*
     |--------------------------------------------------------------------------
     | SetUp & TearDown tests
     |--------------------------------------------------------------------------
     */

    public function test_set_up_configures_issuer_and_fakes(): void
    {
        $this->setUpInteractsWithSiiDte();

        $manager = $this->app->make(ConfigurationManager::class);
        static::assertTrue($manager->hasIssuerResolver());
        static::assertNotNull($this->dteFake);
    }

    public function test_tear_down_restores_facades_and_flushes(): void
    {
        $this->setUpInteractsWithSiiDte();

        $builder = SiiInvoice::fake()->issuedBy(BuilderFixture::issuer())
            ->receivedBy(BuilderFixture::receiver())
            ->addItem(BuilderFixture::item());
        $builder->build();

        $this->tearDownInteractsWithSiiDte();

        static::assertNull($this->dteFake);
        static::assertSame([], SiiInvoice::created());
    }

    /*
     |--------------------------------------------------------------------------
     | setUpCafs / createFakeCafs
     |--------------------------------------------------------------------------
     */

    public function test_set_up_cafs_returns_default_config(): void
    {
        $cafTypes = $this->setUpCafs();
        static::assertArrayHasKey(SiiRut::DEFAULT->value, $cafTypes);
        static::assertContains(DteType::Invoice, $cafTypes[SiiRut::DEFAULT->value]);
    }

    public function test_create_fake_cafs_creates_database_entries(): void
    {
        $this->createFakeCafs();

        static::assertDatabaseHas('sii_cafs', [
            'document_type' => DteType::Invoice,
        ]);
    }

    /*
     |--------------------------------------------------------------------------
     | configureDteIssuer
     |--------------------------------------------------------------------------
     */

    public function test_configure_dte_issuer_sets_issuer_resolver(): void
    {
        $this->configureDteIssuer('12.345.678-9', 'Acme Corp');

        $manager = $this->app->make(ConfigurationManager::class);
        $issuer = $manager->getIssuer();

        static::assertSame('Acme Corp', $issuer->name);
        static::assertTrue($issuer->rut->isEqual('12.345.678-9'));
    }

    /*
     |--------------------------------------------------------------------------
     | fakeDte
     |--------------------------------------------------------------------------
     */

    public function test_fake_dte_fakes_all_facades(): void
    {
        $dteFake = $this->fakeDte();

        static::assertInstanceOf(DteFake::class, $dteFake);
        static::assertSame($dteFake, $this->dteFake);

        static::assertInstanceOf(FakeInvoiceBuilder::class, SiiInvoice::fake());
        static::assertInstanceOf(FakeReceiptBuilder::class, SiiReceipt::fake());
    }

    /*
     |--------------------------------------------------------------------------
     | configureBuilder
     |--------------------------------------------------------------------------
     */

    public function test_configure_builder_sets_issuer(): void
    {
        $builder = SiiInvoice::fake();
        $this->configureBuilder($builder, issuer: BuilderFixture::issuer());

        static::assertNotNull($builder->issuer());
    }

    public function test_configure_builder_sets_receiver_from_string(): void
    {
        $builder = SiiInvoice::fake();
        $this->configureBuilder($builder, receiver: '12.345.678-9');

        static::assertNotNull($builder->receiver());
    }

    public function test_configure_builder_sets_receiver_from_data(): void
    {
        $receiver = ReceiverData::make('12.345.678-9', 'Customer');
        $builder = SiiInvoice::fake();
        $this->configureBuilder($builder, receiver: $receiver);

        static::assertSame($receiver, $builder->receiver());
    }

    public function test_configure_builder_adds_items(): void
    {
        $builder = SiiInvoice::fake();
        $this->configureBuilder($builder, items: [
            ['Widget', 1000, 2],
            Item::make('Service', 5000),
        ]);

        static::assertCount(2, $builder->items());
    }

    /*
     |--------------------------------------------------------------------------
     | new*() Builder Shortcuts
     |--------------------------------------------------------------------------
     */

    public function test_new_invoice_returns_fake_builder(): void
    {
        $builder = $this->newInvoice(
            issuer: BuilderFixture::issuer(),
            receiver: '12.345.678-9',
            items: [['Service', 5000]],
        );

        static::assertInstanceOf(FakeInvoiceBuilder::class, $builder);
        static::assertNotNull($builder->issuer());
        static::assertNotNull($builder->receiver());
        static::assertCount(1, $builder->items());
    }

    public function test_new_receipt_returns_fake_builder(): void
    {
        $builder = $this->newReceipt(items: [['Item', 1000]]);
        static::assertInstanceOf(FakeReceiptBuilder::class, $builder);
        static::assertCount(1, $builder->items());
    }

    public function test_new_credit_note_returns_fake_builder(): void
    {
        $builder = $this->newCreditNote(items: [['Item', 1000]]);
        static::assertInstanceOf(FakeCreditNoteBuilder::class, $builder);
    }

    public function test_new_debit_note_returns_fake_builder(): void
    {
        $builder = $this->newDebitNote(items: [['Item', 1000]]);
        static::assertInstanceOf(FakeDebitNoteBuilder::class, $builder);
    }

    public function test_new_dispatch_guide_returns_fake_builder(): void
    {
        $builder = $this->newDispatchGuide(items: [['Item', 1000]]);
        static::assertInstanceOf(FakeDispatchGuideBuilder::class, $builder);
    }

    public function test_new_purchase_invoice_returns_fake_builder(): void
    {
        $builder = $this->newPurchaseInvoice(items: [['Item', 1000]]);
        static::assertInstanceOf(FakePurchaseInvoiceBuilder::class, $builder);
    }

    public function test_new_invoice_liquidation_returns_fake_builder(): void
    {
        $builder = $this->newInvoiceLiquidation(items: [['Item', 1000]]);
        static::assertInstanceOf(FakeInvoiceLiquidationBuilder::class, $builder);
    }

    /*
     |--------------------------------------------------------------------------
     | withPdfStubs / getStub
     |--------------------------------------------------------------------------
     */

    public function test_get_stub_loads_file_content(): void
    {
        $content = static::getStub('stub_ted.xml');

        static::assertStringContainsString('<TED', $content);
        static::assertStringContainsString('xmlns="http://www.sii.cl/SiiDte"', $content);
    }

    public function test_with_pdf_stubs_injects_xml_and_mocks_generator(): void
    {
        $dte = SiiDte::factory()->create();
        $dte->payload()->create(['data' => []]);

        $this->withPdfStubs($dte);

        static::assertStringContainsString('<TED', $dte->payload->xml);
    }

    public function test_with_pdf_stubs_skips_when_xml_already_set(): void
    {
        $dte = SiiDte::factory()->create();
        $dte->payload()->create(['data' => [], 'xml' => '<custom/>']);

        $this->withPdfStubs($dte);

        static::assertSame('<custom/>', $dte->payload->xml);
    }

    /*
     |--------------------------------------------------------------------------
     | assertDteCreated / assertDtePayload
     |--------------------------------------------------------------------------
     */

    public function test_assert_dte_created_throws_without_fake_dte(): void
    {
        $this->dteFake = null;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('Call fakeDte() before asserting.');

        $this->assertDteCreated();
    }

    public function test_assert_dte_created_delegates_to_fake(): void
    {
        $this->fakeDte();

        SiiInvoice::fake()->issuedBy(BuilderFixture::issuer())
            ->receivedBy(BuilderFixture::receiver())
            ->addItem(BuilderFixture::item())
            ->build();

        $this->assertDteCreated(times: 1);

        static::assertTrue(true);
    }

    public function test_assert_dte_created_filters_by_type(): void
    {
        $this->fakeDte();

        SiiInvoice::fake()->issuedBy(BuilderFixture::issuer())
            ->receivedBy(BuilderFixture::receiver())
            ->addItem(BuilderFixture::item())
            ->build();

        $this->assertDteCreated(DteType::Invoice, times: 1);

        static::assertTrue(true);
    }

    public function test_assert_dte_payload_checks_data(): void
    {
        $dte = $this->newInvoice(
            issuer: BuilderFixture::issuer(),
            receiver: BuilderFixture::receiver(),
            items: [['Service', 5000]],
        )->build();

        $this->assertDtePayload($dte, [
            'items.0.name' => 'Service',
        ]);
    }

    /*
     |--------------------------------------------------------------------------
     | Integration: create + persist + cleanup
     |--------------------------------------------------------------------------
     */

    public function test_create_persists_to_database(): void
    {
        $this->fakeDte();

        $dte = $this->newInvoice(
            issuer: BuilderFixture::issuer(),
            receiver: BuilderFixture::receiver(),
            items: [['Service', 5000]],
        )->build();

        static::assertTrue($dte->exists);
        static::assertDatabaseHas('sii_dtes', ['id' => $dte->getKey()]);
    }

    public function test_tear_down_deletes_persisted_documents(): void
    {
        $this->fakeDte();

        $dte = $this->newInvoice(
            issuer: BuilderFixture::issuer(),
            receiver: BuilderFixture::receiver(),
            items: [['Service', 5000]],
        )->build();

        $id = $dte->getKey();
        $this->tearDownInteractsWithSiiDte();

        static::assertDatabaseMissing('sii_dtes', ['id' => $id]);
    }
}
