<?php

namespace Tests\Unit\Caf;

use InvalidArgumentException;
use Laragear\Dte\Caf\Folio;
use PHPUnit\Framework\TestCase;

class FolioTest extends TestCase
{
    public function test_in_range(): void
    {
        $folio = new Folio(10, 20, 10);

        static::assertTrue($folio->isInRange(10));
        static::assertTrue($folio->isInRange(15));
        static::assertTrue($folio->isInRange(20));
        static::assertFalse($folio->isInRange(9));
        static::assertFalse($folio->isInRange(21));
    }

    public function test_allocatable(): void
    {
        $folio = new Folio(10, 20, 14);

        static::assertTrue($folio->isAllocatable(14));
        static::assertTrue($folio->isAllocatable(20));
        static::assertFalse($folio->isAllocatable(13));
        static::assertFalse($folio->isAllocatable(9));
        static::assertFalse($folio->isAllocatable(21));
    }

    public function test_normalizes_ints_and_ranges(): void
    {
        static::assertSame([10, 12, 14, 15, 16], Folio::normalize([10, 12, [14, 16]]));
        static::assertSame([10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20], Folio::normalize([[10, 20]]));
        static::assertSame([1, 5], Folio::normalize([1, 5]));
        static::assertSame([10, 11, 12], Folio::normalize([10, 10, [10, 12]]));
    }

