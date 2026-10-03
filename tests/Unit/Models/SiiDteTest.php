<?php

namespace Tests\Unit\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Laragear\Dte\Builders\AecCessionBuilder;
use Laragear\Dte\Builders\CreditNoteBuilder;
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Data\CompanyData;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Data\ReceiverData;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Pdf\PdfBuilder;
use Laragear\Rut\Rut;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\DatabaseTestCase;

class SiiDteTest extends DatabaseTestCase
{
    public function test_relationships_and_methods(): void
    {
        $dte = new SiiDte;

        static::assertInstanceOf(BelongsTo::class, $dte->caf());
        static::assertInstanceOf(BelongsTo::class, $dte->envelope());
        static::assertInstanceOf(HasOne::class, $dte->payload());
        static::assertInstanceOf(HasMany::class, $dte->aecCessions());
        static::assertInstanceOf(HasMany::class, $dte->references());
        static::assertInstanceOf(HasMany::class, $dte->referencedBy());

        // Mock app container for builders
        $this->mock(AecCessionBuilder::class)->expects('forDte')->andReturnSelf();

        $this->mock(PdfBuilder::class)->expects('forDte')->andReturnSelf();

        static::assertInstanceOf(AecCessionBuilder::class, $dte->cede());
        static::assertInstanceOf(PdfBuilder::class, $dte->pdf());
    }

    public function test_accepted_with_repairs_helpers(): void
    {
        $dte = new SiiDte;

        $dte->status = DteStatus::Pending;
        $dte->repairs = null;
        static::assertFalse($dte->isAcceptedWithRepairs());
        static::assertTrue($dte->isNotAcceptedWithRepairs());

        $dte->status = DteStatus::Accepted;
        $dte->repairs = null;
        static::assertFalse($dte->isAcceptedWithRepairs());
        static::assertTrue($dte->isNotAcceptedWithRepairs());

        $dte->status = DteStatus::Accepted;
        $dte->repairs = [];
        static::assertFalse($dte->isAcceptedWithRepairs());
        static::assertTrue($dte->isNotAcceptedWithRepairs());

        $dte->status = DteStatus::Accepted;
        $dte->repairs = ['rechazados' => 1];
        static::assertTrue($dte->isAcceptedWithRepairs());
        static::assertFalse($dte->isNotAcceptedWithRepairs());
    }

    public function test_read_only(): void
    {
        $dte = new SiiDte;
        $dte->status = DteStatus::Draft;

        static::assertFalse($dte->isReadOnly());
        static::assertTrue($dte->isNotReadOnly());
    }

    public static function providesModifiableStatuses(): array
    {
        return [
            DteStatus::Pending->value => [DteStatus::Pending],
            DteStatus::Building->value => [DteStatus::Building],
            DteStatus::RequiresCaf->value => [DteStatus::RequiresCaf],
            DteStatus::Signing->value => [DteStatus::Signing],
            DteStatus::Outbox->value => [DteStatus::Outbox],
            DteStatus::Packed->value => [DteStatus::Packed],
            DteStatus::Sent->value => [DteStatus::Sent],
            DteStatus::Accepted->value => [DteStatus::Accepted],
            DteStatus::Rejected->value => [DteStatus::Rejected],
            DteStatus::Failed->value => [DteStatus::Failed],
            DteStatus::Annulled->value => [DteStatus::Annulled],
        ];
    }

    #[DataProvider('providesModifiableStatuses')]
    public function test_read_only_status(DteStatus $status): void
    {
        $dte = new SiiDte;
        $dte->status = $status;

        static::assertTrue($dte->isReadOnly());
        static::assertFalse($dte->isNotReadOnly());
    }

    /*
     |--------------------------------------------------------------------------
     | Retryability
     |--------------------------------------------------------------------------
     */

    public static function providesRetryableWithSameFolioStatuses(): array
    {
        return [
            DteStatus::Draft->value => [DteStatus::Draft],
            DteStatus::Pending->value => [DteStatus::Pending],
            DteStatus::Building->value => [DteStatus::Building],
            DteStatus::RequiresCaf->value => [DteStatus::RequiresCaf],
            DteStatus::Signing->value => [DteStatus::Signing],
            DteStatus::Outbox->value => [DteStatus::Outbox],
            DteStatus::Packed->value => [DteStatus::Packed],
            DteStatus::Failed->value => [DteStatus::Failed],
            DteStatus::Sent->value => [DteStatus::Sent],
        ];
    }

