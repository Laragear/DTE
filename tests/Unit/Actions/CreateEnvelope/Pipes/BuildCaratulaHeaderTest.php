<?php

namespace Tests\Unit\Actions\CreateEnvelope\Pipes;

use Illuminate\Filesystem\Filesystem;
use Laragear\Dte\Actions\CreateEnvelope\Assembly;
use Laragear\Dte\Actions\CreateEnvelope\CreateEnvelope;
use Laragear\Dte\Actions\CreateEnvelope\Pipes\BuildCaratulaHeader;
use Laragear\Dte\Actions\CreateEnvelope\Pipes\ValidateEnvelopeDocuments;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\MetaTesting\Pipeline\InteractsWithPipelines;
use Laragear\Rut\Rut;
use LogicException;
use RuntimeException;
use Tests\DatabaseTestCase;
use UnexpectedValueException;

class BuildCaratulaHeaderTest extends DatabaseTestCase
{
    use InteractsWithPipelines;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config('dte.envelopes.max.documents', 10);
    }

    public function test_build_caratula_header_streams_elements_successfully(): void
    {
        $envelope = SiiDteEnvelope::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'sender_rut' => Rut::parse('22222222-2'),
            'resolution_date' => '2023-01-01',
            'resolution_number' => 1234,
        ]);

        $dte = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'document_type' => 33,
            'status' => DteStatus::Outbox,
        ]);

        $envelope->dtes()->save($dte);

        $assembly = new Assembly($envelope);
        $assembly->path = tempnam(sys_get_temp_dir(), 'dte_');

        try {
            $this
                ->pipeline(CreateEnvelope::class)
                ->isolatePipe(BuildCaratulaHeader::class)
                ->send($assembly)
                ->assertPassable(function (Assembly $result) {
                    static::assertNotNull($result->writer);

                    $xml = $this->app->make(Filesystem::class)->get($result->path);

                    static::assertStringContainsString(
                        '<EnvioDTE',
                        $xml,
                    );
                    static::assertStringContainsString('<SetDTE ID="SetDoc">', $xml);
                    static::assertStringContainsString('<Caratula version="1.0">', $xml);
                    static::assertStringContainsString('<RutEmisor>11111111-1</RutEmisor>', $xml);
                    static::assertStringContainsString('<RutEnvia>22222222-2</RutEnvia>', $xml);
                    static::assertStringContainsString('<FchResol>2023-01-01</FchResol>', $xml);
                    static::assertStringContainsString('<NroResol>1234</NroResol>', $xml);
                    static::assertStringContainsString(
                        '<SubTotDTE><TpoDTE>33</TpoDTE><NroDTE>1</NroDTE></SubTotDTE>',
                        $xml,
                    );

                    return true;
                });
        } finally {
            unlink($assembly->path);
        }
    }

    public function test_throws_if_no_documents(): void
    {
        $envelope = SiiDteEnvelope::factory()->create();
        $assembly = new Assembly($envelope);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The DTE envelope must contain at least one signed document.');

        $this
            ->pipeline(CreateEnvelope::class)
            ->isolatePipe(ValidateEnvelopeDocuments::class)
            ->send($assembly);
    }

    public function test_throws_if_too_many_documents(): void
    {
        $this->config('dte.envelopes.max.documents', 1);

        $envelope = SiiDteEnvelope::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
        ]);

        $dte1 = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'document_type' => 33,
            'status' => DteStatus::Outbox,
        ]);
        $dte2 = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'document_type' => 33,
            'status' => DteStatus::Outbox,
        ]);

        $envelope->dtes()->saveMany([$dte1, $dte2]);

        $assembly = new Assembly($envelope);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The DTE envelope exceeds the configured document limit.');

        $this
            ->pipeline(CreateEnvelope::class)
            ->isolatePipe(ValidateEnvelopeDocuments::class)
            ->send($assembly);
    }

    public function test_throws_if_invalid_documents_in_envelope(): void
    {
        $envelope = SiiDteEnvelope::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
        ]);

        // Wrong status
        $dte = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'document_type' => 33,
            'status' => DteStatus::Building,
        ]);

        $envelope->dtes()->save($dte);

        $assembly = new Assembly($envelope);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The DTE envelope documents must share its issuer, compiled state, and receipt type.');

        $this
            ->pipeline(CreateEnvelope::class)
            ->isolatePipe(ValidateEnvelopeDocuments::class)
            ->send($assembly);
    }

    public function test_throws_on_invalid_resolution_date(): void
    {
        $envelope = SiiDteEnvelope::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'resolution_date' => '2023-01-01',
        ]);
        $envelope->resolution_date = null;

        $dte = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'document_type' => 33,
            'status' => DteStatus::Outbox,
        ]);

        $envelope->dtes()->save($dte);

        $assembly = new Assembly($envelope);
        $assembly->path = tempnam(sys_get_temp_dir(), 'dte_');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIs('The issuer resolution date must use YYYY-MM-DD format.');

        $this
            ->pipeline(CreateEnvelope::class)
            ->isolatePipe(BuildCaratulaHeader::class)
            ->send($assembly);
    }

    public function test_throws_on_invalid_resolution_number(): void
    {
        $envelope = SiiDteEnvelope::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'resolution_date' => '2023-01-01',
            'resolution_number' => 1234,
        ]);
        $envelope->resolution_number = -1;

        $dte = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'document_type' => 33,
            'status' => DteStatus::Outbox,
        ]);

        $envelope->dtes()->save($dte);

        $assembly = new Assembly($envelope);
        $assembly->path = tempnam(sys_get_temp_dir(), 'dte_');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIs('The issuer resolution number must be a non-negative integer.');

        $this
            ->pipeline(CreateEnvelope::class)
            ->isolatePipe(BuildCaratulaHeader::class)
            ->send($assembly);
    }

    public function test_throws_if_bad_max_documents_config(): void
    {
        $this->config('dte.envelopes.max.documents', -1);

        $envelope = SiiDteEnvelope::factory()->create();
        $dte = SiiDte::factory()->create();
        $envelope->dtes()->save($dte);

        $assembly = new Assembly($envelope);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIs('The envelope document limit must be a positive integer.');

        $this
            ->pipeline(CreateEnvelope::class)
            ->isolatePipe(ValidateEnvelopeDocuments::class)
            ->send($assembly);
    }

    public function test_throws_if_path_cannot_be_opened(): void
    {
        $envelope = SiiDteEnvelope::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
        ]);

        $dte = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'document_type' => 33,
            'status' => DteStatus::Outbox,
        ]);

        $envelope->dtes()->save($dte);

        $assembly = new Assembly($envelope);
        $assembly->path = '/non-existent-dir/envelope.xml';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Unable to open the temporary envelope XML file.');

        @$this
            ->pipeline(CreateEnvelope::class)
            ->isolatePipe(BuildCaratulaHeader::class)
            ->send($assembly);
    }

    public function test_filters_documents_by_receiver_rut_when_target_receiver_is_set(): void
    {
        // Line 59: $query->where('receiver_num', $rut->num) in validateDocuments
        $targetReceiver = Rut::parse('33333333-3');

        $envelope = SiiDteEnvelope::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'sender_rut' => Rut::parse('22222222-2'),
            'resolution_date' => '2023-01-01',
            'resolution_number' => 1234,
        ]);

        // DTE for the target receiver (should match)
        $dteMatch = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'receiver_rut' => $targetReceiver,
            'document_type' => 33,
            'status' => DteStatus::Outbox,
        ]);

        // DTE for a different receiver (should be filtered out)
        $dteOther = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'receiver_rut' => Rut::parse('44444444-4'),
            'document_type' => 33,
            'status' => DteStatus::Outbox,
        ]);

        $envelope->dtes()->saveMany([$dteMatch, $dteOther]);

        $assembly = new Assembly($envelope, targetReceiverRut: $targetReceiver);
        $assembly->path = tempnam(sys_get_temp_dir(), 'dte_');

        try {
            $this
                ->pipeline(CreateEnvelope::class)
                ->isolatePipe(ValidateEnvelopeDocuments::class)
                ->send($assembly)
                ->assertPassable(function (Assembly $result) {
                    // Only 1 document should be counted (the matching receiver)
                    static::assertSame(1, $result->expectedDocuments);

                    return true;
                });
        } finally {
            unlink($assembly->path);
        }
    }

    public function test_filters_invalid_documents_by_receiver_rut(): void
    {
        // Line 86: $query->where('receiver_num', $rut->num) in hasInvalidDocuments
        $targetReceiver = Rut::parse('33333333-3');

        $envelope = SiiDteEnvelope::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
        ]);

        // DTE matching receiver but with wrong status → invalid
        $dte = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'receiver_rut' => $targetReceiver,
            'document_type' => 33,
            'status' => DteStatus::Building, // wrong status
        ]);

        // DTE for a different receiver, also wrong status → should be filtered out by receiver_rut
        $dteOther = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'receiver_rut' => Rut::parse('44444444-4'),
            'document_type' => 33,
            'status' => DteStatus::Building,
        ]);

        $envelope->dtes()->saveMany([$dte, $dteOther]);

        $assembly = new Assembly($envelope, targetReceiverRut: $targetReceiver);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The DTE envelope documents must share its issuer, compiled state, and receipt type.');

        $this
            ->pipeline(CreateEnvelope::class)
            ->isolatePipe(ValidateEnvelopeDocuments::class)
            ->send($assembly);
    }

    public function test_uses_receipts_config_key_for_boleta_envelope(): void
    {
        $this->config('dte.envelopes.max.receipts', 1);

        $envelope = SiiDteEnvelope::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'type' => 'boleta',
            'resolution_date' => '2023-01-01',
            'resolution_number' => 1234,
        ]);

        $dte1 = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'document_type' => 33,
            'status' => DteStatus::Outbox,
        ]);
        $dte2 = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'document_type' => 33,
            'status' => DteStatus::Outbox,
        ]);

        $envelope->dtes()->saveMany([$dte1, $dte2]);

        $assembly = new Assembly($envelope);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The DTE envelope exceeds the configured document limit.');

        $this
            ->pipeline(CreateEnvelope::class)
            ->isolatePipe(ValidateEnvelopeDocuments::class)
            ->send($assembly);
    }

    public function test_allows_mixed_document_types_with_multiple_subtotals(): void
    {
        $envelope = SiiDteEnvelope::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'resolution_date' => '2023-01-01',
            'resolution_number' => 1234,
        ]);

        $dteInvoice = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'document_type' => 33,
            'status' => DteStatus::Outbox,
        ]);
        $dteCredit = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'document_type' => 61,
            'status' => DteStatus::Outbox,
        ]);
        $dteDebit = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'document_type' => 56,
            'status' => DteStatus::Outbox,
        ]);

        $envelope->dtes()->saveMany([$dteInvoice, $dteCredit, $dteDebit]);

        $assembly = new Assembly($envelope);
        $assembly->path = tempnam(sys_get_temp_dir(), 'dte_');

        try {
            $this
                ->pipeline(CreateEnvelope::class)
                ->isolatePipe(BuildCaratulaHeader::class)
                ->send($assembly)
                ->assertPassable(function (Assembly $result) {
                    $xml = $this->app->make(Filesystem::class)->get($result->path);

                    static::assertStringContainsString('<SubTotDTE><TpoDTE>33</TpoDTE><NroDTE>1</NroDTE></SubTotDTE>',
                        $xml);
                    static::assertStringContainsString('<SubTotDTE><TpoDTE>56</TpoDTE><NroDTE>1</NroDTE></SubTotDTE>',
                        $xml);
                    static::assertStringContainsString('<SubTotDTE><TpoDTE>61</TpoDTE><NroDTE>1</NroDTE></SubTotDTE>',
                        $xml);

                    return true;
                });
        } finally {
            unlink($assembly->path);
        }
    }

    public function test_throws_when_normal_envelope_contains_receipt(): void
    {
        $envelope = SiiDteEnvelope::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
        ]);

        $dte = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'document_type' => 39,
            'status' => DteStatus::Outbox,
        ]);

        $envelope->dtes()->save($dte);

        $assembly = new Assembly($envelope);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The DTE envelope documents must share its issuer, compiled state, and receipt type.');

        $this
            ->pipeline(CreateEnvelope::class)
            ->isolatePipe(ValidateEnvelopeDocuments::class)
            ->send($assembly);
    }

    public function test_throws_when_boleta_envelope_contains_normal_dte(): void
    {
        $envelope = SiiDteEnvelope::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'type' => 'boleta',
        ]);

        $dte = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'document_type' => 33,
            'status' => DteStatus::Outbox,
        ]);

        $envelope->dtes()->save($dte);

        $assembly = new Assembly($envelope);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The DTE envelope documents must share its issuer, compiled state, and receipt type.');

        $this
            ->pipeline(CreateEnvelope::class)
            ->isolatePipe(ValidateEnvelopeDocuments::class)
            ->send($assembly);
    }

    public function test_filters_dtes_by_target_receiver_rut(): void
    {
        $envelope = SiiDteEnvelope::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'sender_rut' => Rut::parse('22222222-2'),
            'resolution_date' => '2023-01-01',
            'resolution_number' => 1234,
        ]);

        $dte1 = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'receiver_num' => Rut::parse('33333333-3')->num,
            'document_type' => 33,
            'status' => DteStatus::Outbox,
        ]);

        $dte2 = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'receiver_num' => Rut::parse('44444444-4')->num,
            'document_type' => 33,
            'status' => DteStatus::Outbox,
        ]);

        $envelope->dtes()->saveMany([$dte1, $dte2]);

        $assembly = new Assembly($envelope);
        $assembly->targetReceiverRut = Rut::parse('33333333-3');
        $assembly->path = tempnam(sys_get_temp_dir(), 'dte_');

        try {
            $this
                ->pipeline(CreateEnvelope::class)
                ->isolatePipe(BuildCaratulaHeader::class)
                ->send($assembly)
                ->assertPassable(function (Assembly $result) {
                    $xml = $this->app->make(Filesystem::class)->get($result->path);

                    // Only 1 DTE should be in the SubTotDTE (the targeted receiver)
                    static::assertStringContainsString('<NroDTE>1</NroDTE>', $xml);

                    return true;
                });
        } finally {
            unlink($assembly->path);
        }
    }
}
