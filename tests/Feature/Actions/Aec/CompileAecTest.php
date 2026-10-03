<?php

namespace Tests\Feature\Actions\Aec;

use Carbon\Carbon;
use Laragear\Dte\Actions\Aec\CompileAec;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Dte\Data\CessionData;
use Laragear\Rut\Rut;
use Tests\Feature\Actions\Fixtures\GroundTruthSet;
use Tests\Feature\Actions\GroundTruthTestCase;

class CompileAecTest extends GroundTruthTestCase
{
    /*
     |--------------------------------------------------------------------------
     | Happy paths
     |--------------------------------------------------------------------------
     */

    public function test_compiles_aec_for_case_1(): void
    {
        $compiled = [];
        $dte = GroundTruthSet::compileCase(1, $compiled);

        static::assertSame(119000, $dte->amount_total);

        $cession = new CessionData(
            assigneeRut: Rut::parse('33333333-3'),
            assigneeName: 'CESIONARIO EJEMPLO SPA',
            assigneeAddress: 'Cesion Street 789',
            assigneeEmail: 'cesionario@example.com',
            amount: $dte->amount_total,
            lastDueDate: Carbon::make(GroundTruthSet::FROZEN, 'America/Santiago')->addMonth()->toDateTimeImmutable(),
            terms: 'Términos estándar de cesión',
        );

        $certificate = new DigitalCertificate(
            static::getStub(GroundTruthSet::CERTIFICATE_STUB),
            GroundTruthSet::CERTIFICATE_PASSWORD,
        );

        $receiptXml = static::getStub('ground_truth_receipt_case_1.xml');

        $signedAt = Carbon::make(GroundTruthSet::FROZEN, 'America/Santiago')->toDateTimeImmutable();

        $aecXml = app(CompileAec::class)->build(
            dte: $dte,
            cession: $cession,
            receiptXml: $receiptXml,
            authorizedSigner: GroundTruthSet::ISSUER_RUT,
            authorizedName: GroundTruthSet::ISSUER_NAME,
            cedentEmail: 'dte@example.com',
            certificate: $certificate,
            signedAt: $signedAt,
        );

        $goldenFile = 'ground_truth_aec_case_1.xml';
        $stubPath = static::STUBS.'/'.$goldenFile;

        if (! file_exists($stubPath)) {
            file_put_contents($stubPath, $aecXml);
            static::markTestIncomplete("Golden recorded: {$goldenFile}");
        }

        $this->assertXmlMatchesGroundTruth($goldenFile, $aecXml);
    }
}
