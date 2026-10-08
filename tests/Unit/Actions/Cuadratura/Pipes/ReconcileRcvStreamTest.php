<?php

namespace Tests\Unit\Actions\Cuadratura\Pipes;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\LazyCollection;
use Laragear\Dte\Actions\Cuadratura\CuadraturaContext;
use Laragear\Dte\Actions\Cuadratura\Pipes\ReconcileRcvStream;
use Laragear\Dte\Actions\Cuadratura\Sync;
use Laragear\Dte\Actions\RcvParsing\ParsingContext;
use Laragear\Dte\Data\RcvRecord;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\InboundDteStatus;
use Laragear\Dte\Enums\RcvType;
use Laragear\Dte\Events\DteAltered;
use Laragear\Dte\Events\DteUnregistered;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiInboundDocument;
use Laragear\MetaTesting\Pipeline\InteractsWithPipelines;
use Laragear\Rut\Rut;
use Tests\DatabaseTestCase;

class ReconcileRcvStreamTest extends DatabaseTestCase
{
    use InteractsWithPipelines;

    public function test_matches_valid_purchases_and_updates_states_correctly(): void
    {
        $companyRut = Rut::parse('76111222-3');

        $document = SiiInboundDocument::factory()->create([
            'issuer_rut' => '76039449-1',
            'receiver_rut' => '76111222-3',
            'document_type' => DteType::Invoice,
            'folio' => 5550,
            'amount_total' => 119000,
            'claim_status' => null,
            'status' => InboundDteStatus::Received,
        ]);

        $record = new RcvRecord(
            issuer: Rut::parse('76039449-1'),
            receiver: $companyRut,
            documentType: DteType::Invoice,
            folio: 5550,
            amountTotal: 119000,
            characterization: 'Del Giro',
            issuedOn: Carbon::now(),
            acknowledgedAt: Carbon::now(),
        );

        $parsingContext = new ParsingContext('fake', RcvType::Purchases, $companyRut);
        $parsingContext->records = LazyCollection::make([$record]);

        $context = new CuadraturaContext($parsingContext);

        $this
            ->pipeline(Sync::class)
            ->isolatePipe(ReconcileRcvStream::class)
            ->send($context);

        static::assertSame(1, $context->metrics['matched']);

        $document->refresh();

        // A RCV match is not a commercial acceptance decision: claims untouched.
        static::assertNull($document->claim_status);
        static::assertNull($document->claimed_at);
    }

    public function test_matches_valid_sales_and_updates_outbounds_correctly(): void
    {
        $companyRut = Rut::parse('76111222-3');

        $document = SiiDte::factory()->create([
            'issuer_rut' => '76111222-3',
            'receiver_rut' => '76039449-1',
            'document_type' => DteType::Invoice,
            'folio' => 9090,
            'amount_total' => 5000,
            'status' => DteStatus::Sent,
            'acknowledged_at' => null,
        ]);

        $record = new RcvRecord(
            issuer: $companyRut,
            receiver: Rut::parse('76039449-1'),
            documentType: DteType::Invoice,
            folio: 9090,
            amountTotal: 5000,
            characterization: 'Del Giro',
            issuedOn: Carbon::now(),
            acknowledgedAt: Carbon::now(),
        );

        $parsingContext = new ParsingContext('fake', RcvType::Sales, $companyRut);
        $parsingContext->records = LazyCollection::make([$record]);

        $context = new CuadraturaContext($parsingContext);

        $this
            ->pipeline(Sync::class)
            ->isolatePipe(ReconcileRcvStream::class)
            ->send($context);

        static::assertSame(1, $context->metrics['matched']);

        $document->refresh();

        static::assertSame(DteStatus::Accepted, $document->status);
        static::assertNull($document->acknowledged_at);
    }