    #[DataProvider('providesRetryableWithSameFolioStatuses')]
    public function test_is_retryable_with_same_folio(DteStatus $status): void
    {
        $dte = new SiiDte;
        $dte->status = $status;

        static::assertTrue($dte->isRetryableWithSameFolio());
        static::assertTrue($dte->isRetryable());
        static::assertFalse($dte->isReplicable());
    }

    public function test_rejected_is_replicable_not_retryable_with_same_folio(): void
    {
        $dte = new SiiDte;
        $dte->status = DteStatus::Rejected;

        static::assertFalse($dte->isRetryableWithSameFolio());
        static::assertTrue($dte->isReplicable());
        static::assertTrue($dte->isRetryable());
    }

    public function test_accepted_and_annulled_are_not_retryable(): void
    {
        $dte = new SiiDte;
        $dte->status = DteStatus::Accepted;

        static::assertFalse($dte->isRetryable());
        static::assertFalse($dte->isRetryableWithSameFolio());
        static::assertFalse($dte->isReplicable());

        $dte->status = DteStatus::Annulled;

        static::assertFalse($dte->isRetryable());
        static::assertFalse($dte->isRetryableWithSameFolio());
        static::assertFalse($dte->isReplicable());
    }

    public function test_remaining_pack_retries_returns_null_for_uncompiled(): void
    {
        $dte = new SiiDte;
        $dte->status = DteStatus::Pending;

        static::assertNull($dte->remainingPackRetries());

        $dte->status = DteStatus::Draft;

        static::assertNull($dte->remainingPackRetries());
    }

    public static function providesPackRetryableStatuses(): array
    {
        return [
            DteStatus::Outbox->value => [DteStatus::Outbox],
            DteStatus::Packed->value => [DteStatus::Packed],
        ];
    }

    #[DataProvider('providesPackRetryableStatuses')]
    public function test_remaining_pack_retries_for_compiled(DteStatus $status): void
    {
        $this->config('dte.envelopes.max_retries', 3);

        $dte = new SiiDte;
        $dte->status = $status;
        $dte->pack_retries = 0;

        static::assertSame(3, $dte->remainingPackRetries());

        $dte->pack_retries = 2;

        static::assertSame(1, $dte->remainingPackRetries());

        $dte->pack_retries = 3;

        static::assertSame(0, $dte->remainingPackRetries());

        $dte->pack_retries = 5;

        static::assertSame(0, $dte->remainingPackRetries());
    }

    public function test_is_not_retryable(): void
    {
        $dte = new SiiDte;
        $dte->status = DteStatus::Accepted;

        static::assertTrue($dte->isNotRetryable());

        $dte->status = DteStatus::Pending;

        static::assertFalse($dte->isNotRetryable());
    }

    public function test_is_not_retryable_with_same_folio(): void
    {
        $dte = new SiiDte;
        $dte->status = DteStatus::Accepted;

        static::assertTrue($dte->isNotRetryableWithSameFolio());

        $dte->status = DteStatus::Pending;

        static::assertFalse($dte->isNotRetryableWithSameFolio());
    }

    public function test_is_not_replicable(): void
    {
        $dte = new SiiDte;
        $dte->status = DteStatus::Accepted;

        static::assertTrue($dte->isNotReplicable());

        $dte->status = DteStatus::Rejected;

        static::assertFalse($dte->isNotReplicable());
    }

    /*
     |--------------------------------------------------------------------------
     | Items Attribute
     |--------------------------------------------------------------------------
     */

    public function test_items_returns_collection_from_payload(): void
    {
        $dte = $this->createInvoiceWithPayload();
        $items = $dte->items;

        static::assertInstanceOf(Collection::class, $items);
        static::assertCount(1, $items);
        static::assertInstanceOf(Item::class, $items->first());
        static::assertSame('Product A', $items->first()->name);
        static::assertSame(1000.0, $items->first()->unitPrice);
    }

    public function test_items_returns_empty_collection_when_no_payload(): void
    {
        $dte = SiiDte::factory()->create();
        $items = $dte->items;

        static::assertInstanceOf(Collection::class, $items);
        static::assertCount(0, $items);
    }

