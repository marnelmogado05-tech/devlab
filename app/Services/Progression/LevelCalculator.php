<?php

namespace App\Services\Progression;

/**
 * Levels are DERIVED from total XP, never stored as an independently mutable
 * field (§14). `user_statistics.level` is a cache of what this returns, so a
 * disagreement between them is always resolved in favour of the ledger.
 *
 * The titles are gamification. They are not professional qualifications, and the
 * UI must say so (§9.10).
 */
class LevelCalculator
{
    /**
     * The highest level whose XP requirement this total meets.
     *
     * @return array{level: int, title: string, xp_required: int}
     */
    public function forXp(int $totalXp): array
    {
        $reached = $this->ladder()[0];

        foreach ($this->ladder() as $rung) {
            if ($totalXp >= $rung['xp_required']) {
                $reached = $rung;
            }
        }

        return $reached;
    }

    public function levelForXp(int $totalXp): int
    {
        return $this->forXp($totalXp)['level'];
    }

    /**
     * The next rung, or null at the top of the ladder.
     *
     * @return array{level: int, title: string, xp_required: int}|null
     */
    public function nextAfter(int $totalXp): ?array
    {
        foreach ($this->ladder() as $rung) {
            if ($rung['xp_required'] > $totalXp) {
                return $rung;
            }
        }

        return null;
    }

    /**
     * Progress towards the next level, 0.0–1.0. Returns 1.0 at the top, so a
     * progress bar reads as complete rather than as an error.
     */
    public function progressToNext(int $totalXp): float
    {
        $current = $this->forXp($totalXp);
        $next = $this->nextAfter($totalXp);

        if ($next === null) {
            return 1.0;
        }

        $span = $next['xp_required'] - $current['xp_required'];

        if ($span <= 0) {
            return 1.0;
        }

        return max(0.0, min(1.0, ($totalXp - $current['xp_required']) / $span));
    }

    /**
     * @return array<int, array{level: int, title: string, xp_required: int}>
     */
    private function ladder(): array
    {
        /** @var array<int, array{level: int, title: string, xp_required: int}> $levels */
        $levels = config('devlab.levels');

        return $levels;
    }
}
