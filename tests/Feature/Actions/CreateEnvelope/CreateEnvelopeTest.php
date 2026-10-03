<?php

namespace Tests\Feature\Actions\CreateEnvelope;

use Laragear\Dte\Actions\CreateEnvelope\CreateEnvelope;
use Laragear\Dte\Enums\EnvelopeStatus;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Rut\Rut;
use Tests\Feature\Actions\Fixtures\GroundTruthSet;
use Tests\Feature\Actions\GroundTruthTestCase;

class CreateEnvelopeTest extends GroundTruthTestCase
{
    /*
     |--------------------------------------------------------------------------
     | Happy paths
     |--------------------------------------------------------------------------
     */

    public function test_compiles_set_envelope(): void
    {
        $compiled = [];

        foreach (range(1, 8) as $case) {
            GroundTruthSet::compileCase($case, $compiled);
        }

        $envelope = SiiDteEnvelope::factory()->create([
            'issuer_rut' => Rut::parse(GroundTruthSet::ISSUER_RUT),
            'sender_rut' => Rut::parse(GroundTruthSet::ISSUER_RUT),
            'type' => 'dte',
            'resolution_date' => GroundTruthSet::RESOLUTION_DATE,
            'resolution_number' => GroundTruthSet::RESOLUTION_NUMBER,
            'status' => EnvelopeStatus::Pending,
        ]);

        foreach ($compiled as $case => $dte) {
            SiiDte::where('id', $dte->id)->update([
                'sii_dte_envelope_id' => $envelope->id,
                'metadata' => ['sort_order' => $case],
            ]);
        }

        app(CreateEnvelope::class)->forEnvelope($envelope);

        static::assertEquals(EnvelopeStatus::Signed, $envelope->status);
        static::assertSame(8, $envelope->dtes()->count());

        $goldenFile = 'ground_truth_envelope_set.xml';
        $stubPath = static::STUBS.'/'.$goldenFile;

        if (! file_exists($stubPath)) {
            file_put_contents($stubPath, $envelope->payload->xml);
            static::markTestIncomplete("Golden recorded: {$goldenFile}");
        }

        $this->assertXmlMatchesGroundTruth($goldenFile, $envelope->payload->xml);

        static::assertDatabaseHas('sii_dte_envelope_payloads', [
            'sii_dte_envelope_id' => $envelope->id,
            'xml' => $envelope->payload->xml,
        ]);
    }
}