    public function test_generates_phantom_pending_when_valid_purchases_are_missing_locally(): void
    {
        $companyRut = Rut::parse('76111222-3');

        $receivedOn = Carbon::parse('2023-03-07');

        $record = new RcvRecord(
            issuer: Rut::parse('76039449-1'),
            receiver: $companyRut,
            documentType: DteType::Invoice,
            folio: 1000,
            amountTotal: 50000,
            characterization: 'Del Giro',
            issuedOn: $receivedOn,
            receivedOn: $receivedOn,
            acknowledgedAt: Carbon::now(),
        );

        $parsingContext = new ParsingContext('fake', RcvType::Purchases, $companyRut);
        $parsingContext->records = LazyCollection::make([$record]);

        $context = new CuadraturaContext($parsingContext);

        $this
            ->pipeline(Sync::class)
            ->isolatePipe(ReconcileRcvStream::class)
            ->send($context);

        static::assertSame(1, $context->metrics['phantoms']);

        $this->assertDatabaseHas(SiiInboundDocument::class, [
            'folio' => 1000,
            'status' => InboundDteStatus::PhantomPending->value,
            'amount_total' => 50000,
            'received_at' => $receivedOn->toDateTimeString(),
        ]);
    }

    public function test_triggers_unregistered_event_for_missing_sales_natively(): void
    {
        Event::fake([DteUnregistered::class]);

        $companyRut = Rut::parse('76111222-3');

        $record = new RcvRecord(
            issuer: $companyRut,
            receiver: Rut::parse('76039449-1'),
            documentType: DteType::Invoice,
            folio: 9999,
            amountTotal: 100,
            characterization: 'Del Giro',
            issuedOn: Carbon::now(),
            acknowledgedAt: null,
        );

        $parsingContext = new ParsingContext('fake', RcvType::Sales, $companyRut);
        $parsingContext->records = LazyCollection::make([$record]);

        $context = new CuadraturaContext($parsingContext);

        $this
            ->pipeline(Sync::class)
            ->isolatePipe(ReconcileRcvStream::class)
            ->send($context);

        static::assertSame(1, $context->metrics['phantoms']);

        Event::assertDispatched(DteUnregistered::class, function (DteUnregistered $event) {
            return $event->record->folio === 9999;
        });
    }

    public function test_dispatches_altered_discrepancy_event_on_amount_mismatch(): void
    {
        Event::fake([DteAltered::class]);

        $companyRut = Rut::parse('76111222-3');

        SiiInboundDocument::factory()->create([
            'issuer_rut' => '76039449-1',
            'receiver_rut' => '76111222-3',
            'document_type' => DteType::Invoice,
            'folio' => 5550,
            'amount_total' => 500, // Different from RcvRecord!
        ]);

        $record = new RcvRecord(
            issuer: Rut::parse('76039449-1'),
            receiver: $companyRut,
            documentType: DteType::Invoice,
            folio: 5550,
            amountTotal: 119000,
            characterization: 'Del Giro',
            issuedOn: Carbon::now(),
            acknowledgedAt: null,
        );

        $parsingContext = new ParsingContext('fake', RcvType::Purchases, $companyRut);
        $parsingContext->records = LazyCollection::make([$record]);

        $context = new CuadraturaContext($parsingContext);

        $this
            ->pipeline(Sync::class)
            ->isolatePipe(ReconcileRcvStream::class)
            ->send($context);

        static::assertSame(1, $context->metrics['discrepancies']);

        Event::assertDispatched(DteAltered::class, function (DteAltered $event) {
            return $event->record->amountTotal === 119000 && $event->model->amount_total === 500;
        });
    }

    public function test_dispatches_altered_discrepancy_event_on_outbound_amount_mismatch(): void
    {
        $event = Event::fake([DteAltered::class]);

        $companyRut = Rut::parse('76111222-3');

        SiiDte::factory()->create([
            'issuer_rut' => '76111222-3',
            'receiver_rut' => '76039449-1',
            'document_type' => DteType::Invoice,
            'folio' => 9090,
            'amount_total' => 500, // Different from RcvRecord!
        ]);

        $record = new RcvRecord(
            issuer: $companyRut,
            receiver: Rut::parse('76039449-1'),
            documentType: DteType::Invoice,
            folio: 9090,
            amountTotal: 119000,
            characterization: 'Del Giro',
            issuedOn: Carbon::now(),
            acknowledgedAt: null,
        );

        $parsingContext = new ParsingContext('fake', RcvType::Sales, $companyRut);
        $parsingContext->records = LazyCollection::make([$record]);

        $context = new CuadraturaContext($parsingContext);

        $this
            ->pipeline(Sync::class)
            ->isolatePipe(ReconcileRcvStream::class)
            ->send($context);

        static::assertSame(1, $context->metrics['discrepancies']);

        $event->assertDispatched(DteAltered::class, function (DteAltered $event) {
            return $event->record->amountTotal === 119000 && $event->model->amount_total === 500;
        });
    }

