<?php

namespace Tests\Unit\Models\Relations;

use Laragear\Dte\Enums\AecStatus;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\EnvelopeStatus;
use Laragear\Dte\Enums\InboundDteStatus;
use Laragear\Dte\Models\SiiAecCession;
use Laragear\Dte\Models\SiiCaf;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Models\SiiDteEnvelopePayload;
use Laragear\Dte\Models\SiiDtePayload;
use Laragear\Dte\Models\SiiInboundDocument;
use Laragear\Dte\Models\SiiInboundDocumentPayload;
use Laragear\Dte\Models\SiiInterchangeLog;
use Tests\DatabaseTestCase;

class RelationshipsTest extends DatabaseTestCase
{
    /*
     |--------------------------------------------------------------------------
     | Happy paths
     |--------------------------------------------------------------------------
     */

    public function test_caf_has_many_dtes(): void
    {
        $caf = SiiCaf::factory()->has(SiiDte::factory(), 'dtes')->create();
        $dte = $caf->dtes->first();

        static::assertTrue($dte->caf->is($caf));
        static::assertTrue($caf->dtes->contains($dte));
        static::assertInstanceOf(DteStatus::class, $dte->status);
    }

    public function test_dte_has_one_payload_and_many_aec_cessions(): void
    {
        $dte = SiiDte::factory()
            ->has(SiiDtePayload::factory(), 'payload')
            ->has(SiiAecCession::factory(), 'aecCessions')
            ->create();
        $payload = $dte->payload;
        $cession = $dte->aecCessions->first();

        static::assertTrue($payload->dte->is($dte));
        static::assertTrue($dte->payload->is($payload));
        static::assertTrue($cession->dte->is($dte));
        static::assertTrue($dte->aecCessions->contains($cession));
        static::assertInstanceOf(AecStatus::class, $cession->status);
        static::assertSame([], $payload->data['items']);
        static::assertIsInt($payload->data['resolution_number']);
        static::assertMatchesRegularExpression('/^\d{4}-(?:0[1-9]|1[0-2])$/', $payload->data['resolution_date']);
    }

    public function test_envelope_has_payload_dtes_and_interchange_logs(): void
    {
        $envelope = SiiDteEnvelope::factory()
            ->has(SiiDteEnvelopePayload::factory(), 'payload')
            ->has(SiiDte::factory(), 'dtes')
            ->has(SiiInterchangeLog::factory(), 'interchangeLogs')
            ->create();

        $payload = $envelope->payload;
        $dte = $envelope->dtes->first();
        $log = $envelope->interchangeLogs->first();

        static::assertTrue($payload->envelope->is($envelope));
        static::assertTrue($dte->envelope->is($envelope));
        static::assertTrue($log->envelope->is($envelope));
        static::assertTrue($envelope->dtes->contains($dte));
        static::assertTrue($envelope->interchangeLogs->contains($log));
        static::assertInstanceOf(EnvelopeStatus::class, $envelope->status);
    }

    public function test_interchange_log_has_inbound_documents_with_payloads(): void
    {
        $log = SiiInterchangeLog::factory()
            ->has(
                SiiInboundDocument::factory()->has(SiiInboundDocumentPayload::factory(), 'payload'),
                'inboundDocuments'
            )
            ->create();
        $document = $log->inboundDocuments->first();
        $payload = $document->payload;

        static::assertTrue($document->interchangeLog->is($log));
        static::assertTrue($log->inboundDocuments->contains($document));
        static::assertTrue($payload->inboundDocument->is($document));
        static::assertTrue($document->payload->is($payload));
        static::assertInstanceOf(InboundDteStatus::class, $document->status);
    }
}
