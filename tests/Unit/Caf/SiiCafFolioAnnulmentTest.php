<?php

namespace Tests\Unit\Caf;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use Laragear\Dte\Caf\CafManager;
use Laragear\Dte\Caf\Exceptions\FolioAlreadyAllocatedException;
use Laragear\Dte\Caf\Exceptions\FolioAlreadyAnnuledException;
use Laragear\Dte\Caf\Exceptions\FolioOutOfRangeException;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Events\CafFoliosAnnuled;
use Laragear\Dte\Models\SiiCaf;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\DatabaseTestCase;
use Throwable;

class SiiCafFolioAnnulmentTest extends DatabaseTestCase
{
    /**
     * Create a CAF ranging 10-20 with the folio pointer at the given value.
     */
    protected function caf(int $current = 12, array $annuled = []): SiiCaf
    {
        return SiiCaf::factory()->create([
            'document_type' => DteType::Invoice,
            'folio_from' => 10,
            'folio_to' => 20,
            'folio_current' => $current,
            'folio_annuled' => $annuled,
            'expires_on' => now()->addDays(10),
        ]);
    }

    public function test_annuls_single_folio_and_persists(): void
    {
        $caf = $this->caf();

        $returned = $caf->annulFolios([15], 'Folio dañado');

        static::assertSame($caf->getKey(), $returned->getKey());
        static::assertTrue($caf->folios->isAnnuled(15));
        static::assertSame([[15, 15]], $caf->fresh()->folio_annuled);
        static::assertSame(12, $caf->fresh()->folio_current);
    }

    public function test_annuls_range_and_mixed_batch(): void
    {
        $caf = $this->caf(current: 10);

        $caf->annulFolios([10, 12, [14, 16]], 'Daños');

        static::assertSame([[10, 10], [12, 12], [14, 16]], $caf->fresh()->folio_annuled);
    }

    public function test_throws_when_folio_out_of_range(): void
    {
        $caf = $this->caf();

        foreach ([[9], [21], [19, 25]] as $folios) {
            try {
                $caf->annulFolios($folios);
                static::fail('The annulment should have failed.');
            } catch (FolioOutOfRangeException) {
                static::assertSame([], $caf->fresh()->folio_annuled ?? []);
            }
        }
    }

    public function test_throws_when_folio_already_allocated(): void
    {
        $caf = $this->caf(current: 12);

        $this->expectException(FolioAlreadyAllocatedException::class);
        $this->expectExceptionMessageIs('The folio [10] was already allocated.');

        $caf->annulFolios([10]);
    }

    public function test_throws_when_folio_already_annuled_including_after_commit(): void
    {
        $caf = $this->caf(current: 10, annuled: [10]);

        $this->expectException(FolioAlreadyAnnuledException::class);
        $this->expectExceptionMessageIs('The folio [10] was already annulled.');

        $caf->annulFolios([10]);
    }

    public function test_throws_when_folio_already_annuled_on_a_second_annulment(): void
    {
        $caf = $this->caf();

        $caf->annulFolios([15]);

        $this->expectException(FolioAlreadyAnnuledException::class);

        $caf->annulFolios([15]);
    }

    public function test_is_all_or_nothing_when_a_folio_fails_validation(): void
    {
        $caf = $this->caf();

        try {
            $caf->annulFolios([15, 16, 21]);
            static::fail('The annulment should have failed.');
        } catch (FolioOutOfRangeException $exception) {
            static::assertSame('The folio [21] is out of the CAF range.', $exception->getMessage());
        }

        static::assertSame([], $caf->fresh()->folio_annuled ?? []);
    }

    public function test_allocation_skips_annuled_folios(): void
    {
        $caf = $this->caf(current: 10);

        $caf->annulFolios([10, 12, [14, 16]], 'Daños');

        $manager = $this->app->make(CafManager::class);

        foreach ([11, 13, 17, 18] as $expected) {
            static::assertSame(
                $expected,
                $manager->allocate($caf->rut, DteType::Invoice, static fn(SiiCaf $selected, int $folio): int => $folio),
            );
        }
    }

    public function test_dispatches_caf_folios_annuled_after_commit(): void
    {
        Event::fake([CafFoliosAnnuled::class]);

        $caf = $this->caf();

        $caf->annulFolios([15], 'Daños');

        Event::assertDispatched(
            CafFoliosAnnuled::class,
            fn(CafFoliosAnnuled $event): bool => $event->caf->getKey() === $caf->getKey() && $event->folios === [15],
        );
    }

    public function test_restores_folios_locally(): void
    {
        $caf = $this->caf(annuled: [10, 12, [14, 16]]);

        $caf->restoreFolios([10, [14, 16]]);

        static::assertFalse($caf->folios->isAnnuled(10));
        static::assertFalse($caf->folios->isAnnuled(14));
        static::assertTrue($caf->folios->isAnnuled(12));
        static::assertSame([[12, 12]], $caf->fresh()->folio_annuled);
    }

    public function test_annuls_folios_and_persists(): void
    {
        $caf = $this->caf();

        $caf->annulFolios([15], 'Daños');

        static::assertTrue($caf->folios->isAnnuled(15));
        static::assertSame([[15, 15]], $caf->fresh()->folio_annuled);
    }

    public function test_restore_failure_logs_and_rolls_back(): void
    {
        $caf = $this->caf(annuled: [10]);

        $this->mock(LoggerInterface::class)
            ->shouldReceive('error')
            ->once()
            ->with(
                'CAF folio restore failed, rolling back to the pre-restore state.',
                Mockery::on(static fn(array $context): bool => $context['flow'] === 'folio-restore'
                    && $context['caf_id'] === $caf->getKey()
                    && $context['folios'] === [10]
                    && is_a($context['exception'], Throwable::class, true)),
            );

        SiiCaf::query()->whereKey($caf->getKey())->delete();

        try {
            $caf->restoreFolios([10]);

            static::fail('Expected restore to fail.');
        } catch (ModelNotFoundException) {
            //
        }

        static::assertDatabaseCount('sii_cafs', 0);
    }
}
