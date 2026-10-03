<?php

namespace Laragear\Dte\Caf;

use InvalidArgumentException;

class Folio
{
    /**
     * The maximum number of folios a single range may expand into via `normalize()`, to avoid OOM.
     */
    public const int MAX_EXPANDABLE_FOLIOS = 10000;

    /**
     * Create a new Folio instance.
     */
    public function __construct(
        public int $from,
        public int $to,
        public int $current,
        public array $annuled = [],
    ) {
        $this->annuled = $this->mergeAll($this->annuled);
    }

    /**
     * Normalize folio arguments into a flat list of folio numbers.
     *
     * @param  array<int|array{int, int}>  $folios
     * @return array<int>
     */
    public static function normalize(array $folios): array
    {
        // Ranges spanning more than `MAX_EXPANDABLE_FOLIOS` folios are refused,
        // to avoid unbounded in-memory expansion.
        $normalized = [];

        foreach ($folios as $folio) {
            if (is_array($folio)) {
                [$from, $to] = self::range($folio);

                if ($to - $from + 1 > static::MAX_EXPANDABLE_FOLIOS) {
                    throw new InvalidArgumentException(
                        "Folio range [$from, $to] is too large for flat expansion.",
                    );
                }

                for ($folio = $from; $folio <= $to; $folio++) {
                    $normalized[] = $folio;
                }

                continue;
            }

            $normalized[] = (int) $folio;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Normalizes a `[from, to]` pair into an ascending range, reversing flipped pairs.
     *
     * @return array{int, int}
     */
    protected static function range(array $range): array
    {
        if (count($range) !== 2 || !is_numeric($range[0]) || !is_numeric($range[1])) {
            throw new InvalidArgumentException('Folio ranges must be a pair of numbers, like [from, to].');
        }

        $from = (int) $range[0];
        $to = (int) $range[1];

        return $from > $to ? [$to, $from] : [$from, $to];
    }

    /**
     * Merge mixed folio inputs into sorted, non-overlapping tuples.
     *
     * @param  array<int|array{int, int}>  $folios
     * @return array<int, array{int, int}>
     */
    protected function mergeAll(array $folios): array
    {
        $merged = [];

        foreach ($folios as $folio) {
            if (is_array($folio)) {
                [$from, $to] = self::range($folio);
            } else {
                $to = (int) $folio;
                $from = $to;
            }

            $merged = $this->insertRange($merged, $from, $to);
        }

        return $merged;
    }

    /**
     * Insert a range into the sorted tuple list, merging overlaps.
     *
     * @param  array<int, array{int, int}>  $ranges
     * @return array<int, array{int, int}>
     */
    protected function insertRange(array $ranges, int $from, int $to): array
    {
        if ($ranges === []) {
            return [[$from, $to]];
        }

        $result = [];
        // The currently carried (merged) range that has not yet been flushed to $result.
        $carryFrom = null;
        $carryTo = null;

        foreach ($ranges as [$a, $b]) {
            if ($carryFrom === null && $from > $b + 1) {
                $result[] = [$a, $b];

                continue;
            }

            if ($carryFrom === null && $to < $a - 1) {
                $result[] = [$from, $to];
                $result[] = [$a, $b];

                continue;
            }

            $from = min($from, $a);
            $to = max($to, $b);
            $carryFrom = $from;
            $carryTo = $to;
        }

        $result[] = [$carryFrom ?? $from, $carryTo ?? $to];

        return $result;
    }

    /**
     * Merge a range into the annulment list, keeping it sorted.
     */
    protected function mergeRange(int $from, int $to): void
    {
        $this->annuled = $this->insertRange($this->annuled, $from, $to);
    }

    /**
     * Remove a range from the annulment list, splitting overlaps.
     */
    protected function subtractRange(int $from, int $to): void
    {
        $result = [];

        foreach ($this->annuled as [$a, $b]) {
            if ($b < $from || $a > $to) {
                $result[] = [$a, $b];

                continue;
            }

            if ($a < $from && $b > $to) {
                $result[] = [$a, $from - 1];
                $result[] = [$to + 1, $b];

                continue;
            }

            if ($a < $from && $b >= $from && $b <= $to) {
                $result[] = [$a, $from - 1];

                continue;
            }

            if ($b > $to && $a >= $from && $a <= $to) {
                $result[] = [$to + 1, $b];

                continue;
            }
        }

        $this->annuled = array_values($result);
    }

    /**
     * Finds the annuled range containing the given folio via binary search.
     *
     * @return array{int, int}|null
     */
    protected function rangeContaining(int $folio): ?array
    {
        $low = 0;
        $high = count($this->annuled) - 1;

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            [$from, $to] = $this->annuled[$mid];

            if ($folio < $from) {
                $high = $mid - 1;
            } elseif ($folio > $to) {
                $low = $mid + 1;
            } else {
                return $this->annuled[$mid];
            }
        }

        return null;
    }

    /**
     * Annuls specific folios or ranges (e.g., 4, 5, [7, 88]).
     *
     * @return $this
     */
    public function annul(int|array ...$folios): static
    {
        foreach ($folios as $folio) {
            if (is_array($folio)) {
                [$from, $to] = self::range($folio);
            } else {
                $from = $to = (int) $folio;
            }

            $this->mergeRange($from, $to);
        }

        return $this;
    }

    /**
     * Annuls a range of folios explicitly.
     *
     * @return $this
     */
    public function annulRange(int $from, int $to): static
    {
        return $this->annul([$from, $to]);
    }

    /**
     * Annuls all remaining folios.
     *
     * @return $this
     */
    public function annulAll(): static
    {
        return $this->annulRange($this->current, $this->to);
    }

    /**
     * Restores specific folios or ranges from annulment.
     *
     * @return $this
     */
    public function restore(int|array ...$folios): static
    {
        foreach ($folios as $folio) {
            if (is_array($folio)) {
                [$from, $to] = self::range($folio);
            } else {
                $from = $to = (int) $folio;
            }

            $this->subtractRange($from, $to);
        }

        return $this;
    }

    /**
     * Restores a range of folios from annulment.
     *
     * @return $this
     */
    public function restoreRange(int $from, int $to): static
    {
        return $this->restore([$from, $to]);
    }

    /**
     * Checks if a folio is annulled.
     */
    public function isAnnuled(int $folio): bool
    {
        return $this->rangeContaining($folio) !== null;
    }

    /**
     * Checks if a folio is not annulled.
     */
    public function isNotAnnuled(int $folio): bool
    {
        return !$this->isAnnuled($folio);
    }

    /**
     * Checks if a folio is inside the CAF range.
     */
    public function isInRange(int $folio): bool
    {
        return $this->from <= $folio && $folio <= $this->to;
    }

    /**
     * Check if a folio is not inside the CAF range.
     */
    public function isNotInRange(int $folio): bool
    {
        return !$this->isInRange($folio);
    }

    /**
     * Checks if a folio is allocatable (inside the range and not yet handed out).
     */
    public function isAllocatable(int $folio): bool
    {
        return $folio >= $this->current && $this->isInRange($folio);
    }

    /**
     * Check if a folio is not allocatable (outside the range or already handed out).
     */
    public function isNotAllocatable(int $folio): bool
    {
        return !$this->isAllocatable($folio);
    }

    /**
     * Returns the first available folio (accounting for annulled).
     */
    public function first(): ?int
    {
        $folio = $this->current;

        while ($folio <= $this->to && ($range = $this->rangeContaining($folio)) !== null) {
            $folio = $range[1] + 1;
        }

        return $folio <= $this->to ? $folio : null;
    }

    /**
     * Returns the last available folio in the CAF (accounting for annulled).
     */
    public function last(): ?int
    {
        $folio = $this->to;

        while ($folio >= $this->current && ($range = $this->rangeContaining($folio)) !== null) {
            $folio = $range[0] - 1;
        }

        return $folio >= $this->current ? $folio : null;
    }

    /**
     * Returns the number of available folios remaining.
     */
    public function remaining(): int
    {
        $total = $this->to - $this->current + 1;

        $annuled = 0;

        foreach ($this->annuled as [$from, $to]) {
            if ($to < $this->current || $from > $this->to) {
                continue;
            }

            $annuled += min($to, $this->to) - max($from, $this->current) + 1;
        }

        return max(0, $total - $annuled);
    }

    /**
     * Checks if the folio has been exhausted.
     */
    public function isExhausted(): bool
    {
        return $this->remaining() === 0;
    }

    /**
     * Checks if the folio has not been exhausted.
     */
    public function isNotExhausted(): bool
    {
        return !$this->isExhausted();
    }

    /**
     * Groups consecutive available folio ranges into groups, like `[[1, 4], [6, 10]]`.
     *
     * @return array<int, array{int, int}>
     */
    public function blocks(): array
    {
        $blocks = [];
        $from = $this->current;

        foreach ($this->annuled as [$a, $b]) {
            if ($b < $this->current || $a > $this->to) {
                continue;
            }

            $a = max($a, $this->current);
            $b = min($b, $this->to);

            if ($from < $a) {
                $blocks[] = [$from, $a - 1];
            }

            $from = max($from, $b + 1);
        }

        if ($from <= $this->to) {
            $blocks[] = [$from, $this->to];
        }

        return $blocks;
    }

    /**
     * Return the next available folio, or null when depleted.
     */
    public function next(): ?int
    {
        while ($this->current <= $this->to && ($range = $this->rangeContaining($this->current)) !== null) {
            $this->current = $range[1] + 1;
        }

        if ($this->current > $this->to) {
            return null;
        }

        $allocated = $this->current;

        $this->current++;

        return $allocated;
    }
}
