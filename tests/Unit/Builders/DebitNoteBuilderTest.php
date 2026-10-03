<?php

namespace Tests\Unit\Builders;

use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Laragear\Dte\Builders\DebitNoteBuilder;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\ReferenceType;
use Laragear\Dte\Models\SiiDte;
use Tests\DatabaseTestCase;

class DebitNoteBuilderTest extends DatabaseTestCase
{
    public function test_annul_overwrites_references(): void
    {
        $builder = $this->app->make(DebitNoteBuilder::class);
        $date = new DateTimeImmutable('2026-08-15');

        $builder->annul(DteType::CreditNote, '123', $date);

        $references = $builder->references();
        static::assertCount(1, $references);
        static::assertSame(DteType::CreditNote, $references[0]->documentType);
        static::assertSame('123', $references[0]->folio);
        static::assertSame($date, $references[0]->date);
        static::assertSame('Anula documento', $references[0]->reason);
        static::assertSame(1, $references[0]->referenceCode);
    }

    public function test_amend_overwrites_references(): void
    {
        $builder = $this->app->make(DebitNoteBuilder::class);
        $date = new DateTimeImmutable('2026-08-15');

        $builder->amend(DteType::InvoiceExempt, '456', $date);

        $references = $builder->references();
        static::assertCount(1, $references);
        static::assertSame(DteType::InvoiceExempt, $references[0]->documentType);
        static::assertSame('456', $references[0]->folio);
        static::assertSame($date, $references[0]->date);
        static::assertSame('Corrige texto', $references[0]->reason);
        static::assertSame(2, $references[0]->referenceCode);
    }

    public function test_charge_overwrites_references(): void
    {
        $builder = $this->app->make(DebitNoteBuilder::class);
        $date = new DateTimeImmutable('2026-08-15');

        $builder->charge(ReferenceType::PurchaseOrder, 'PO-789', $date);

        $references = $builder->references();
        static::assertCount(1, $references);
        static::assertSame(ReferenceType::PurchaseOrder, $references[0]->documentType);
        static::assertSame('PO-789', $references[0]->folio);
        static::assertSame($date, $references[0]->date);
        static::assertSame('Corrige montos', $references[0]->reason);
        static::assertSame(3, $references[0]->referenceCode);
    }

    public function test_methods_accept_sii_dte(): void
    {
        $dte = SiiDte::factory()->make([
            'document_type' => DteType::Invoice,
            'folio' => 123,
            'issued_on' => new Carbon('2026-08-15'),
        ]);

        $builder = $this->app->make(DebitNoteBuilder::class);

        $builder->annul($dte);
        static::assertSame('123', $builder->references()[0]->folio);

        $builder->amend($dte);
        static::assertSame('123', $builder->references()[0]->folio);

        $builder->charge($dte);
        static::assertSame('123', $builder->references()[0]->folio);
    }

    public function test_correction_methods_preserve_leading_test_set_reference(): void
    {
        $date = new DateTimeImmutable('2026-08-15');

        foreach (['annul', 'amend', 'charge'] as $method) {
            $builder = $this->app->make(DebitNoteBuilder::class)
                ->forTestCase('5034081-7');

            $builder->{$method}(DteType::CreditNote, '456', $date);

            $references = $builder->references();

            static::assertCount(2, $references, "Failed for [{$method}].");
            static::assertSame(ReferenceType::TestSet, $references[0]->documentType);
            static::assertSame('CASO 5034081-7', $references[0]->reason);
            static::assertSame(DteType::CreditNote, $references[1]->documentType);
            static::assertSame('456', $references[1]->folio);
        }
    }
}