    public function test_phantom_creation_is_idempotent_across_resyncs(): void
    {
        $companyRut = Rut::parse('76111222-3');

        $record = new RcvRecord(
            issuer: Rut::parse('76039449-1'),
            receiver: $companyRut,
            documentType: DteType::Invoice,
            folio: 1000,
            amountTotal: 50000,
            characterization: 'Del Giro',
            issuedOn: Carbon::now(),
        );

        $parsingContext = new ParsingContext('fake', RcvType::Purchases, $companyRut);
        $parsingContext->records = LazyCollection::make([$record]);

        $context = new CuadraturaContext($parsingContext);

        $this
            ->pipeline(Sync::class)
            ->isolatePipe(ReconcileRcvStream::class)
            ->send($context);

        $this
            ->pipeline(Sync::class)
            ->isolatePipe(ReconcileRcvStream::class)
            ->send($context);

        static::assertSame(1, SiiInboundDocument::query()->where('folio', 1000)->count());
    }

    public function test_skips_phantom_creation_for_non_claimable_document_types(): void
    {
        $companyRut = Rut::parse('76111222-3');

        $record = new RcvRecord(
            issuer: Rut::parse('76039449-1'),
            receiver: $companyRut,
            documentType: DteType::Receipt,
            folio: 1000,
            amountTotal: 50000,
            characterization: 'Del Giro',
            issuedOn: Carbon::now(),
        );

        $parsingContext = new ParsingContext('fake', RcvType::Purchases, $companyRut);
        $parsingContext->records = LazyCollection::make([$record]);

        $context = new CuadraturaContext($parsingContext);

        $this
            ->pipeline(Sync::class)
            ->isolatePipe(ReconcileRcvStream::class)
            ->send($context);

        static::assertSame(1, $context->metrics['skipped']);
        static::assertSame(0, $context->metrics['phantoms']);
        $this->assertDatabaseEmpty(SiiInboundDocument::class);
    }

    public function test_dispatches_altered_when_unsent_document_appears_in_rcv(): void
    {
        $event = Event::fake([DteAltered::class]);

        $companyRut = Rut::parse('76111222-3');

        $document = SiiDte::factory()->create([
            'issuer_rut' => '76111222-3',
            'receiver_rut' => '76039449-1',
            'document_type' => DteType::Invoice,
            'folio' => 9090,
            'amount_total' => 5000,
            'status' => DteStatus::Draft,
        ]);

        $record = new RcvRecord(
            issuer: $companyRut,
            receiver: Rut::parse('76039449-1'),
            documentType: DteType::Invoice,
            folio: 9090,
            amountTotal: 5000,
            characterization: 'Del Giro',
            issuedOn: Carbon::now(),
        );

        $parsingContext = new ParsingContext('fake', RcvType::Sales, $companyRut);
        $parsingContext->records = LazyCollection::make([$record]);

        $context = new CuadraturaContext($parsingContext);

        $this
            ->pipeline(Sync::class)
            ->isolatePipe(ReconcileRcvStream::class)
            ->send($context);

        static::assertSame(0, $context->metrics['matched']);
        static::assertSame(1, $context->metrics['discrepancies']);

        $document->refresh();
        static::assertSame(DteStatus::Draft, $document->status);

        $event->assertDispatched(DteAltered::class);
    }