    public function test_reverses_flipped_ranges_when_normalizing(): void
    {
        static::assertSame([14, 15, 16], Folio::normalize([[16, 14]]));
        static::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], Folio::normalize([[10, 1]]));
    }

    public function test_rejects_non_pair_ranges(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Folio::normalize([[10]]);
    }

    public function test_rejects_non_numeric_ranges(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Folio::normalize([[10, 'abc']]);
    }

    public function test_annuls_individual_folios(): void
    {
        $folio = new Folio(1, 100, 1);
        $folio->annul(5, 7, 10);

        static::assertTrue($folio->isAnnuled(5));
        static::assertTrue($folio->isAnnuled(7));
        static::assertTrue($folio->isAnnuled(10));
        static::assertFalse($folio->isAnnuled(6));
    }

    public function test_annuls_ranges(): void
    {
        $folio = new Folio(1, 100, 1);
        $folio->annul([5, 10]);

        static::assertTrue($folio->isAnnuled(5));
        static::assertTrue($folio->isAnnuled(7));
        static::assertTrue($folio->isAnnuled(10));
        static::assertFalse($folio->isAnnuled(4));
        static::assertFalse($folio->isAnnuled(11));
    }

    public function test_annul_reverses_flipped_range(): void
    {
        $folio = new Folio(1, 100, 1);
        $folio->annul([10, 7]);

        static::assertSame([[7, 10]], $folio->annuled);
        static::assertTrue($folio->isAnnuled(9));
        static::assertFalse($folio->isAnnuled(6));
    }

    public function test_mixed_annulments(): void
    {
        $folio = new Folio(1, 100, 1);
        $folio->annul(1, 2, [5, 7], 9, [11, 12]);

        static::assertTrue($folio->isAnnuled(1));
        static::assertTrue($folio->isAnnuled(6));
        static::assertTrue($folio->isAnnuled(9));
        static::assertTrue($folio->isAnnuled(12));
        static::assertFalse($folio->isAnnuled(3));
        static::assertFalse($folio->isAnnuled(8));
        static::assertFalse($folio->isAnnuled(10));
    }

    public function test_restores_folios(): void
    {
        $folio = new Folio(1, 100, 1);
        $folio->annul(1, 2, [5, 7]);

        $folio->restore(1, [5, 7]);

        static::assertFalse($folio->isAnnuled(1));
        static::assertTrue($folio->isAnnuled(2));
        static::assertFalse($folio->isAnnuled(5));
    }

    public function test_remaining_folios(): void
    {
        $folio = new Folio(1, 10, 1);
        $folio->annul(2, [4, 6]);

        // available: 1, 3, 7, 8, 9, 10 (total 6)
        static::assertSame(6, $folio->remaining());
    }

    public function test_remaining_includes_both_endpoints(): void
    {
        $folio = new Folio(1, 10, 1);

        static::assertSame(10, $folio->remaining());
    }

    public function test_remaining_decrements_after_allocation(): void
    {
        $folio = new Folio(1, 10, 1);

        $folio->next(); // allocates 1, current becomes 2

        static::assertSame(9, $folio->remaining());
    }

    public function test_is_exhausted_when_no_remaining_folios(): void
    {
        static::assertFalse((new Folio(1, 10, 1))->isExhausted());
        static::assertFalse((new Folio(1, 10, 1, [[1, 5]]))->isExhausted());

        static::assertTrue((new Folio(1, 10, 10, [[1, 10]]))->isExhausted());
        static::assertTrue((new Folio(1, 10, 11, []))->isExhausted());
    }

    public function test_is_not_exhausted_when_folios_remain(): void
    {
        static::assertTrue((new Folio(1, 10, 1))->isNotExhausted());
        static::assertTrue((new Folio(1, 10, 1, [[1, 5]]))->isNotExhausted());

        static::assertFalse((new Folio(1, 10, 10, [[1, 10]]))->isNotExhausted());
    }

    public function test_is_not_annuled(): void
    {
        $folio = new Folio(1, 100, 1);
        $folio->annul(5, 10);

        static::assertTrue($folio->isNotAnnuled(1));
        static::assertTrue($folio->isNotAnnuled(4));
        static::assertTrue($folio->isNotAnnuled(11));

        static::assertFalse($folio->isNotAnnuled(5));
        static::assertFalse($folio->isNotAnnuled(10));
    }

    public function test_first_and_last_folios(): void
    {
        $folio = new Folio(1, 10, 1);
        $folio->annul(1, 2, 10);

        static::assertSame(3, $folio->first());
        static::assertSame(9, $folio->last());
    }

    public function test_first_returns_current_when_not_annulled(): void
    {
        $folio = new Folio(1, 10, 1);

        static::assertSame(1, $folio->first());
    }

    public function test_last_returns_to_when_not_annulled(): void
    {
        $folio = new Folio(1, 10, 1);

        static::assertSame(10, $folio->last());
    }

    public function test_blocks(): void
    {
        $folio = new Folio(1, 10, 1);
        $folio->annul(3, 4, 8);

        // available: 1, 2, 5, 6, 7, 9, 10
        $expected = [
            [1, 2],
            [5, 7],
            [9, 10],
        ];

        static::assertSame($expected, $folio->blocks());
    }

    public function test_next(): void
    {
        $folio = new Folio(1, 5, 1);
        $folio->annul(2, 4);

        static::assertSame(1, $folio->next());
        static::assertSame(3, $folio->next());
        static::assertSame(5, $folio->next());
        static::assertNull($folio->next());
    }

    public function test_annul_and_restore_range(): void
    {
        $folio = new Folio(1, 10, 5, []);
        $folio->annulRange(6, 8);
        static::assertSame([[6, 8]], $folio->annuled);

        $folio->restoreRange(6, 8);
        static::assertSame([], $folio->annuled);
    }

    public function test_annul_returns_this(): void
    {
        $folio = new Folio(1, 10, 5, []);
        static::assertSame($folio, $folio->annul(6));
    }

    public function test_get_available_returns_null(): void
    {
        $folio = new Folio(1, 10, 10, [[1, 10]]);
        static::assertNull($folio->first());

        $folio = new Folio(1, 10, 11, []);
        static::assertNull($folio->first());
    }

    public function test_annul_all(): void
    {
        $folio = new Folio(1, 10, 5, []);
        $folio->annulAll();
        static::assertSame([[5, 10]], $folio->annuled);
    }

    public function test_restores_integer_removing_single_entry_from_list(): void
    {
        $folio = new Folio(1, 10, 5, [6, [7, 8]]);
        $folio->restore(6);
        static::assertSame([[7, 8]], $folio->annuled);
    }

    public function test_next_returns_null_when_exhausted(): void
    {
        $folio = new Folio(1, 10, 10, []);
        static::assertSame(10, $folio->next());

        $folio->current = 11;
        static::assertNull($folio->next());
    }

    public function test_last_returns_null_when_all_remaining_annulled(): void
    {
        $folio = new Folio(1, 10, 8, [[8, 10]]);
        static::assertNull($folio->last());
    }

    public function test_annuls_single_folio_to_range_tuple(): void
    {
        $folio = new Folio(1, 100, 1);
        $folio->annul(5);

        static::assertSame([[5, 5]], $folio->annuled);
    }

    public function test_annuls_range_merges_adjacent(): void
    {
        $folio = new Folio(1, 100, 1);
        $folio->annul([1, 5]);
        $folio->annul([6, 10]);

        static::assertSame([[1, 10]], $folio->annuled);
    }

    public function test_annuls_overlapping_ranges_merges(): void
    {
        $folio = new Folio(1, 100, 1);
        $folio->annul([1, 10]);
        $folio->annul([5, 15]);

        static::assertSame([[1, 15]], $folio->annuled);
    }

    public function test_restores_single_from_range_splits(): void
    {
        $folio = new Folio(1, 100, 1);
        $folio->annul([1, 10]);
        $folio->restore(5);

        static::assertSame([[1, 4], [6, 10]], $folio->annuled);
    }

    public function test_remaining_uses_math_on_large_range(): void
    {
        $folio = new Folio(1, 100000, 1);
        $folio->annulRange(500, 1500);   // 1001 annulled
        $folio->annulRange(5000, 6000);  // 1001 annulled
        $folio->annulRange(50000, 51000); // 1001 annulled

        static::assertSame(100000 - 3003, $folio->remaining());
    }

    public function test_next_jumps_past_annulled_range(): void
    {
        $folio = new Folio(1, 10000, 1);
        $folio->annulRange(5, 9999);

        static::assertSame(1, $folio->next());
        static::assertSame(2, $folio->next());
        static::assertSame(3, $folio->next());
        static::assertSame(4, $folio->next());

        // current is now 5 but [5, 9999] is annulled -> jump straight to 10000
        static::assertSame(10000, $folio->next());
        static::assertNull($folio->next());
    }

    public function test_constructor_normalizes_legacy_mixed_annuled(): void
    {
        $folio = new Folio(1, 20, 1, [5, [6, 10], 12, [15, 17]]);

        static::assertSame([[5, 10], [12, 12], [15, 17]], $folio->annuled);
        static::assertTrue($folio->isAnnuled(8));
        static::assertTrue($folio->isAnnuled(16));
        static::assertFalse($folio->isAnnuled(11));
    }

    public function test_normalize_throws_on_huge_range(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Folio::normalize([[1, 100000]]);
    }

    public function test_blocks_from_merged_ranges(): void
    {
        $folio = new Folio(1, 10, 1);
        $folio->annul([3, 4]);
        $folio->annul(8);

        static::assertSame([[1, 2], [5, 7], [9, 10]], $folio->blocks());
    }

    public function test_insert_range_flushes_carry_when_non_adjacent(): void
    {
        // annul [1,5] then [3,8] — carry extends, then [10,12] — new range is non-adjacent to carry [1,8]
        $folio = new Folio(1, 20, 1);
        $folio->annul([1, 5]);
        $folio->annul([3, 8]);
        $folio->annul([10, 12]);

        static::assertSame([[1, 8], [10, 12]], $folio->annuled);
    }

    public function test_insert_range_before_existing(): void
    {
        // When a range [10,20] is annulled first, then [1,5] is annulled,
        // the new range [1,5] is entirely before the existing range [10,20].
        // This exercises insertRange lines 134-136 (carryFrom === null && to < a - 1).
        $folio = new Folio(1, 30, 1);
        $folio->annul([10, 20]);
        $folio->annul([1, 5]);

        static::assertTrue($folio->isAnnuled(1));
        static::assertTrue($folio->isAnnuled(3));
        static::assertTrue($folio->isAnnuled(15));
        static::assertFalse($folio->isAnnuled(7));
    }

    public function test_subtract_range_splits_partial_right_overlap(): void
    {
        // annul [1,10] then restore [5,15] — existing range [1,10] starts before restore, ends within
        $folio = new Folio(1, 20, 1);
        $folio->annul([1, 10]);
        $folio->restore([5, 15]);

        static::assertSame([[1, 4]], $folio->annuled);
    }

    public function test_blocks_skips_outside_ranges(): void
    {
        // annulled range [1,5] is entirely before current=10, should be skipped
        $folio = new Folio(10, 20, 10, [[1, 5]]);

        static::assertSame([[10, 20]], $folio->blocks());
    }
}