    /*
     |--------------------------------------------------------------------------
     | Annul
     |--------------------------------------------------------------------------
     */

    public function test_annul_creates_credit_note_for_invoice(): void
    {
        $invoice = $this->createInvoiceWithPayload();

        $creditNote = $invoice->annul();

        static::assertInstanceOf(SiiDte::class, $creditNote);
        static::assertSame(DteType::CreditNote, $creditNote->document_type);

        $references = $creditNote->references;
        static::assertCount(1, $references);
        static::assertSame(1, $references->first()->reference_code);
        static::assertSame('Anula documento', $references->first()->reason);
    }

    public function test_annul_with_custom_reason(): void
    {
        $invoice = $this->createInvoiceWithPayload();

        $creditNote = $invoice->annul('Anulación por error');

        $references = $creditNote->references;
        static::assertSame('Anulación por error', $references->first()->reason);
    }

    public function test_annul_creates_debit_note_for_credit_note(): void
    {
        $creditNote = $this->createCreditNoteWithPayload();

        $debitNote = $creditNote->annul();

        static::assertInstanceOf(SiiDte::class, $debitNote);
        static::assertSame(DteType::DebitNote, $debitNote->document_type);

        $references = $debitNote->references;
        static::assertCount(1, $references);
        static::assertSame(1, $references->first()->reference_code);
    }

    public function test_annul_copies_items_from_original(): void
    {
        $invoice = $this->createInvoiceWithPayload();

        $creditNote = $invoice->annul();

        static::assertCount(1, $creditNote->items);
        static::assertSame('Product A', $creditNote->items->first()->name);
    }

    public function test_annul_throws_when_payload_missing(): void
    {
        $dte = SiiDte::factory()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('no payload data to infer from');

        $dte->annul();
    }

    /*
     |--------------------------------------------------------------------------
     | Amend
     |--------------------------------------------------------------------------
     */

    public function test_amend_creates_credit_note_for_invoice(): void
    {
        $invoice = $this->createInvoiceWithPayload();

        $creditNote = $invoice->amend();

        static::assertInstanceOf(SiiDte::class, $creditNote);
        static::assertSame(DteType::CreditNote, $creditNote->document_type);

        $references = $creditNote->references;
        static::assertCount(1, $references);
        static::assertSame(2, $references->first()->reference_code);
        static::assertSame('Corrige texto', $references->first()->reason);
    }

    public function test_amend_with_callback_modifies_builder(): void
    {
        $invoice = $this->createInvoiceWithPayload();

        $creditNote = $invoice->amend(function (CreditNoteBuilder $builder) {
            $builder->receivedBy(ReceiverData::make(
                Rut::parse('76.999.999-K'),
                'New Receiver Name',
                'New Activity',
                'new@example.com',
                'New Address',
                'New Commune',
                'New City',
            ));
        });

        // The receiver RUT should be changed to the new one.
        static::assertSame(76999999, $creditNote->receiver_rut->num);
    }

    public function test_amend_throws_for_receipt(): void
    {
        $receipt = $this->createReceiptWithPayload();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('cannot be amended');

        $receipt->amend();
    }

    public function test_amend_throws_for_dispatch_guide(): void
    {
        $guide = $this->createDispatchGuideWithPayload();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('cannot be amended');

        $guide->amend();
    }

    public function test_amend_throws_for_debit_note(): void
    {
        $debitNote = $this->createDebitNoteWithPayload();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('cannot be amended');

        $debitNote->amend();
    }

    public function test_amend_throws_for_credit_note(): void
    {
        $creditNote = $this->createCreditNoteWithPayload();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('cannot be amended');

        $creditNote->amend();
    }

    /*
     |--------------------------------------------------------------------------
     | Discount
     |--------------------------------------------------------------------------
     */

    public function test_discount_creates_credit_note(): void
    {
        $invoice = $this->createInvoiceWithPayload();

        $items = [Item::make('Discount', 500, quantity: 1)];
        $creditNote = $invoice->discount($items);

        static::assertInstanceOf(SiiDte::class, $creditNote);
        static::assertSame(DteType::CreditNote, $creditNote->document_type);

        $references = $creditNote->references;
        static::assertCount(1, $references);
        static::assertSame(3, $references->first()->reference_code);
        static::assertSame('Corrige montos', $references->first()->reason);
    }

