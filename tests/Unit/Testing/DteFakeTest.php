<?php

namespace Tests\Unit\Testing;

use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Testing\DteFake;
use Laragear\Dte\Testing\Fakes\FakeInvoiceBuilder;
use PHPUnit\Framework\AssertionFailedError;
use ReflectionClass;
use Tests\TestCase;

class DteFakeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DteFake::flushAll();
    }

    public function test_fake_returns_new_instance(): void
    {
        $fake = DteFake::fake();

        static::assertInstanceOf(DteFake::class, $fake);
    }

    public function test_assert_created_with_null_fails_when_no_documents(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageIs('No documents were created.');

        $fake = new DteFake;
        $fake->assertCreated();
    }

    public function test_assert_created_with_null_passes_when_documents_exist(): void
    {
        $dte = new SiiDte;
        $dte->setRawAttributes(['id' => 1, 'document_type' => DteType::Invoice]);
        $dte->exists = true;
        $prop = (new ReflectionClass(FakeInvoiceBuilder::class))->getProperty('created');
        $prop->setValue(null, [$dte]);

        $fake = new DteFake;
        $fake->assertCreated();

        static::expectNotToPerformAssertions();
    }

    public function test_assert_created_fails_when_count_mismatches(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageIs('Expected 3 documents to be created, but 1 were created.');

        $dte = new SiiDte;
        $dte->setRawAttributes(['id' => 1, 'document_type' => DteType::Invoice]);
        $dte->exists = true;
        $prop = (new ReflectionClass(FakeInvoiceBuilder::class))->getProperty('created');
        $prop->setValue(null, [$dte]);

        $fake = new DteFake;
        $fake->assertCreated(3);
    }

    public function test_assert_not_created_fails_when_documents_exist(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageIs('Expected 0 documents to be created, but 1 were created.');

        $dte = new SiiDte;
        $dte->setRawAttributes(['id' => 1, 'document_type' => DteType::Invoice]);
        $dte->exists = true;
        $prop = (new ReflectionClass(FakeInvoiceBuilder::class))->getProperty('created');
        $prop->setValue(null, [$dte]);

        $fake = new DteFake;
        $fake->assertNotCreated();
    }

    public function test_last_created_returns_latest_across_types(): void
    {
        $dte1 = new SiiDte;
        $dte1->setRawAttributes(['id' => 1, 'document_type' => DteType::Invoice]);
        $dte1->exists = true;
        $dte2 = new SiiDte;
        $dte2->setRawAttributes(['id' => 2, 'document_type' => DteType::Invoice]);
        $dte2->exists = true;

        $prop = (new ReflectionClass(FakeInvoiceBuilder::class))->getProperty('created');
        $prop->setValue(null, [$dte1, $dte2]);

        $fake = new DteFake;
        $last = $fake->lastCreated();

        static::assertSame(2, $last->getKey());
    }

    public function test_last_created_returns_null_when_empty(): void
    {
        $fake = new DteFake;

        static::assertNull($fake->lastCreated());
    }

    public function test_assert_created_for_with_null_passes_when_type_matches(): void
    {
        $dte = new SiiDte;
        $dte->setRawAttributes(['id' => 1, 'document_type' => DteType::Invoice]);
        $dte->exists = true;
        $prop = (new ReflectionClass(FakeInvoiceBuilder::class))->getProperty('created');
        $prop->setValue(null, [$dte]);

        $fake = new DteFake;
        $fake->assertCreatedFor(DteType::Invoice);

        static::expectNotToPerformAssertions();
    }

    public function test_assert_created_for_with_null_fails_when_no_type_match(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageIs('No documents of type 39 were created.');

        $dte = new SiiDte;
        $dte->setRawAttributes(['id' => 1, 'document_type' => DteType::Invoice]);
        $dte->exists = true;
        $prop = (new ReflectionClass(FakeInvoiceBuilder::class))->getProperty('created');
        $prop->setValue(null, [$dte]);

        $fake = new DteFake;
        $fake->assertCreatedFor(DteType::Receipt);
    }

    public function test_assert_created_for_fails_on_count_mismatch(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageIs('Expected 3 documents of type 33 to be created, but 1 were created.');

        $dte = new SiiDte;
        $dte->setRawAttributes(['id' => 1, 'document_type' => DteType::Invoice]);
        $dte->exists = true;
        $prop = (new ReflectionClass(FakeInvoiceBuilder::class))->getProperty('created');
        $prop->setValue(null, [$dte]);

        $fake = new DteFake;
        $fake->assertCreatedFor(DteType::Invoice, 3);
    }
}
