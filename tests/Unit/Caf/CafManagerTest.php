<?php

namespace Tests\Unit\Caf;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Laragear\Dte\Caf\CafManager;
use Laragear\Dte\Caf\Exceptions\CafNotFoundException;
use Laragear\Dte\Caf\Exceptions\DepletionException;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Facades\Caf;
use Laragear\Dte\Models\SiiCaf;
use Laragear\Rut\Rut;
use RuntimeException;
use Tests\DatabaseTestCase;
use Tests\Unit\Caf\Fixtures\CafFixture;

class CafManagerTest extends DatabaseTestCase
{
    /**
     * Allocate the next invoice folio.
     */
    protected function allocate(CafManager $manager, Rut $issuer): int
    {
        return $manager->allocate(
            $issuer,
            DteType::Invoice,
            static fn(SiiCaf $caf, int $folio): int => $folio,
        );
    }

    public function test_parses_and_persists_a_caf(): void
    {
        $fixture = CafFixture::create();

        $caf = $this->app->make(CafManager::class)->store($fixture->xml(10, 20));

        static::assertTrue($caf->exists);
        static::assertSame($fixture->issuer, $caf->rut->formatRaw());
        static::assertSame(DteType::Invoice, $caf->document_type);
        static::assertSame(10, $caf->folio_from);
        static::assertSame(20, $caf->folio_to);
        static::assertSame(10, $caf->folio_current);
        static::assertSame('2026-08-01', $caf->authorized_on->toDateString());
    }

    public function test_parses_and_persists_a_caf_from_file_path(): void
    {
        $fixture = CafFixture::create();

        $path = tempnam(sys_get_temp_dir(), 'caf_');
        file_put_contents($path, $fixture->xml(10, 20));

        $caf = $this->app->make(CafManager::class)->storeFile($path);

        static::assertTrue($caf->exists);
        static::assertSame($fixture->issuer, $caf->rut->formatRaw());

        unlink($path);
    }

    public function test_allocates_sequential_folios_atomically(): void
    {
        $caf = SiiCaf::factory()->create([
            'folio_from' => 10,
            'folio_to' => 11,
            'folio_current' => 10,
        ]);
        $manager = $this->app->make(CafManager::class);
        $issuer = $caf->rut;

        static::assertSame(10, $this->allocate($manager, $issuer));
        static::assertSame(11, $this->allocate($manager, $issuer));
        static::assertSame(12, $caf->fresh()->folio_current);
    }

    public function test_returns_the_selected_caf_to_the_allocation_callback(): void
    {
        $caf = SiiCaf::factory()->create();

        $selected = $this->app->make(CafManager::class)->allocate(
            $caf->rut,
            DteType::Invoice,
            static fn(SiiCaf $selected, int $folio): array => [$selected->getKey(), $folio],
        );

        static::assertSame([$caf->getKey(), $caf->folio_from], $selected);
    }

    public function test_caf_facade_resolves_caf_manager(): void
    {
        $fixture = CafFixture::create();

        $caf = Caf::store($fixture->xml(10, 20));

        static::assertTrue($caf->exists);
    }

    public function test_rolls_back_the_folio_when_the_callback_fails(): void
    {
        $caf = SiiCaf::factory()->create();

        try {
            $this->app->make(CafManager::class)->allocate(
                $caf->rut,
                DteType::Invoice,
                static fn(SiiCaf $selected, int $folio): never => throw new RuntimeException('Compilation failed.'),
            );
            static::fail('The allocation callback should have failed.');
        } catch (RuntimeException $exception) {
            static::assertSame('Compilation failed.', $exception->getMessage());
        }

        static::assertSame($caf->folio_current, $caf->fresh()->folio_current);
    }

    public function test_throws_when_file_does_not_exist(): void
    {
        $this->expectException(RuntimeException::class);

        $this->app->make(CafManager::class)->storeFile('/path/that/does/not/exist.xml');
    }

    public function test_throws_when_all_matching_cafs_are_depleted(): void
    {
        $caf = SiiCaf::factory()->create(['folio_to' => 10, 'folio_current' => 11]);

        $this->expectException(DepletionException::class);
        $this->expectExceptionMessageIs("No CAF folios available for the issuer [$caf->rut] and document type [33].");

        $this->allocate($this->app->make(CafManager::class), $caf->rut);
    }

    public function test_does_not_allocate_from_expired_cafs(): void
    {
        $caf = SiiCaf::factory()->create(['expires_on' => Carbon::now()->subDays(5)]);

        $this->expectException(DepletionException::class);
        $this->expectExceptionMessageIs("No CAF folios available for the issuer [$caf->rut] and document type [33].");

        $this->allocate($this->app->make(CafManager::class), $caf->rut);
    }