    public function test_discount_uses_provided_items(): void
    {
        $invoice = $this->createInvoiceWithPayload();

        $items = [Item::make('Discount Item', 500, quantity: 2)];
        $creditNote = $invoice->discount($items);

        static::assertCount(1, $creditNote->items);
        static::assertSame('Discount Item', $creditNote->items->first()->name);
    }

    public function test_discount_throws_for_credit_note(): void
    {
        $creditNote = $this->createCreditNoteWithPayload();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('Cannot apply a discount to a Credit Note');

        $creditNote->discount([Item::make('Test', 100)]);
    }

    public function test_discount_throws_for_dispatch_guide(): void
    {
        $guide = $this->createDispatchGuideWithPayload();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('Dispatch Guides cannot be modified');

        $guide->discount([Item::make('Test', 100)]);
    }

    /*
     |--------------------------------------------------------------------------
     | Surcharge
     |--------------------------------------------------------------------------
     */

    public function test_surcharge_creates_debit_note(): void
    {
        $invoice = $this->createInvoiceWithPayload();

        $items = [Item::make('Surcharge', 500, quantity: 1)];
        $debitNote = $invoice->surcharge($items);

        static::assertInstanceOf(SiiDte::class, $debitNote);
        static::assertSame(DteType::DebitNote, $debitNote->document_type);

        $references = $debitNote->references;
        static::assertCount(1, $references);
        static::assertSame(3, $references->first()->reference_code);
        static::assertSame('Corrige montos', $references->first()->reason);
    }

    public function test_surcharge_uses_provided_items(): void
    {
        $invoice = $this->createInvoiceWithPayload();

        $items = [Item::make('Surcharge Item', 500, quantity: 3)];
        $debitNote = $invoice->surcharge($items);

        static::assertCount(1, $debitNote->items);
        static::assertSame('Surcharge Item', $debitNote->items->first()->name);
    }

    public function test_surcharge_throws_for_debit_note(): void
    {
        $debitNote = $this->createDebitNoteWithPayload();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('Cannot apply a surcharge to a Debit Note');

        $debitNote->surcharge([Item::make('Test', 100)]);
    }

    public function test_surcharge_throws_for_dispatch_guide(): void
    {
        $guide = $this->createDispatchGuideWithPayload();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('Dispatch Guides cannot be modified');

        $guide->surcharge([Item::make('Test', 100)]);
    }

    /*
     |--------------------------------------------------------------------------
     | Issuer Inference
     |--------------------------------------------------------------------------
     */

    public function test_annul_infers_issuer_from_payload(): void
    {
        $invoice = $this->createInvoiceWithPayload();

        $creditNote = $invoice->annul();

        // The credit note issuer should match the original invoice issuer.
        static::assertSame($invoice->issuer_rut->formatRaw(), $creditNote->issuer_rut->formatRaw());
    }

    public function test_annul_infers_receiver_from_payload(): void
    {
        $invoice = $this->createInvoiceWithPayload();

        $creditNote = $invoice->annul();

        // The credit note receiver should match the original invoice receiver.
        static::assertSame($invoice->receiver_rut->formatRaw(), $creditNote->receiver_rut->formatRaw());
    }

    /*
     |--------------------------------------------------------------------------
     | Helpers
     |--------------------------------------------------------------------------
     */

    private function configureIssuer(): void
    {
        $issuerRut = Rut::parse('76.123.456-0');

        ConfigurationManager::setCompany(fn() => CompanyData::make(
            issuer: IssuerData::make($issuerRut, 'Test Company', 'Software', '620200', 'Main St', 'Santiago',
                '2025-01-01', 80),
            senderRut: $issuerRut,
        ));
    }

