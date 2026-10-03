<?php

namespace Laragear\Dte\Testing;

use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Testing\Fakes\FakeCreditNoteBuilder;
use Laragear\Dte\Testing\Fakes\FakeDebitNoteBuilder;
use Laragear\Dte\Testing\Fakes\FakeDispatchGuideBuilder;
use Laragear\Dte\Testing\Fakes\FakeDocumentBuilder;
use Laragear\Dte\Testing\Fakes\FakeInvoiceBuilder;
use Laragear\Dte\Testing\Fakes\FakeInvoiceLiquidationBuilder;
use Laragear\Dte\Testing\Fakes\FakePurchaseInvoiceBuilder;
use Laragear\Dte\Testing\Fakes\FakeReceiptBuilder;
use PHPUnit\Framework\Assert;

class DteFake
{
    /**
     * All fake classes that can be tracked.
     *
     * @var list<class-string<FakeDocumentBuilder>>
     */
    protected const array FAKE_CLASSES = [
        FakeInvoiceBuilder::class,
        FakeReceiptBuilder::class,
        FakeCreditNoteBuilder::class,
        FakeDebitNoteBuilder::class,
        FakeDispatchGuideBuilder::class,
        FakePurchaseInvoiceBuilder::class,
        FakeInvoiceLiquidationBuilder::class,
    ];

    /**
     * Assert the expected number of documents were created.
     */
    public function assertCreated(?int $times = null): void
    {
        $count = count($this->created());

        if ($times === null) {
            if ($count === 0) {
                Assert::fail('No documents were created.');
            }

            return;
        }

        if ($count !== $times) {
            Assert::fail("Expected {$times} documents to be created, but {$count} were created.");
        }
    }

    /**
     * Assert the expected number of documents of a specific type were created.
     */
    public function assertCreatedFor(int|DteType $type, ?int $times = null): void
    {
        $count = count($this->createdFor($type));

        if ($times === null) {
            if ($count === 0) {
                Assert::fail("No documents of type {$type->value} were created.");
            }

            return;
        }

        if ($count !== $times) {
            Assert::fail("Expected {$times} documents of type {$type->value} to be created, but {$count} were created.");
        }
    }

    /**
     * Assert no documents were created.
     */
    public function assertNotCreated(): void
    {
        $this->assertCreated(0);
    }

    /**
     * Return all created documents across all fake types.
     *
     * @return list<SiiDte>
     */
    public function created(): array
    {
        $all = [];

        foreach (static::FAKE_CLASSES as $fakeClass) {
            $all = array_merge($all, $fakeClass::created());
        }

        return $all;
    }

    /**
     * Return created documents filtered by document type.
     *
     * @return list<SiiDte>
     */
    public function createdFor(int|DteType $type): array
    {
        $dteType = $type instanceof DteType ? $type : DteType::from($type);

        return array_filter(
            $this->created(),
            static fn(SiiDte $dte): bool => $dte->document_type === $dteType,
        );
    }

    /**
     * Return the last created document of any type, or null.
     */
    public function lastCreated(): ?SiiDte
    {
        $all = $this->created();

        return $all[array_key_last($all)] ?? null;
    }

    /**
     * Create a new DTE Fake instance.
     */
    public static function fake(): static
    {
        return new static;
    }

    /**
     * Flush all creation records across all fake types.
     */
    public static function flushAll(): void
    {
        foreach (static::FAKE_CLASSES as $fakeClass) {
            $fakeClass::flushCreated();
        }
    }
}
