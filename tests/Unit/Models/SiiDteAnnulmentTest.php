<?php

namespace Tests\Unit\Models;

use Laragear\Dte\Caf\Exceptions\CafNotFoundException;
use Laragear\Dte\Caf\Exceptions\FolioOutOfRangeException;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\SiiCaf;
use Laragear\Dte\Models\SiiDte;
use LogicException;
use Tests\DatabaseTestCase;

class SiiDteAnnulmentTest extends DatabaseTestCase
{
    /**
     * Create a CAF 10-20 at folio 12 with a folio 10 document.
     */
    protected function dte(): array
    {
        $caf = SiiCaf::factory()->create([
            'document_type' => DteType::Invoice,
            'folio_from' => 10,
            'folio_to' => 20,
            'folio_current' => 12,
            'folio_annuled' => [],
            'expires_on' => now()->addDays(10),
        ]);

        $dte = SiiDte::factory()->create([
            'issuer_rut' => $caf->rut,
            'sii_caf_id' => $caf->getKey(),
            'folio' => 10,
        ]);

        return [$caf, $dte];
    }

    public function test_annuls_dte_folio_through_its_caf(): void
    {
        [$caf, $dte] = $this->dte();

        static::assertSame($dte, $dte->annulFolio('Dañado'));
        static::assertTrue($dte->isFolioAnnuled());
        static::assertSame([[10, 10]], $caf->fresh()->folio_annuled);
        static::assertSame(12, $caf->fresh()->folio_current);
    }

    public function test_allowed_for_folio_below_folio_current(): void
    {
        [$caf, $dte] = $this->dte();

        $dte->annulFolio();

        // The document folio (10) is below the CAF pointer (12), yet the annulment succeeds.
        static::assertSame([[10, 10]], $caf->fresh()->folio_annuled);
    }

    public function test_throws_when_dte_has_no_folio(): void
    {
        $caf = SiiCaf::factory()->create(['folio_annuled' => []]);

        $dte = SiiDte::factory()->create([
            'issuer_rut' => $caf->rut,
            'sii_caf_id' => $caf->getKey(),
            'folio' => null,
        ]);

        $this->expectException(LogicException::class);

        $dte->annulFolio();
    }

    public function test_throws_when_dte_has_no_caf(): void
    {
        $dte = SiiDte::factory()->create(['sii_caf_id' => null, 'folio' => 10]);

        $this->expectException(CafNotFoundException::class);

        $dte->annulFolio();
    }

    public function test_throws_when_folio_out_of_caf_range(): void
    {
        $caf = SiiCaf::factory()->create(['folio_to' => 20, 'folio_annuled' => []]);

        $dte = SiiDte::factory()->create([
            'issuer_rut' => $caf->rut,
            'sii_caf_id' => $caf->getKey(),
            'folio' => 25,
        ]);

        $this->expectException(FolioOutOfRangeException::class);
        $this->expectExceptionMessageIs('The folio [25] is out of the CAF range.');

        $dte->annulFolio();
    }

    public function test_is_folio_annuled_helper(): void
    {
        [$caf, $dte] = $this->dte();

        static::assertFalse($dte->isFolioAnnuled());

        $dte->annulFolio();

        static::assertTrue($dte->isFolioAnnuled());
        static::assertTrue($dte->fresh()->isFolioAnnuled());
    }

    public function test_is_folio_annuled_returns_false_without_caf_or_folio(): void
    {
        $dte = SiiDte::factory()->create(['sii_caf_id' => null, 'folio' => null]);

        static::assertFalse($dte->isFolioAnnuled());
    }
}
