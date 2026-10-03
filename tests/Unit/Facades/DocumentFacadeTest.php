<?php

namespace Tests\Unit\Facades;

use Laragear\Dte\Facades\SiiCreditNote;
use Laragear\Dte\Facades\SiiDebitNote;
use Laragear\Dte\Facades\SiiDispatchGuide;
use Laragear\Dte\Facades\SiiInvoice;
use Laragear\Dte\Facades\SiiInvoiceLiquidation;
use Laragear\Dte\Facades\SiiPurchaseInvoice;
use Laragear\Dte\Facades\SiiReceipt;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Testing\Fakes\FakeCreditNoteBuilder;
use Laragear\Dte\Testing\Fakes\FakeDebitNoteBuilder;
use Laragear\Dte\Testing\Fakes\FakeDispatchGuideBuilder;
use Laragear\Dte\Testing\Fakes\FakeInvoiceBuilder;
use Laragear\Dte\Testing\Fakes\FakeInvoiceLiquidationBuilder;
use Laragear\Dte\Testing\Fakes\FakePurchaseInvoiceBuilder;
use Laragear\Dte\Testing\Fakes\FakeReceiptBuilder;
use Laragear\Dte\Testing\InteractsWithSiiDte;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\DatabaseTestCase;

class DocumentFacadeTest extends DatabaseTestCase
{
    use InteractsWithSiiDte;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpInteractsWithSiiDte();
    }

    protected function tearDown(): void
    {
        $this->tearDownInteractsWithSiiDte();

        parent::tearDown();
    }

    public static function providesFacades(): array
    {
        return [
            'SiiInvoice' => [SiiInvoice::class, FakeInvoiceBuilder::class],
            'SiiReceipt' => [SiiReceipt::class, FakeReceiptBuilder::class],
            'SiiCreditNote' => [SiiCreditNote::class, FakeCreditNoteBuilder::class],
            'SiiDebitNote' => [SiiDebitNote::class, FakeDebitNoteBuilder::class],
            'SiiDispatchGuide' => [SiiDispatchGuide::class, FakeDispatchGuideBuilder::class],
            'SiiPurchaseInvoice' => [SiiPurchaseInvoice::class, FakePurchaseInvoiceBuilder::class],
            'SiiInvoiceLiquidation' => [SiiInvoiceLiquidation::class, FakeInvoiceLiquidationBuilder::class],
        ];
    }

    #[DataProvider('providesFacades')]
    public function test_fake_returns_fake_builder(string $facadeClass, string $fakeClass): void
    {
        $fake = $facadeClass::fake();

        static::assertInstanceOf($fakeClass, $fake);

        $facadeClass::restore();
    }

    #[DataProvider('providesFacades')]
    public function test_assert_not_created_passes_when_no_documents(string $facadeClass, string $fakeClass): void
    {
        $facadeClass::assertNotCreated();

        static::expectNotToPerformAssertions();
    }

    #[DataProvider('providesFacades')]
    public function test_last_created_returns_null_when_empty(string $facadeClass, string $fakeClass): void
    {
        static::assertNull($facadeClass::lastCreated());
    }

    #[DataProvider('providesFacades')]
    public function test_created_returns_empty_when_no_documents(string $facadeClass, string $fakeClass): void
    {
        static::assertSame([], $facadeClass::created());
    }

    #[DataProvider('providesFacades')]
    public function test_assert_created_passes_when_documents_exist(string $facadeClass, string $fakeClass): void
    {
        $facadeClass::fake();

        $dte = new SiiDte;
        $dte->setRawAttributes(['id' => 1]);
        $dte->exists = true;
        $prop = (new ReflectionClass($fakeClass))->getProperty('created');
        $prop->setValue(null, [$dte]);

        $facadeClass::assertCreated(1);

        static::expectNotToPerformAssertions();
    }

    #[DataProvider('providesFacades')]
    public function test_assert_created_with_null_times_passes(string $facadeClass, string $fakeClass): void
    {
        $facadeClass::fake();

        $dte = new SiiDte;
        $dte->setRawAttributes(['id' => 1]);
        $dte->exists = true;
        $prop = (new ReflectionClass($fakeClass))->getProperty('created');
        $prop->setValue(null, [$dte]);

        $facadeClass::assertCreated();

        static::expectNotToPerformAssertions();
    }

    #[DataProvider('providesFacades')]
    public function test_created_returns_documents(string $facadeClass, string $fakeClass): void
    {
        $facadeClass::fake();

        $dte = new SiiDte;
        $dte->setRawAttributes(['id' => 1]);
        $dte->exists = true;
        $prop = (new ReflectionClass($fakeClass))->getProperty('created');
        $prop->setValue(null, [$dte]);

        $created = $facadeClass::created();

        static::assertCount(1, $created);
        static::assertInstanceOf(SiiDte::class, $created[0]);
    }

    #[DataProvider('providesFacades')]
    public function test_last_created_returns_latest(string $facadeClass, string $fakeClass): void
    {
        $facadeClass::fake();

        $dte1 = new SiiDte;
        $dte1->setRawAttributes(['id' => 1]);
        $dte1->exists = true;
        $dte2 = new SiiDte;
        $dte2->setRawAttributes(['id' => 2]);
        $dte2->exists = true;

        $prop = (new ReflectionClass($fakeClass))->getProperty('created');
        $prop->setValue(null, [$dte1, $dte2]);

        $last = $facadeClass::lastCreated();

        static::assertSame(2, $last->getKey());
    }
}