    private function createInvoiceWithPayload(): SiiDte
    {
        $this->configureIssuer();

        $builder = app(CreditNoteBuilder::class);

        // We need to use InvoiceBuilder to create an invoice, but we can
        // configure a CreditNoteBuilder to test the note creation.
        // For a proper invoice, let's use the factory with a payload.

        $issuerRut = Rut::parse('76.123.456-0');
        $receiverRut = Rut::parse('76.987.654-5');

        $dte = SiiDte::factory()->invoice()->accepted()->create([
            'issuer_rut' => $issuerRut,
            'receiver_rut' => $receiverRut,
        ]);

        $dte->payload()->create([
            'data' => [
                'document_type' => DteType::Invoice->value,
                'issued_on' => '2026-08-15',
                'issuer' => [
                    'rut' => $issuerRut->formatRaw(),
                    'name' => 'Test Company',
                    'activity' => 'Software',
                    'activity_code' => ['620200'],
                    'address' => 'Main St',
                    'commune' => 'Santiago',
                    'city' => 'Santiago',
                    'resolution_date' => '2025-01-01',
                    'resolution_number' => 80,
                ],
                'receiver' => [
                    'rut' => $receiverRut->formatRaw(),
                    'name' => 'Receiver Company',
                    'activity' => 'Retail',
                    'email' => 'receiver@example.com',
                    'address' => 'Receiver St 456',
                    'commune' => 'Providencia',
                    'city' => 'Santiago',
                ],
                'items' => [
                    [
                        'name' => 'Product A',
                        'unit_price' => 1000,
                        'quantity' => 1,
                        'description' => null,
                        'unit' => null,
                        'code' => null,
                        'code_type' => null,
                        'discount_percentage' => 0,
                        'exempt' => false,
                        'taxes' => [],
                        'discount_amount' => null,
                    ],
                ],
                'references' => [],
                'global_modifiers' => [],
                'taxes' => [],
                'totals' => ['net' => 1000, 'exempt' => 0, 'tax' => 190, 'total' => 1190],
            ],
        ]);

        return $dte;
    }

    private function createCreditNoteWithPayload(): SiiDte
    {
        $this->configureIssuer();

        $issuerRut = Rut::parse('76.123.456-0');
        $receiverRut = Rut::parse('76.987.654-5');

        $dte = SiiDte::factory()->creditNote()->accepted()->create([
            'issuer_rut' => $issuerRut,
            'receiver_rut' => $receiverRut,
        ]);

        $dte->payload()->create([
            'data' => [
                'document_type' => DteType::CreditNote->value,
                'issued_on' => '2026-08-15',
                'issuer' => [
                    'rut' => $issuerRut->formatRaw(),
                    'name' => 'Test Company',
                    'activity' => 'Software',
                    'activity_code' => ['620200'],
                    'address' => 'Main St',
                    'commune' => 'Santiago',
                    'city' => 'Santiago',
                    'resolution_date' => '2025-01-01',
                    'resolution_number' => 80,
                ],
                'receiver' => [
                    'rut' => $receiverRut->formatRaw(),
                    'name' => 'Receiver Company',
                    'activity' => 'Retail',
                    'email' => 'receiver@example.com',
                    'address' => 'Receiver St 456',
                    'commune' => 'Providencia',
                    'city' => 'Santiago',
                ],
                'items' => [
                    [
                        'name' => 'Credit Adjustment',
                        'unit_price' => 500,
                        'quantity' => 1,
                        'description' => null,
                        'unit' => null,
                        'code' => null,
                        'code_type' => null,
                        'discount_percentage' => 0,
                        'exempt' => false,
                        'taxes' => [],
                        'discount_amount' => null,
                    ],
                ],
                'references' => [],
                'global_modifiers' => [],
                'taxes' => [],
                'totals' => ['net' => 500, 'exempt' => 0, 'tax' => 95, 'total' => 595],
            ],
        ]);

        return $dte;
    }

