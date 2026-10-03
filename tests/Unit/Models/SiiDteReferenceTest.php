<?php

namespace Tests\Unit\Models;

use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\ReferenceType;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteReference;
use Tests\DatabaseTestCase;

class SiiDteReferenceTest extends DatabaseTestCase
{
    public function test_document_type_resolves_dte_type(): void
    {
        $reference = SiiDteReference::create([
            'sii_dte_id' => SiiDte::factory()->create()->getKey(),
            'document_type' => DteType::Invoice->value,
            'folio' => '123',
            'date' => '2026-08-15',
            'reason' => 'Test',
            'reference_code' => 1,
        ]);

        $documentType = $reference->document_type;

        static::assertInstanceOf(DteType::class, $documentType);
        static::assertSame(DteType::Invoice, $documentType);
    }

    public function test_document_type_resolves_numeric_reference_type(): void
    {
        $reference = SiiDteReference::create([
            'sii_dte_id' => SiiDte::factory()->create()->getKey(),
            'document_type' => ReferenceType::PurchaseOrder->value,
            'folio' => 'PO-123',
            'date' => '2026-08-15',
            'reason' => 'Purchase order reference',
            'reference_code' => null,
        ]);

        $documentType = $reference->document_type;

        static::assertInstanceOf(ReferenceType::class, $documentType);
        static::assertSame(ReferenceType::PurchaseOrder, $documentType);
    }

    public function test_document_type_resolves_alphabetic_reference_type(): void
    {
        $reference = SiiDteReference::create([
            'sii_dte_id' => SiiDte::factory()->create()->getKey(),
            'document_type' => ReferenceType::TestSet->value,
            'folio' => '0',
            'date' => '2026-08-15',
            'reason' => 'Test set reference',
            'reference_code' => null,
        ]);

        $documentType = $reference->document_type;

        static::assertInstanceOf(ReferenceType::class, $documentType);
        static::assertSame(ReferenceType::TestSet, $documentType);
    }

    public function test_document_type_resolves_hes_reference_type(): void
    {
        $reference = SiiDteReference::create([
            'sii_dte_id' => SiiDte::factory()->create()->getKey(),
            'document_type' => ReferenceType::ServiceEntrySheet->value,
            'folio' => 'HES-001',
            'date' => '2026-08-15',
            'reason' => 'Service entry',
            'reference_code' => null,
        ]);

        $documentType = $reference->document_type;

        static::assertInstanceOf(ReferenceType::class, $documentType);
        static::assertSame(ReferenceType::ServiceEntrySheet, $documentType);
    }

    public function test_document_type_resolves_credit_note(): void
    {
        $reference = SiiDteReference::create([
            'sii_dte_id' => SiiDte::factory()->create()->getKey(),
            'document_type' => DteType::CreditNote->value,
            'folio' => '456',
            'date' => '2026-08-15',
            'reason' => 'Anula documento',
            'reference_code' => 1,
        ]);

        $documentType = $reference->document_type;

        static::assertInstanceOf(DteType::class, $documentType);
        static::assertSame(DteType::CreditNote, $documentType);
    }

    public function test_document_type_resolves_debit_note(): void
    {
        $reference = SiiDteReference::create([
            'sii_dte_id' => SiiDte::factory()->create()->getKey(),
            'document_type' => DteType::DebitNote->value,
            'folio' => '789',
            'date' => '2026-08-15',
            'reason' => 'Corrige montos',
            'reference_code' => 3,
        ]);

        $documentType = $reference->document_type;

        static::assertInstanceOf(DteType::class, $documentType);
        static::assertSame(DteType::DebitNote, $documentType);
    }

    public function test_document_type_returns_null_when_missing(): void
    {
        $reference = new SiiDteReference;

        static::assertNull($reference->document_type);
    }

    public function test_dte_and_target_dte_relations(): void
    {
        $owner = SiiDte::factory()->create();
        $target = SiiDte::factory()->create();

        $reference = SiiDteReference::create([
            'sii_dte_id' => $owner->getKey(),
            'target_dte_id' => $target->getKey(),
            'document_type' => DteType::Invoice->value,
            'folio' => '123',
            'date' => '2026-08-15',
            'reason' => 'Test',
            'reference_code' => 1,
        ]);

        static::assertTrue($reference->dte()->exists());
        static::assertTrue($reference->dte->is($owner));
        static::assertTrue($reference->targetDte()->exists());
        static::assertTrue($reference->targetDte->is($target));
    }
}
