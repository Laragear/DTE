<?php

namespace Tests\Unit\Actions\Cuadratura\Pipes;

use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;
use Laragear\Dte\Actions\Cuadratura\CuadraturaContext;
use Laragear\Dte\Actions\Cuadratura\Pipes\DowngradeOrphanedDocuments;
use Laragear\Dte\Actions\Cuadratura\Sync;
use Laragear\Dte\Actions\RcvParsing\ParsingContext;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\InboundDteStatus;
use Laragear\Dte\Enums\RcvType;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiInboundDocument;
use Laragear\MetaTesting\Pipeline\InteractsWithPipelines;
use Laragear\Rut\Rut;
use Tests\DatabaseTestCase;

class DowngradeOrphanedDocumentsTest extends DatabaseTestCase
{
    use InteractsWithPipelines;

    public function test_downgrades_orphaned_sent_outbound_records_safely(): void
    {
        $companyRut = Rut::parse('76111222-3');

        $staleDocument = SiiDte::factory()->create([
            'issuer_rut' => '76111222-3',
            'status' => DteStatus::Sent,
            'created_at' => Carbon::now()->subDays(3), // Older than 48 hours
        ]);

        $parsingContext = new ParsingContext('fake', RcvType::Sales, $companyRut);
        $parsingContext->records = LazyCollection::empty();

        $context = new CuadraturaContext($parsingContext);

        $this
            ->pipeline(Sync::class)
            ->isolatePipe(DowngradeOrphanedDocuments::class)
            ->send($context);

        static::assertSame(1, $context->metrics['orphans']);

        $staleDocument->refresh();

        static::assertSame(DteStatus::Rejected, $staleDocument->status);
    }

    public function test_downgrades_orphaned_unmapped_inbound_forged_documents_safely(): void
    {
        $companyRut = Rut::parse('76111222-3');

        $staleDocument = SiiInboundDocument::factory()->create([
            'receiver_rut' => '76111222-3',
            'status' => InboundDteStatus::Received,
            'created_at' => Carbon::now()->subDays(3),
        ]);

        $parsingContext = new ParsingContext('fake', RcvType::Purchases, $companyRut);
        $parsingContext->records = LazyCollection::empty();

        $context = new CuadraturaContext($parsingContext);

        $this
            ->pipeline(Sync::class)
            ->isolatePipe(DowngradeOrphanedDocuments::class)
            ->send($context);

        static::assertSame(1, $context->metrics['orphans']);

        $staleDocument->refresh();
        static::assertSame(InboundDteStatus::Forged, $staleDocument->status);
    }
}