    private function createDebitNoteWithPayload(): SiiDte
    {
        $this->configureIssuer();

        $issuerRut = Rut::parse('76.123.456-0');
        $receiverRut = Rut::parse('76.987.654-5');

        $dte = SiiDte::factory()->debitNote()->accepted()->create([
            'issuer_rut' => $issuerRut,
            'receiver_rut' => $receiverRut,
        ]);

        $dte->payload()->create([
            'data' => [
                'document_type' => DteType::DebitNote->value,
                'issued_on' => '2026-08-15',
                'issuer' => [
                    'rut' => $issuerRut->formatRaw(),
                    'name' => 'Test Company',
                    'activity' => 'Software',
                    'activity_code' => ['620200'],
                    'address' => 'Main St',
                    'commune' => 'Santiago',
                    'city' => 'Santiago',
                    'resolution_date' => '2025-01-01',
                    'resolution_number' => 80,
                ],
                'receiver' => [
                    'rut' => $receiverRut->formatRaw(),
                    'name' => 'Receiver Company',
                    'activity' => 'Retail',
                    'email' => 'receiver@example.com',
                    'address' => 'Receiver St 456',
                    'commune' => 'Providencia',
                    'city' => 'Santiago',
                ],
                'items' => [
                    [
                        'name' => 'Debit Adjustment',
                        'unit_price' => 500,
                        'quantity' => 1,
                        'description' => null,
                        'unit' => null,
                        'code' => null,
                        'code_type' => null,
                        'discount_percentage' => 0,
                        'exempt' => false,
                        'taxes' => [],
                        'discount_amount' => null,
                    ],
                ],
                'references' => [],
                'global_modifiers' => [],
                'taxes' => [],
                'totals' => ['net' => 500, 'exempt' => 0, 'tax' => 95, 'total' => 595],
            ],
        ]);

        return $dte;
    }

    private function createReceiptWithPayload(): SiiDte
    {
        $this->configureIssuer();

        $issuerRut = Rut::parse('76.123.456-0');

        $dte = SiiDte::factory()->receipt()->accepted()->create([
            'issuer_rut' => $issuerRut,
        ]);

        $dte->payload()->create([
            'data' => [
                'document_type' => DteType::Receipt->value,
                'issued_on' => '2026-08-15',
                'issuer' => [
                    'rut' => $issuerRut->formatRaw(),
                    'name' => 'Test Company',
                    'activity' => 'Software',
                    'activity_code' => ['620200'],
                    'address' => 'Main St',
                    'commune' => 'Santiago',
                    'city' => 'Santiago',
                    'resolution_date' => '2025-01-01',
                    'resolution_number' => 80,
                ],
                'receiver' => null,
                'items' => [
                    [
                        'name' => 'Receipt Item',
                        'unit_price' => 5000,
                        'quantity' => 1,
                        'description' => null,
                        'unit' => null,
                        'code' => null,
                        'code_type' => null,
                        'discount_percentage' => 0,
                        'exempt' => false,
                        'taxes' => [],
                        'discount_amount' => null,
                    ],
                ],
                'references' => [],
                'global_modifiers' => [],
                'taxes' => [],
                'totals' => ['net' => 4202, 'exempt' => 0, 'tax' => 798, 'total' => 5000],
            ],
        ]);

        return $dte;
    }

    private function createDispatchGuideWithPayload(): SiiDte
    {
        $this->configureIssuer();

        $issuerRut = Rut::parse('76.123.456-0');
        $receiverRut = Rut::parse('76.987.654-5');

        $dte = SiiDte::factory()->dispatchGuide()->accepted()->create([
            'issuer_rut' => $issuerRut,
            'receiver_rut' => $receiverRut,
        ]);

        $dte->payload()->create([
            'data' => [
                'document_type' => DteType::DispatchGuide->value,
                'issued_on' => '2026-08-15',
                'issuer' => [
                    'rut' => $issuerRut->formatRaw(),
                    'name' => 'Test Company',
                    'activity' => 'Software',
                    'activity_code' => ['620200'],
                    'address' => 'Main St',
                    'commune' => 'Santiago',
                    'city' => 'Santiago',
                    'resolution_date' => '2025-01-01',
                    'resolution_number' => 80,
                ],
                'receiver' => [
                    'rut' => $receiverRut->formatRaw(),
                    'name' => 'Receiver Company',
                    'activity' => 'Retail',
                    'email' => 'receiver@example.com',
                    'address' => 'Receiver St 456',
                    'commune' => 'Providencia',
                    'city' => 'Santiago',
                ],
                'items' => [
                    [
                        'name' => 'Guide Item',
                        'unit_price' => 1000,
                        'quantity' => 5,
                        'description' => null,
                        'unit' => null,
                        'code' => null,
                        'code_type' => null,
                        'discount_percentage' => 0,
                        'exempt' => false,
                        'taxes' => [],
                        'discount_amount' => null,
                    ],
                ],
                'references' => [],
                'global_modifiers' => [],
                'taxes' => [],
                'totals' => ['net' => 5000, 'exempt' => 0, 'tax' => 950, 'total' => 5950],
            ],
        ]);

        return $dte;
    }
}
