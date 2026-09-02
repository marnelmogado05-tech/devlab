<?php

namespace App\Services\Scoring;

/**
 * How a score was arrived at, component by component.
 *
 * Kept as a breakdown rather than a single integer so the UI can show a player
 * why they got what they got, and so a dispute about a score has an auditable
 * answer that does not require re-running the evaluator.
 */
final readonly class ScoreBreakdown
{
    public function __construct(
        public int $base,
        public int $speedBonus,
        public int $accuracyBonus,
        public int $streakBonus,
        public int $noHintBonus,
        public int $total,
        public int $maxPossible,
    ) {}

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'base' => $this->base,
            'speed_bonus' => $this->speedBonus,
            'accuracy_bonus' => $this->accuracyBonus,
            'streak_bonus' => $this->streakBonus,
            'no_hint_bonus' => $this->noHintBonus,
            'total' => $this->total,
            'max_possible' => $this->maxPossible,
        ];
    }
}
