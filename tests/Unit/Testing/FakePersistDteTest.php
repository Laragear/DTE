<?php

namespace Tests\Unit\Testing;

use Laragear\Dte\Builders\DocumentBuilder;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDtePayload;
use Laragear\Dte\Testing\Fakes\FakePersistDte;
use PHPUnit\Framework\AssertionFailedError;
use Tests\TestCase;

class FakePersistDteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        FakePersistDte::flush();
    }

    protected function createBuilderStub(): DocumentBuilder
    {
        $stub = $this->createStub(DocumentBuilder::class);
        $stub->method('attributes')->willReturn(['document_type' => DteType::Invoice, 'rut' => '11111111-1']);
        $stub->method('payloadData')->willReturn(['xml' => '<test/>']);

        return $stub;
    }

    public function test_handle_captures_call_and_returns_in_memory_dte(): void
    {
        $pipeline = new FakePersistDte($this->app);
        $dte = $pipeline->handle($this->createBuilderStub(), isUpdate: false);

        static::assertInstanceOf(SiiDte::class, $dte);
        static::assertTrue($dte->exists);
        static::assertTrue($dte->wasRecentlyCreated);
        static::assertSame(DteType::Invoice, $dte->document_type);
        static::assertSame('11111111-1', $dte->rut);
    }

    public function test_handle_sets_payload_data(): void
    {
        $pipeline = new FakePersistDte($this->app);
        $dte = $pipeline->handle($this->createBuilderStub());

        static::assertInstanceOf(SiiDtePayload::class, $dte->payload);
        static::assertSame(['xml' => '<test/>'], $dte->payload->data->toArray());
    }

    public function test_handle_records_is_update(): void
    {
        $pipeline = new FakePersistDte($this->app);
        $pipeline->handle($this->createBuilderStub(), isUpdate: true);

        $calls = FakePersistDte::calls();

        static::assertCount(1, $calls);
        static::assertTrue($calls[0]['isUpdate']);
    }

    public function test_assert_called_fails_when_never_called(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageIs('PersistDte pipeline was never called.');

        FakePersistDte::assertCalled();
    }

    public function test_assert_called_passes_when_called_at_least_once(): void
    {
        $pipeline = new FakePersistDte($this->app);
        $pipeline->handle($this->createBuilderStub());

        FakePersistDte::assertCalled();

        static::expectNotToPerformAssertions();
    }

    public function test_assert_called_passes_when_count_matches(): void
    {
        $pipeline = new FakePersistDte($this->app);
        $pipeline->handle($this->createBuilderStub());
        $pipeline->handle($this->createBuilderStub());

        FakePersistDte::assertCalled(2);

        static::expectNotToPerformAssertions();
    }

    public function test_assert_called_fails_on_mismatch(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageIs('Expected PersistDte to be called 3 times, but it was called 1 times.');

        $pipeline = new FakePersistDte($this->app);
        $pipeline->handle($this->createBuilderStub());

        FakePersistDte::assertCalled(3);
    }

    public function test_assert_called_with_passes_when_predicate_matches(): void
    {
        $pipeline = new FakePersistDte($this->app);
        $pipeline->handle($this->createBuilderStub(), isUpdate: true);

        FakePersistDte::assertCalledWith(fn(array $call) => $call['isUpdate'] === true);

        static::expectNotToPerformAssertions();
    }

    public function test_assert_called_with_fails_when_no_match(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageIs('No PersistDte pipeline call matched the given predicate.');

        $pipeline = new FakePersistDte($this->app);
        $pipeline->handle($this->createBuilderStub());

        FakePersistDte::assertCalledWith(fn(array $call) => $call['isUpdate'] === true);
    }

    public function test_calls_returns_all_recorded_calls(): void
    {
        static::assertSame([], FakePersistDte::calls());

        $pipeline = new FakePersistDte($this->app);
        $pipeline->handle($this->createBuilderStub());

        static::assertCount(1, FakePersistDte::calls());
    }

    public function test_flush_clears_recorded_calls(): void
    {
        $pipeline = new FakePersistDte($this->app);
        $pipeline->handle($this->createBuilderStub());

        static::assertCount(1, FakePersistDte::calls());

        FakePersistDte::flush();

        static::assertSame([], FakePersistDte::calls());
    }
}