    public function test_never_matches_documents_of_other_issuers(): void
    {
        $companyRut = Rut::parse('76111222-3');

        // Same receiver, type and folio, but issued by another company.
        SiiDte::factory()->create([
            'issuer_rut' => '99999999-9',
            'receiver_rut' => '76039449-1',
            'document_type' => DteType::Invoice,
            'folio' => 9090,
            'amount_total' => 5000,
            'status' => DteStatus::Sent,
        ]);

        $record = new RcvRecord(
            issuer: $companyRut,
            receiver: Rut::parse('76039449-1'),
            documentType: DteType::Invoice,
            folio: 9090,
            amountTotal: 5000,
            characterization: 'Del Giro',
            issuedOn: Carbon::now(),
        );

        $parsingContext = new ParsingContext('fake', RcvType::Sales, $companyRut);
        $parsingContext->records = LazyCollection::make([$record]);

        $context = new CuadraturaContext($parsingContext);

        $this
            ->pipeline(Sync::class)
            ->isolatePipe(ReconcileRcvStream::class)
            ->send($context);

        static::assertSame(0, $context->metrics['matched']);
        static::assertSame(1, $context->metrics['phantoms']);
    }

    public function test_derives_period_from_oldest_record_issued_on(): void
    {
        $companyRut = Rut::parse('76111222-3');

        $records = [
            new RcvRecord(
                issuer: Rut::parse('76039449-1'),
                receiver: $companyRut,
                documentType: DteType::Invoice,
                folio: 1000,
                amountTotal: 50000,
                characterization: 'Del Giro',
                issuedOn: Carbon::parse('2023-03-24'),
            ),
            new RcvRecord(
                issuer: Rut::parse('76039449-1'),
                receiver: $companyRut,
                documentType: DteType::Invoice,
                folio: 1001,
                amountTotal: 50000,
                characterization: 'Del Giro',
                issuedOn: Carbon::parse('2023-02-28'),
            ),
            // Records without an issue date are derivation-neutral.
            new RcvRecord(
                issuer: Rut::parse('76039449-1'),
                receiver: $companyRut,
                documentType: DteType::Invoice,
                folio: 1002,
                amountTotal: 50000,
                characterization: 'Del Giro',
            ),
        ];

        $parsingContext = new ParsingContext('fake', RcvType::Purchases, $companyRut);
        $parsingContext->records = LazyCollection::make($records);

        $context = new CuadraturaContext($parsingContext);

        $this
            ->pipeline(Sync::class)
            ->isolatePipe(ReconcileRcvStream::class)
            ->send($context);

        static::assertSame('2023-02', $context->period);
    }

    public function test_phantom_falls_back_to_reception_date_when_issue_date_missing(): void
    {
        $companyRut = Rut::parse('76111222-3');

        $receivedOn = Carbon::parse('2023-03-07');

        $record = new RcvRecord(
            issuer: Rut::parse('76039449-1'),
            receiver: $companyRut,
            documentType: DteType::Invoice,
            folio: 1000,
            amountTotal: 50000,
            characterization: 'Del Giro',
            receivedOn: $receivedOn,
        );

        $parsingContext = new ParsingContext('fake', RcvType::Purchases, $companyRut);
        $parsingContext->records = LazyCollection::make([$record]);

        $context = new CuadraturaContext($parsingContext);

        $this
            ->pipeline(Sync::class)
            ->isolatePipe(ReconcileRcvStream::class)
            ->send($context);

        $this->assertDatabaseHas(SiiInboundDocument::class, [
            'folio' => 1000,
            'status' => InboundDteStatus::PhantomPending->value,
            'issued_on' => $receivedOn->toDateTimeString(),
        ]);
    }

    public function test_explicit_period_wins_over_derivation(): void
    {
        $companyRut = Rut::parse('76111222-3');

        $record = new RcvRecord(
            issuer: Rut::parse('76039449-1'),
            receiver: $companyRut,
            documentType: DteType::Invoice,
            folio: 1000,
            amountTotal: 50000,
            characterization: 'Del Giro',
            issuedOn: Carbon::parse('2023-03-24'),
        );

        $parsingContext = new ParsingContext('fake', RcvType::Purchases, $companyRut, period: '2023-03');
        $parsingContext->records = LazyCollection::make([$record]);

        $context = new CuadraturaContext($parsingContext, '2023-03');

        $this
            ->pipeline(Sync::class)
            ->isolatePipe(ReconcileRcvStream::class)
            ->send($context);

        static::assertSame('2023-03', $context->period);
    }
}
