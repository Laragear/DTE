<?php

namespace Tests\Unit\Builders;

use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Laragear\Dte\Builders\CreditNoteBuilder;
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Data\CompanyData;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Data\ReceiverData;
use Laragear\Dte\Data\ReferenceData;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\ReferenceType;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteReference;
use Laragear\Rut\Rut;
use LogicException;
use Tests\DatabaseTestCase;

class CreditNoteBuilderTest extends DatabaseTestCase
{
    public function test_annul_overwrites_references(): void
    {
        $builder = $this->app->make(CreditNoteBuilder::class);
        $date = new DateTimeImmutable('2026-08-15');

        $builder->annul(DteType::Invoice, '123', $date);

        $references = $builder->references();
        static::assertCount(1, $references);
        static::assertSame(DteType::Invoice, $references[0]->documentType);
        static::assertSame('123', $references[0]->folio);
        static::assertSame($date, $references[0]->date);
        static::assertSame('Anula documento', $references[0]->reason);
        static::assertSame(1, $references[0]->referenceCode);
    }

    public function test_amend_overwrites_references(): void
    {
        $builder = $this->app->make(CreditNoteBuilder::class);
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

    public function test_discount_overwrites_references(): void
    {
        $builder = $this->app->make(CreditNoteBuilder::class);
        $date = new DateTimeImmutable('2026-08-15');

        $builder->discount(ReferenceType::PurchaseOrder, 'PO-789', $date);

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

        $builder = $this->app->make(CreditNoteBuilder::class);

        $builder->annul($dte);
        static::assertSame('123', $builder->references()[0]->folio);

        $builder->amend($dte);
        static::assertSame('123', $builder->references()[0]->folio);

        $builder->discount($dte);
        static::assertSame('123', $builder->references()[0]->folio);
    }

    public function test_discount_with_sii_dte_null_issued_on_falls_back_to_now(): void
    {
        $dte = SiiDte::factory()->create([
            'issued_on' => null,
        ]);

        $builder = $this->app->make(CreditNoteBuilder::class);

        $builder->discount($dte);

        static::assertNotEmpty($builder->references());
    }

    public function test_annulment_credit_note_succeeds_when_invoice_has_no_existing_credit_notes(): void
    {
        $invoice = SiiDte::factory()->invoice()->accepted()->create();

        $builder = $this->makeBuilderForDte($invoice);
        $builder->annul($invoice);

        // Should not throw
        $builder->validate();

        static::assertTrue(true);
    }

    public function test_annulment_credit_note_throws_when_invoice_has_accepted_credit_note(): void
    {
        $invoice = SiiDte::factory()->invoice()->accepted()->create();

        $existingCreditNote = SiiDte::factory()->creditNote()->accepted()->create([
            'issuer_rut' => $invoice->issuer_rut,
        ]);

        SiiDteReference::create([
            'sii_dte_id' => $existingCreditNote->getKey(),
            'target_dte_id' => $invoice->getKey(),
            'document_type' => DteType::Invoice,
            'folio' => (string) $invoice->folio,
            'date' => $invoice->issued_on,
            'reason' => 'Anula documento',
            'reference_code' => 1,
        ]);

        $builder = $this->makeBuilderForDte($invoice);
        $builder->annul($invoice);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('already has an accepted or pending annulment credit note');

        $builder->validate();
    }

    public function test_annulment_credit_note_throws_when_invoice_has_pending_annulment_credit_note(): void
    {
        $invoice = SiiDte::factory()->invoice()->create();

        $pendingCreditNote = SiiDte::factory()->creditNote()->create([
            'issuer_rut' => $invoice->issuer_rut,
            'status' => DteStatus::Pending,
        ]);

        SiiDteReference::create([
            'sii_dte_id' => $pendingCreditNote->getKey(),
            'target_dte_id' => $invoice->getKey(),
            'document_type' => DteType::Invoice,
            'folio' => (string) $invoice->folio,
            'date' => $invoice->issued_on,
            'reason' => 'Anula documento',
            'reference_code' => 1,
        ]);

        $builder = $this->makeBuilderForDte($invoice);
        $builder->annul($invoice);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('already has an accepted or pending annulment credit note');

        $builder->validate();
    }

    public function test_annulment_credit_note_succeeds_when_invoice_has_rejected_credit_note(): void
    {
        $invoice = SiiDte::factory()->invoice()->create();

        $rejectedCreditNote = SiiDte::factory()->creditNote()->rejected()->create([
            'issuer_rut' => $invoice->issuer_rut,
        ]);

        SiiDteReference::create([
            'sii_dte_id' => $rejectedCreditNote->getKey(),
            'target_dte_id' => $invoice->getKey(),
            'document_type' => DteType::Invoice,
            'folio' => (string) $invoice->folio,
            'date' => $invoice->issued_on,
            'reason' => 'Anula documento',
            'reference_code' => 1,
        ]);

        $builder = $this->makeBuilderForDte($invoice);
        $builder->annul($invoice);

        // Should not throw
        $builder->validate();

        static::assertTrue(true);
    }

    public function test_annulment_credit_note_succeeds_when_invoice_has_pending_amend_credit_note(): void
    {
        $invoice = SiiDte::factory()->invoice()->create();

        $amendCreditNote = SiiDte::factory()->creditNote()->create([
            'issuer_rut' => $invoice->issuer_rut,
        ]);

        SiiDteReference::create([
            'sii_dte_id' => $amendCreditNote->getKey(),
            'target_dte_id' => $invoice->getKey(),
            'document_type' => DteType::Invoice,
            'folio' => (string) $invoice->folio,
            'date' => $invoice->issued_on,
            'reason' => 'Corrige texto',
            'reference_code' => 2,
        ]);

        $builder = $this->makeBuilderForDte($invoice);
        $builder->annul($invoice);

        // Should not throw — only annulment (code 1) conflicts
        $builder->validate();

        static::assertTrue(true);
    }

    public function test_amend_credit_note_succeeds_when_invoice_has_accepted_credit_note(): void
    {
        $invoice = SiiDte::factory()->invoice()->accepted()->create();

        $existingCreditNote = SiiDte::factory()->creditNote()->accepted()->create([
            'issuer_rut' => $invoice->issuer_rut,
        ]);

        SiiDteReference::create([
            'sii_dte_id' => $existingCreditNote->getKey(),
            'target_dte_id' => $invoice->getKey(),
            'document_type' => DteType::Invoice,
            'folio' => (string) $invoice->folio,
            'date' => $invoice->issued_on,
            'reason' => 'Anula documento',
            'reference_code' => 1,
        ]);

        $builder = $this->makeBuilderForDte($invoice);
        $builder->amend($invoice);

        // Should not throw — amend (code 2) is not restricted
        $builder->validate();

        static::assertTrue(true);
    }

    public function test_annulment_credit_note_skips_check_when_invoice_not_in_db(): void
    {
        $issuerRut = Rut::parse('76.123.456-0');

        ConfigurationManager::setCompany(fn() => CompanyData::make(
            issuer: IssuerData::make($issuerRut, 'Test Company', 'Software', '620200', 'Main St', 'Santiago',
                '2025-01-01', 80),
            senderRut: $issuerRut,
        ));

        $builder = $this->app->make(CreditNoteBuilder::class);
        $builder->receivedBy(ReceiverData::make(
            Rut::parse('76.987.654-5'),
            'Receiver Company',
            'Retail',
            'receiver@example.com',
            'Receiver St 456',
            'Providencia',
            'Santiago',
        ));
        $builder->addItem(Item::make('Credit adjustment', 1000, quantity: 1));

        $date = new DateTimeImmutable('2026-08-15');

        $builder->annul(DteType::Invoice, '99999', $date);

        // Should not throw — invoice not in DB, skip check
        $builder->validate();

        static::assertTrue(true);
    }

    public function test_correction_methods_preserve_leading_test_set_reference(): void
    {
        $date = new DateTimeImmutable('2026-08-15');

        foreach (['annul', 'amend', 'discount'] as $method) {
            $builder = $this->app->make(CreditNoteBuilder::class)
                ->forTestCase('5034081-1');

            $builder->{$method}(DteType::Invoice, '123', $date);

            $references = $builder->references();

            static::assertCount(2, $references, "Failed for [{$method}].");
            static::assertSame(ReferenceType::TestSet, $references[0]->documentType);
            static::assertSame('CASO 5034081-1', $references[0]->reason);
            static::assertSame(DteType::Invoice, $references[1]->documentType);
            static::assertSame('123', $references[1]->folio);
        }
    }

    public function test_correction_methods_replace_non_test_set_references(): void
    {
        $builder = $this->app->make(CreditNoteBuilder::class)
            ->addReference(ReferenceData::make(ReferenceType::PurchaseOrder, 'PO-1',
                new DateTimeImmutable('2026-08-01'), 'Order ref'));

        $builder->annul(DteType::Invoice, '123', new DateTimeImmutable('2026-08-15'));

        $references = $builder->references();

        static::assertCount(1, $references);
        static::assertSame(DteType::Invoice, $references[0]->documentType);
        static::assertSame(1, $references[0]->referenceCode);
    }

    private function configureIssuerForDte(SiiDte $dte): void
    {
        $issuerRut = $dte->issuer_rut;

        ConfigurationManager::setCompany(fn() => CompanyData::make(
            issuer: IssuerData::make($issuerRut, 'Test Company', 'Software', '620200', 'Main St', 'Santiago',
                '2025-01-01', 80),
            senderRut: $issuerRut,
        ));
    }

    /**
     * Create a credit note builder configured with the same issuer as the DTE.
     */
    private function makeBuilderForDte(SiiDte $dte): CreditNoteBuilder
    {
        $this->configureIssuerForDte($dte);

        $builder = $this->app->make(CreditNoteBuilder::class);
        $builder->receivedBy(ReceiverData::make(
            $dte->receiver_rut,
            'Receiver Company',
            'Retail',
            'receiver@example.com',
            'Receiver St 456',
            'Providencia',
            'Santiago',
        ));
        $builder->addItem(Item::make('Credit adjustment', 1000, quantity: 1));

        return $builder;
    }
}
