<?php

namespace Tests\Unit\Actions\CreateEnvelope\Pipes;

use DOMDocument;
use DOMException;
use InvalidArgumentException;
use Laragear\Dte\Actions\CreateEnvelope\Assembly;
use Laragear\Dte\Actions\CreateEnvelope\CreateEnvelope;
use Laragear\Dte\Actions\CreateEnvelope\Pipes\EmbedDteNodes;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Models\SiiDtePayload;
use Laragear\Dte\Support\XmlDomFactory;
use Laragear\MetaTesting\Pipeline\InteractsWithPipelines;
use Laragear\Rut\Rut;
use Mockery;
use RuntimeException;
use Tests\DatabaseTestCase;

class EmbedDteNodesTest extends DatabaseTestCase
{
    use InteractsWithPipelines;

    protected function makeAssemblyWithDtes(int $count, string $xml = '<DTE><Documento></Documento></DTE>'): Assembly
    {
        $envelope = SiiDteEnvelope::factory()->create();

        for ($i = 0; $i < $count; $i++) {
            $dte = SiiDte::factory()->create([
                'sii_dte_envelope_id' => $envelope->id,
            ]);

            SiiDtePayload::factory()->create([
                'sii_dte_id' => $dte->id,
                'xml' => $xml,
            ]);
        }

        $assembly = new Assembly($envelope);

        $writer = $this->app->make(XmlDomFactory::class)->writer();
        $writer->openMemory();
        $writer->startDocument('1.0', 'ISO-8859-1');
        $writer->startElement('EnvioDTE');
        $writer->startElement('SetDTE');
        $assembly->writer = $writer;

        return $assembly;
    }

    public function test_embeds_nodes_and_closes_envelope(): void
    {
        $assembly = $this->makeAssemblyWithDtes(2);
        $assembly->expectedDocuments = 2;

        $this->pipeline(CreateEnvelope::class)
            ->isolatePipe(EmbedDteNodes::class)
            ->send($assembly)
            ->assertPassable(function (Assembly $result) {
                static::assertEquals(2, $result->embeddedDocuments);
                static::assertNull($result->writer); // closed

                return true;
            });
    }

    public function test_throws_if_xml_payload_is_malformed(): void
    {
        $assembly = $this->makeAssemblyWithDtes(1);

        $document = Mockery::mock(DOMDocument::class);
        $document->expects('loadXml')->andThrow(new DOMException('Invalid State Error'));

        $this->mock(XmlDomFactory::class)->expects('document')->andReturn($document);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The model XML payload is malformed.');

        $this->pipeline(CreateEnvelope::class)
            ->isolatePipe(EmbedDteNodes::class)
            ->send($assembly);
    }

    public function test_throws_if_embedded_count_does_not_match_expected(): void
    {
        $assembly = $this->makeAssemblyWithDtes(1);
        $assembly->expectedDocuments = 2;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Every envelope document must contain a signed XML payload.');

        $this->pipeline(CreateEnvelope::class)
            ->isolatePipe(EmbedDteNodes::class)
            ->send($assembly);
    }

    public function test_throws_if_payload_does_not_contain_dte_root(): void
    {
        $assembly = $this->makeAssemblyWithDtes(1, '<WrongRoot></WrongRoot>');
        $assembly->expectedDocuments = 1;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('An envelope payload does not contain a DTE root element.');

        $this->pipeline(CreateEnvelope::class)
            ->isolatePipe(EmbedDteNodes::class)
            ->send($assembly);
    }

    public function test_filters_payloads_by_receiver_rut_when_target_receiver_is_set(): void
    {
        // Line 67: $query->where('sii_dtes.receiver_num', $rut->num) in payloads()
        $targetReceiver = Rut::parse('33333333-3');

        $envelope = SiiDteEnvelope::factory()->create();

        // DTE matching receiver
        $dteMatch = SiiDte::factory()->create([
            'sii_dte_envelope_id' => $envelope->id,
            'receiver_rut' => $targetReceiver,
        ]);

        SiiDtePayload::factory()->create([
            'sii_dte_id' => $dteMatch->id,
            'xml' => '<DTE><Documento></Documento></DTE>',
        ]);

        // DTE for a different receiver (should be filtered out)
        $dteOther = SiiDte::factory()->create([
            'sii_dte_envelope_id' => $envelope->id,
            'receiver_rut' => Rut::parse('44444444-4'),
        ]);

        SiiDtePayload::factory()->create([
            'sii_dte_id' => $dteOther->id,
            'xml' => '<DTE><Documento></Documento></DTE>',
        ]);

        $assembly = new Assembly($envelope, targetReceiverRut: $targetReceiver);
        $assembly->expectedDocuments = 1; // only 1 should match

        $writer = $this->app->make(XmlDomFactory::class)->writer();
        $writer->openMemory();
        $writer->startDocument('1.0', 'ISO-8859-1');
        $writer->startElement('EnvioDTE');
        $writer->startElement('SetDTE');
        $assembly->writer = $writer;

        $this->pipeline(CreateEnvelope::class)
            ->isolatePipe(EmbedDteNodes::class)
            ->send($assembly)
            ->assertPassable(function (Assembly $result) {
                static::assertEquals(1, $result->embeddedDocuments);
                static::assertNull($result->writer); // closed

                return true;
            });
    }

    public function test_orders_documents_by_metadata_sort_order_then_id(): void
    {
        $envelope = SiiDteEnvelope::factory()->create();

        // Create DTEs with metadata.sort_order: 2, 1, 3 — embed order should be 1, 2, 3
        $dteA = SiiDte::factory()->create([
            'sii_dte_envelope_id' => $envelope->id,
            'metadata' => ['sort_order' => 2],
        ]);
        SiiDtePayload::factory()->create([
            'sii_dte_id' => $dteA->id, 'xml' => '<DTE><Documento><ID>A</ID></Documento></DTE>',
        ]);

        $dteB = SiiDte::factory()->create([
            'sii_dte_envelope_id' => $envelope->id,
            'metadata' => ['sort_order' => 1],
        ]);
        SiiDtePayload::factory()->create([
            'sii_dte_id' => $dteB->id, 'xml' => '<DTE><Documento><ID>B</ID></Documento></DTE>',
        ]);

        $dteC = SiiDte::factory()->create([
            'sii_dte_envelope_id' => $envelope->id,
            'metadata' => ['sort_order' => 3],
        ]);
        SiiDtePayload::factory()->create([
            'sii_dte_id' => $dteC->id, 'xml' => '<DTE><Documento><ID>C</ID></Documento></DTE>',
        ]);

        $assembly = new Assembly($envelope);
        $assembly->expectedDocuments = 3;

        $writer = $this->app->make(XmlDomFactory::class)->writer();
        $writer->openMemory();
        $writer->startDocument('1.0', 'ISO-8859-1');
        $writer->startElement('EnvioDTE');
        $writer->startElement('SetDTE');
        $assembly->writer = $writer;

        $this->pipeline(CreateEnvelope::class)
            ->isolatePipe(EmbedDteNodes::class)
            ->send($assembly)
            ->assertPassable(function (Assembly $result) {
                static::assertEquals(3, $result->embeddedDocuments);

                return true;
            });
    }
}
