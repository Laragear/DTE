<?php

namespace Tests\Unit\Actions\Cuadratura\Pipes;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\LazyCollection;
use Laragear\Dte\Actions\Cuadratura\CuadraturaContext;
use Laragear\Dte\Actions\Cuadratura\Pipes\DetectOrphanedDocuments;
use Laragear\Dte\Actions\Cuadratura\Sync;
use Laragear\Dte\Actions\RcvParsing\ParsingContext;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\InboundDteStatus;
use Laragear\Dte\Enums\RcvType;
use Laragear\Dte\Events\DteOrphaned;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiInboundDocument;
use Laragear\MetaTesting\Pipeline\InteractsWithPipelines;
use Laragear\Rut\Rut;
use Tests\DatabaseTestCase;

class DetectOrphanedDocumentsTest extends DatabaseTestCase
{
    use InteractsWithPipelines;

    protected function setUp(): void
    {
        // Time frozen inside a period whose detection window has elapsed:
        // March 2023 closes on 2023-04-10 end of day.
        Carbon::setTestNow(Carbon::parse('2023-04-15'));

        parent::setUp();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function syncContext(RcvType $type): CuadraturaContext
    {
        $parsingContext = new ParsingContext('fake', $type, Rut::parse('76111222-3'), period: '2023-03');
        $parsingContext->records = LazyCollection::empty();

        $context = new CuadraturaContext($parsingContext, '2023-03');

        $this
            ->pipeline(Sync::class)
            ->isolatePipe(DetectOrphanedDocuments::class)
            ->send($context);

        return $context;
    }

    public function test_reports_orphaned_sent_outbound_without_writing_statuses(): void
    {
        $document = SiiDte::factory()->create([
            'issuer_rut' => '76111222-3',
            'status' => DteStatus::Sent,
            'issued_on' => Carbon::parse('2023-03-20'),
        ]);

        $context = $this->syncContext(RcvType::Sales);

        static::assertSame(1, $context->metrics['orphans']);

        // Read-only detection: the status survives, the event carries the case.
        $document->refresh();
        static::assertSame(DteStatus::Sent, $document->status);
    }

    public function test_dispatches_orphaned_event_for_the_document(): void
    {
        $document = SiiDte::factory()->create([
            'issuer_rut' => '76111222-3',
            'status' => DteStatus::Sent,
            'issued_on' => Carbon::parse('2023-03-20'),
        ]);

        Event::fake([DteOrphaned::class]);

        $this->syncContext(RcvType::Sales);

        Event::assertDispatched(DteOrphaned::class, fn (DteOrphaned $event) => $event->model->is($document));
    }

    public function test_skips_detection_while_sii_incorporation_window_is_open(): void
    {
        // Window for 2023-03 closes on 2023-04-10; we are still inside it.
        Carbon::setTestNow(Carbon::parse('2023-04-05'));

        SiiDte::factory()->create([
            'issuer_rut' => '76111222-3',
            'status' => DteStatus::Sent,
            'issued_on' => Carbon::parse('2023-03-20'),
        ]);

        $context = $this->syncContext(RcvType::Sales);

        static::assertSame(0, $context->metrics['orphans']);
    }

    public function test_ignores_documents_outside_the_synced_period(): void
    {
        SiiDte::factory()->create([
            'issuer_rut' => '76111222-3',
            'status' => DteStatus::Sent,
            'issued_on' => Carbon::parse('2023-02-20'),
        ]);

        SiiDte::factory()->create([
            'issuer_rut' => '76111222-3',
            'status' => DteStatus::Sent,
            'issued_on' => Carbon::parse('2023-04-01'),
        ]);

        $context = $this->syncContext(RcvType::Sales);

        static::assertSame(0, $context->metrics['orphans']);
    }

    public function test_ignores_outbound_documents_in_other_statuses(): void
    {
        foreach ([DteStatus::Draft, DteStatus::Outbox, DteStatus::Accepted, DteStatus::Rejected] as $status) {
            SiiDte::factory()->create([
                'issuer_rut' => '76111222-3',
                'status' => $status,
                'issued_on' => Carbon::parse('2023-03-20'),
            ]);
        }

        $context = $this->syncContext(RcvType::Sales);

        static::assertSame(0, $context->metrics['orphans']);
    }

    public function test_ignores_documents_of_other_companies(): void
    {
        SiiDte::factory()->create([
            'issuer_rut' => '99999999-9',
            'status' => DteStatus::Sent,
            'issued_on' => Carbon::parse('2023-03-20'),
        ]);

        $context = $this->syncContext(RcvType::Sales);

        static::assertSame(0, $context->metrics['orphans']);
    }

    public function test_reports_orphaned_inbound_documents(): void
    {
        SiiInboundDocument::factory()->create([
            'receiver_rut' => '76111222-3',
            'status' => InboundDteStatus::TechnicalAccepted,
            'issued_on' => Carbon::parse('2023-03-20'),
        ]);

        $context = $this->syncContext(RcvType::Purchases);

        static::assertSame(1, $context->metrics['orphans']);
    }

    public function test_ignores_phantom_and_forged_inbound_documents(): void
    {
        SiiInboundDocument::factory()->create([
            'receiver_rut' => '76111222-3',
            'status' => InboundDteStatus::PhantomPending,
            'issued_on' => Carbon::parse('2023-03-20'),
        ]);

        SiiInboundDocument::factory()->create([
            'receiver_rut' => '76111222-3',
            'status' => InboundDteStatus::Forged,
            'issued_on' => Carbon::parse('2023-03-20'),
        ]);

        $context = $this->syncContext(RcvType::Purchases);

        static::assertSame(0, $context->metrics['orphans']);
    }

    public function test_bounds_inbound_documents_by_reception_date(): void
    {
        // Issued in February but received in March: inside the synced period.
        SiiInboundDocument::factory()->create([
            'receiver_rut' => '76111222-3',
            'status' => InboundDteStatus::TechnicalAccepted,
            'issued_on' => Carbon::parse('2023-02-27'),
            'received_at' => Carbon::parse('2023-03-03'),
        ]);

        $context = $this->syncContext(RcvType::Purchases);

        static::assertSame(1, $context->metrics['orphans']);
    }

    public function test_skips_detection_without_a_period(): void
    {
        SiiDte::factory()->create([
            'issuer_rut' => '76111222-3',
            'status' => DteStatus::Sent,
            'issued_on' => Carbon::parse('2023-03-20'),
        ]);

        $parsingContext = new ParsingContext('fake', RcvType::Sales, Rut::parse('76111222-3'));
        $parsingContext->records = LazyCollection::empty();

        $context = new CuadraturaContext($parsingContext);

        $this
            ->pipeline(Sync::class)
            ->isolatePipe(DetectOrphanedDocuments::class)
            ->send($context);

        static::assertSame(0, $context->metrics['orphans']);
    }
}