    public function test_allocate_retries_when_all_remaining_folios_are_annulled(): void
    {
        $manager = clone $this->app->make(CafManager::class);

        $caf1 = SiiCaf::factory()->depleted()->create([
            'document_type' => DteType::Invoice,
            'folio_from' => 10,
            'folio_to' => 10,
            'folio_current' => 10,
            'folio_annuled' => [10],
            'expires_on' => now()->addDays(10),
        ]);

        $caf2 = SiiCaf::factory()->create([
            'rut' => $caf1->rut,
            'document_type' => DteType::Invoice,
            'folio_from' => 11,
            'folio_to' => 20,
            'folio_current' => 11,
            'folio_annuled' => [],
            'expires_on' => now()->addDays(10),
        ]);

        $called = false;

        $manager->allocate($caf1->rut, DteType::Invoice, function ($caf, $folio) use (&$called) {
            static::assertSame(11, $folio);

            $called = true;
        });

        static::assertTrue($called);
    }

    public function test_allocate_throws_after_max_attempts(): void
    {
        $issuer = Rut::parse('76.123.456-7');

        // Create more depleted CAFs than the circuit breaker allows, all of
        // which look "available" (current <= to) but return null from next().
        for ($i = 0; $i < CafManager::MAX_ALLOCATE_ATTEMPTS + 2; $i++) {
            SiiCaf::factory()->create([
                'rut' => $issuer,
                'document_type' => DteType::Invoice,
                'folio_from' => 10 + $i,
                'folio_to' => 10 + $i,
                'folio_current' => 10 + $i,
                'folio_annuled' => [10 + $i],
                'expires_on' => now()->addDays(10),
            ]);
        }

        $this->expectException(DepletionException::class);
        $this->expectExceptionMessageIs(
            'Unable to allocate a folio issuer [76.123.456-7] and document type [33].',
        );

        $this->allocate($this->app->make(CafManager::class), $issuer);
    }

    public function test_annuls_folios_through_the_found_caf(): void
    {
        $caf = SiiCaf::factory()->create([
            'document_type' => DteType::Invoice,
            'folio_from' => 10,
            'folio_to' => 20,
            'folio_current' => 12,
            'folio_annuled' => [],
            'expires_on' => now()->addDays(10),
        ]);

        $result = $this->app->make(CafManager::class)
            ->annulFolios($caf->rut, DteType::Invoice, 'Daños', [15, 16]);

        static::assertSame($caf->getKey(), $result->getKey());
        static::assertSame([[15, 16]], $caf->fresh()->folio_annuled);
    }

    public function test_annuls_folios_through_the_caf_facade(): void
    {
        $caf = SiiCaf::factory()->create([
            'document_type' => DteType::Invoice,
            'folio_from' => 10,
            'folio_to' => 20,
            'folio_current' => 12,
            'folio_annuled' => [],
            'expires_on' => now()->addDays(10),
        ]);

        $result = Caf::annulFolios((string) $caf->rut, DteType::Invoice, 'Daños', [15]);

        static::assertSame($caf->getKey(), $result->getKey());
    }

    public function test_annuls_folios_with_numeric_document_type(): void
    {
        $caf = SiiCaf::factory()->create([
            'document_type' => DteType::Invoice,
            'folio_from' => 10,
            'folio_to' => 20,
            'folio_current' => 12,
            'folio_annuled' => [],
            'expires_on' => now()->addDays(10),
        ]);

        $result = $this->app->make(CafManager::class)
            ->annulFolios($caf->rut, DteType::Invoice->value, 'Daños', [15]);

        static::assertSame($caf->getKey(), $result->getKey());
        static::assertSame([[15, 15]], $caf->fresh()->folio_annuled);
    }

    public function test_throws_when_no_caf_covers_the_folios(): void
    {
        $this->expectException(CafNotFoundException::class);
        $this->expectExceptionMessageIs(
            'No CAF covers the issuer [12.345.678-9] and document type [33] for the folios [10, 12].',
        );

        $this->app->make(CafManager::class)->annulFolios('12.345.678-9', DteType::Invoice, 'Daños', [10, 12]);
    }

    public function test_throws_when_no_single_caf_covers_all_folios(): void
    {
        SiiCaf::factory()->create([
            'document_type' => DteType::Invoice,
            'folio_from' => 10,
            'folio_to' => 15,
            'folio_annuled' => [],
            'expires_on' => now()->addDays(10),
        ]);

        $caf2 = SiiCaf::factory()->create([
            'document_type' => DteType::Invoice,
            'folio_from' => 16,
            'folio_to' => 20,
            'folio_annuled' => [],
            'expires_on' => now()->addDays(10),
        ]);

        $this->expectException(CafNotFoundException::class);

        $this->app->make(CafManager::class)
            ->annulFolios($caf2->rut, DteType::Invoice, 'Daños', [15, 16]);
    }

    public function test_throws_when_no_folios_to_annul(): void
    {
        $caf = SiiCaf::factory()->create([
            'document_type' => DteType::Invoice,
            'folio_from' => 10,
            'folio_to' => 20,
            'folio_current' => 12,
            'folio_annuled' => [],
            'expires_on' => now()->addDays(10),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('No folios given to annul.');

        $this->app->make(CafManager::class)
            ->annulFolios($caf->rut, DteType::Invoice, 'No reason', []);
    }
}
