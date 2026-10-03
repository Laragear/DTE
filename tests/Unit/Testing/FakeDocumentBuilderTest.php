<?php

namespace Tests\Unit\Testing;

use Error;
use Laragear\Dte\Actions\CompileDte\Compile;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Testing\Fakes\FakeInvoiceBuilder;
use Laragear\Dte\Testing\InteractsWithSiiDte;
use ReflectionClass;
use Tests\DatabaseTestCase;
use Tests\Unit\Builders\Fixtures\BuilderFixture;

class FakeDocumentBuilderTest extends DatabaseTestCase
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

    protected function newConfiguredBuilder(): FakeInvoiceBuilder
    {
        return $this->newInvoice(
            issuer: BuilderFixture::issuer(),
            receiver: BuilderFixture::receiver(),
            items: [['Service', 5000]],
        );
    }

    /*
     |--------------------------------------------------------------------------
     | Without storing DTE
     |--------------------------------------------------------------------------
     */

    public function test_without_storing_dte_returns_new_instance_with_flag(): void
    {
        $builder = FakeInvoiceBuilder::withoutStoringDte();

        static::assertInstanceOf(FakeInvoiceBuilder::class, $builder);

        $reflection = new ReflectionClass($builder);
        $property = $reflection->getProperty('storeDte');
        $property->setAccessible(true);

        static::assertFalse($property->getValue());
    }

    public function test_without_storing_dte_resets_after_flush(): void
    {
        FakeInvoiceBuilder::withoutStoringDte();

        FakeInvoiceBuilder::flushCreated();

        $builder = app(FakeInvoiceBuilder::class);

        $reflection = new ReflectionClass($builder);
        $property = $reflection->getProperty('storeDte');
        $property->setAccessible(true);

        static::assertTrue($property->getValue());
    }

    /*
     |--------------------------------------------------------------------------
     | Build
     |--------------------------------------------------------------------------
     */

    public function test_build_returns_dte(): void
    {
        $dte = $this->newConfiguredBuilder()->build();

        static::assertInstanceOf(SiiDte::class, $dte);
        static::assertTrue($dte->exists);
        static::assertTrue($dte->wasRecentlyCreated);
    }

    public function test_draft_returns_draft_dte(): void
    {
        $dte = $this->newConfiguredBuilder()->draft();

        static::assertInstanceOf(SiiDte::class, $dte);
        static::assertTrue($dte->exists);
        static::assertSame(DteStatus::Draft, $dte->status);
        static::assertDatabaseHas('sii_dtes', ['id' => $dte->getKey()]);
    }

    public function test_build_persists_to_database_by_default(): void
    {
        $dte = $this->newConfiguredBuilder()->build();

        static::assertDatabaseHas('sii_dtes', ['id' => $dte->getKey()]);
    }

    /*
     |--------------------------------------------------------------------------
     | buildDte without storing
     |--------------------------------------------------------------------------
     */

    public function test_build_dte_when_not_storing_creates_in_memory(): void
    {
        $builder = FakeInvoiceBuilder::withoutStoringDte();

        $this->configureBuilder($builder, receiver: BuilderFixture::receiver(), items: [['Service', 5000]]);

        $dte = $builder->build();

        static::assertInstanceOf(SiiDte::class, $dte);
        static::assertTrue($dte->exists);
        static::assertTrue($dte->wasRecentlyCreated);
        static::assertDatabaseMissing('sii_dtes', ['id' => $dte->getKey()]);
    }

    /*
     |--------------------------------------------------------------------------
     | assertCreated / assertNotCreated
     |--------------------------------------------------------------------------
     */

    public function test_assert_created_null_fails_when_none(): void
    {
        $this->expectException(Error::class);

        FakeInvoiceBuilder::assertCreated();
    }

    public function test_assert_created_passes_when_documents_exist(): void
    {
        $this->newConfiguredBuilder()->build();

        FakeInvoiceBuilder::assertCreated();

        static::expectNotToPerformAssertions();
    }

    public function test_assert_created_with_count_passes_when_match(): void
    {
        $this->newConfiguredBuilder()->build();
        $this->newConfiguredBuilder()->build();

        FakeInvoiceBuilder::assertCreated(2);

        static::expectNotToPerformAssertions();
    }

    public function test_assert_created_with_count_fails_on_mismatch(): void
    {
        $this->expectException(Error::class);

        $this->newConfiguredBuilder()->build();

        FakeInvoiceBuilder::assertCreated(3);
    }

    public function test_assert_not_created_fails_when_documents_exist(): void
    {
        $this->expectException(Error::class);

        $this->newConfiguredBuilder()->build();

        FakeInvoiceBuilder::assertNotCreated();
    }

    public function test_assert_not_created_passes_when_none(): void
    {
        FakeInvoiceBuilder::assertNotCreated();

        static::expectNotToPerformAssertions();
    }

    /*
     |--------------------------------------------------------------------------
     | created / lastCreated
     |--------------------------------------------------------------------------
     */

    public function test_created_returns_all_documents(): void
    {
        static::assertSame([], FakeInvoiceBuilder::created());

        $this->newConfiguredBuilder()->build();

        static::assertCount(1, FakeInvoiceBuilder::created());
    }

    public function test_last_created_returns_latest(): void
    {
        $first = $this->newConfiguredBuilder()->build();
        $second = $this->newConfiguredBuilder()->build();

        static::assertSame($second->getKey(), FakeInvoiceBuilder::lastCreated()->getKey());
    }

    public function test_last_created_returns_null_when_empty(): void
    {
        static::assertNull(FakeInvoiceBuilder::lastCreated());
    }

    /*
     |--------------------------------------------------------------------------
     | flushCreated
     |--------------------------------------------------------------------------
     */

    public function test_flush_created_clears_state(): void
    {
        $this->newConfiguredBuilder()->build();

        static::assertCount(1, FakeInvoiceBuilder::created());

        FakeInvoiceBuilder::flushCreated();

        static::assertSame([], FakeInvoiceBuilder::created());
        static::assertNull(FakeInvoiceBuilder::lastCreated());
    }

    public function test_flush_created_resets_store_dte_flag(): void
    {
        FakeInvoiceBuilder::withoutStoringDte();

        FakeInvoiceBuilder::flushCreated();

        $builder = app(FakeInvoiceBuilder::class);
        $reflection = new ReflectionClass($builder);
        $property = $reflection->getProperty('storeDte');
        $property->setAccessible(true);

        static::assertTrue($property->getValue());
    }

    /*
     |--------------------------------------------------------------------------
     | build with sync
     |--------------------------------------------------------------------------
     */

    public function test_build_with_sync_true_persists_and_compiles(): void
    {
        $this->mock(Compile::class, fn($mock) => $mock->shouldReceive('forDte')->once());

        $dte = $this->newConfiguredBuilder()->buildSync();

        static::assertInstanceOf(SiiDte::class, $dte);
        static::assertTrue($dte->exists);
        static::assertDatabaseHas('sii_dtes', ['id' => $dte->getKey()]);
        static::assertDatabaseHas('sii_dte_payloads', ['sii_dte_id' => $dte->getKey()]);
    }

    /*
     |--------------------------------------------------------------------------
     | persistAndCompile
     |--------------------------------------------------------------------------
     */

    public function test_persist_and_compile_when_not_storing_saves_and_compiles(): void
    {
        $this->mock(Compile::class, fn($mock) => $mock->shouldReceive('forDte')->once());

        $builder = FakeInvoiceBuilder::withoutStoringDte();
        $this->configureBuilder($builder, receiver: BuilderFixture::receiver(), items: [['Service', 5000]]);

        $dte = $builder->buildSync();

        static::assertTrue($dte->exists);
        static::assertDatabaseHas('sii_dtes', ['id' => $dte->getKey()]);
        static::assertDatabaseHas('sii_dte_payloads', ['sii_dte_id' => $dte->getKey()]);
    }

    /*
     |--------------------------------------------------------------------------
     | rebuild
     |--------------------------------------------------------------------------
     */

    public function test_rebuild_modifies_existing_dte(): void
    {
        $dte = $this->newConfiguredBuilder()->build();

        $builder = $this->newConfiguredBuilder();
        $builder->hydrate($dte);

        $this->mock(Compile::class, fn($mock) => $mock->shouldReceive('forDte')->once());

        $updated = $builder->build(sync: true);

        static::assertSame($dte->getKey(), $updated->getKey());
        static::assertDatabaseHas('sii_dtes', ['id' => $dte->getKey()]);
    }
}
