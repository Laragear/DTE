<?php

namespace Tests\Performance;

use Illuminate\Support\Benchmark;
use Laragear\Dte\Caf\Folio;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Pinpoint performance tests for the Folio range-list internals.
 *
 * These are intentionally excluded from the default test suite because they
 * assert wall-clock timings. Run them explicitly with:
 *
 *     ./vendor/bin/phpunit --filter=FolioPerformanceTest --group=performance
 */
#[Group('performance')]
class FolioPerformanceTest extends TestCase
{
    public function test_remaining_is_fast_on_large_range(): void
    {
        $folio = new Folio(from: 1, to: 100000, current: 1, annuled: []);
        $folio->annulRange(500, 1500);   // 1001 annulled
        $folio->annulRange(5000, 6000);  // 1001 annulled
        $folio->annulRange(50000, 51000); // 1001 annulled

        $result = Benchmark::measure(fn (): int => $folio->remaining());

        static::assertSame(100000 - 3003, $folio->remaining());
        static::assertLessThan(10, $result, 'remaining() took '.$result.'ms');
    }

    public function test_next_skips_large_annuled_range(): void
    {
        $folio = new Folio(from: 1, to: 100000, current: 2, annuled: []);
        $folio->annulRange(2, 99999); // Almost everything annulled

        $result = Benchmark::measure(fn (): ?int => $folio->next());

        static::assertSame(100000, $folio->current - 1);
        static::assertLessThan(1, $result, 'next() took '.$result.'ms');
    }
}
