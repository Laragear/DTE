<?php

namespace Tests\Feature\Actions\CompileDte;

use Carbon\Carbon;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Facades\SiiDebitNote;
use Tests\Feature\Actions\Fixtures\GroundTruthSet;
use Tests\Feature\Actions\GroundTruthTestCase;

/**
 * A zero-value correction line carries a stored quantity of 0, which the SII
 * rejects because SiiDte:Dec12_6Type declares minInclusive 0.000001. The
 * emission must floor the quantity at 1 while keeping the line amount at 0.
 */
class ZeroQuantityTest extends GroundTruthTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Zero quantity lines
    |--------------------------------------------------------------------------
    */

    public function test_zero_quantity_line_passes_xsd_validation(): void
    {
        $dte = SiiDebitNote::issuedBy(GroundTruthSet::issuerData())
            ->receivedBy(GroundTruthSet::receiverData())
            ->issuedOn(Carbon::make(GroundTruthSet::FROZEN, 'America/Santiago')->toDateTimeImmutable())
            ->addItem(Item::make('CORRIGE GIRO DEL RECEPTOR', 0.0, 0.0))
            ->amend(61, '1', Carbon::make(GroundTruthSet::FROZEN, 'America/Santiago')->toDateTimeImmutable(), 'Corrige giro del receptor')
            ->buildSync();

        static::assertEquals(DteStatus::Outbox, $dte->status);

        // The pipeline already ran XsdValidation with dte.validation.xsd_enabled
        // on, so reaching the outbox proves the schema accepted the document.
        $this->assertXmlMatchesGroundTruth('zero_quantity_dte.xml', $dte->payload->xml);
    }

    public function test_zero_quantity_line_is_emitted_as_one_with_a_zero_amount(): void
    {
        $dte = SiiDebitNote::issuedBy(GroundTruthSet::issuerData())
            ->receivedBy(GroundTruthSet::receiverData())
            ->issuedOn(Carbon::make(GroundTruthSet::FROZEN, 'America/Santiago')->toDateTimeImmutable())
            ->addItem(Item::make('CORRIGE GIRO DEL RECEPTOR', 0.0, 0.0))
            ->amend(61, '1', Carbon::make(GroundTruthSet::FROZEN, 'America/Santiago')->toDateTimeImmutable(), 'Corrige giro del receptor')
            ->buildSync();

        $xml = $dte->payload->xml;

        // The zero quantity must never reach the SII as a literal zero.
        static::assertStringContainsString('<QtyItem>1</QtyItem>', $xml);
        static::assertStringNotContainsString('<QtyItem>0</QtyItem>', $xml);

        // The line amount stays truthful at zero, and the zero price is omitted.
        static::assertStringContainsString('<MontoItem>0</MontoItem>', $xml);
        static::assertStringNotContainsString('<PrcItem>', $xml);

        static::assertTrue(true);
    }
}
