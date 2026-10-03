<?php

namespace Tests\Unit\Models;

use Generator;
use InvalidArgumentException;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\SiiCaf;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\DatabaseTestCase;

class SiiCafTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        SiiCaf::factory()->create([
            'rut' => '11.111.111-1',
            'document_type' => DteType::Invoice,
            'folio_from' => 50,
            'folio_to' => 100,
        ]);
    }

    public static function providesCollisionableRutDocumentOverlap(): Generator
    {
        yield 'overlaps from' => ['11.111.111-1', DteType::Invoice, 10, 50];
        yield 'overlaps to' => ['11.111.111-1', DteType::Invoice, 100, 120];
        yield 'overlaps inside from' => ['11.111.111-1', DteType::Invoice, 60, 120];
        yield 'overlaps inside to' => ['11.111.111-1', DteType::Invoice, 1, 60];
        yield 'overlaps around' => ['11.111.111-1', DteType::Invoice, 1, 120];
    }

    #[DataProvider('providesCollisionableRutDocumentOverlap')]
    public function test_collides_with_same_rut_same_document_overlap(
        string $rut,
        DteType $type,
        int $from,
        int $to,
    ): void {
        static::assertTrue(
            SiiCaf::query()->collidesWith($rut, $type, $from, $to)->exists(),
        );
    }

    public static function providesCollisionableRutDocumentNoOverlap(): Generator
    {
        yield 'below' => ['11.111.111-1', DteType::Invoice, 10, 49];
        yield 'over' => ['11.111.111-1', DteType::Invoice, 101, 150];
    }

    #[DataProvider('providesCollisionableRutDocumentNoOverlap')]
    public function test_does_not_collides_with_same_rut_same_document_no_overlap(
        string $rut,
        DteType $type,
        int $from,
        int $to,
    ): void {
        static::assertTrue(
            SiiCaf::query()->collidesWith($rut, $type, $from, $to)->doesntExist(),
        );
    }

    public function test_does_not_collides_with_different_rut(): void
    {
        // Different RUT, Overlapping
        static::assertFalse(
            SiiCaf::query()->collidesWith('22.222.222-2', DteType::Invoice, 50, 60)->exists(),
        );
    }

    public function test_does_not_collides_with_different_document(): void
    {
        // Different Document, Overlapping
        static::assertFalse(
            SiiCaf::query()->collidesWith('11.111.111-1', DteType::DispatchGuide, 50, 60)->exists(),
        );
    }

    public function test_where_depleted_scope_filters_correctly(): void
    {
        SiiCaf::factory()->create([
            'rut' => '33.333.333-3',
            'document_type' => DteType::Invoice,
            'folio_from' => 1,
            'folio_to' => 10,
            'depleted_at' => now(),
        ]);

        static::assertTrue(SiiCaf::query()->whereDepleted()->exists());
        static::assertSame(1, SiiCaf::query()->whereDepleted()->count());
        static::assertTrue(SiiCaf::query()->whereNotDepleted()->exists());
        static::assertSame(1, SiiCaf::query()->whereNotDepleted()->count());
    }

    public function test_throws_when_setting_invalid_folio_type(): void
    {
        $caf = SiiCaf::factory()->make();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The given value is not a Folio instance.');
        $caf->folios = 'invalid';
    }

    public function test_annuls_all_folios_marks_caf_as_depleted(): void
    {
        $caf = SiiCaf::factory()->create([
            'rut' => '55.555.555-5',
            'document_type' => DteType::Invoice,
            'folio_from' => 1,
            'folio_to' => 10,
            'folio_current' => 1,
        ]);

        $caf->annulFolios([1, [2, 10]], 'All folios are invalid.');

        static::assertNotNull($caf->depleted_at);
    }

    public function test_expired_at_accessor_returns_authorized_on_plus_six_months(): void
    {
        $caf = SiiCaf::factory()->create([
            'rut' => '77.777.777-7',
            'document_type' => DteType::Invoice,
            'authorized_on' => '2024-01-15',
        ]);

        $expiredAt = $caf->expired_at;

        static::assertNotNull($expiredAt);
        static::assertSame('2024-07-15', $expiredAt->format('Y-m-d'));
    }
}
