<?php

namespace App\Services\Scoring;

use App\Models\Challenge;
use App\Services\Challenge\EvaluationResult;

/**
 * score = base × difficulty_multiplier
 *       + speed_bonus + accuracy_bonus + streak_bonus + no_hint_bonus   (§13)
 *
 * A PURE function of (challenge, evaluation, elapsed seconds, hints, streak).
 * It reads no request, touches no database and holds no state, which is what
 * makes a historical attempt reproducible: feed the same five inputs back in
 * years later and the same score comes out.
 *
 * Every value comes from `config/devlab.php`, so tuning is a config change that
 * tests read from the same place — a change can never silently disagree with an
 * assertion.
 */
class ScoreCalculator
{
    public function calculate(
        Challenge $challenge,
        EvaluationResult $evaluation,
        int $elapsedSeconds,
        int $hintsUsed = 0,
        int $streakDays = 0,
    ): ScoreBreakdown {
        $maxPossible = $this->maxPossible($challenge);

        /*
         * A wrong answer scores nothing, and does not earn a speed bonus for
         * being wrong quickly. Accuracy still carries partial credit for
         * experiences that grade a rubric rather than a right answer.
         */
        if (! $evaluation->correct && $evaluation->accuracy <= 0.0) {
            return new ScoreBreakdown(0, 0, 0, 0, 0, 0, $maxPossible);
        }

        $base = $evaluation->correct ? $this->basePoints($challenge) : 0;

        $speed = $evaluation->correct
            ? $this->speedBonus($challenge, $elapsedSeconds)
            : 0;

        $accuracy = $this->accuracyBonus($evaluation);
        $streak = $this->streakBonus($streakDays);
        $noHint = $evaluation->correct && $hintsUsed === 0
            ? (int) config('devlab.scoring.bonus.no_hint')
            : 0;

        return new ScoreBreakdown(
            base: $base,
            speedBonus: $speed,
            accuracyBonus: $accuracy,
            streakBonus: $streak,
            noHintBonus: $noHint,
            total: $base + $speed + $accuracy + $streak + $noHint,
            maxPossible: $maxPossible,
        );
    }

    /**
     * The challenge's own `points`, weighted by difficulty. A challenge may
     * override the configured base; the multiplier always applies.
     */
    private function basePoints(Challenge $challenge): int
    {
        $base = $challenge->points > 0
            ? $challenge->points
            : (int) config('devlab.scoring.base_points');

        $multiplier = (float) (config("devlab.scoring.difficulty_multiplier.{$challenge->difficulty}") ?? 1.0);

        return (int) round($base * $multiplier);
    }

    /**
     * Full marks for finishing inside `speed_ceiling_ratio` of the estimate,
     * nothing past `speed_floor_ratio`, linear in between.
     *
     * Speed is deliberately capped well below the accuracy bonus. Some
     * experiences reward reasoning quality, and a model that mostly rewards
     * typing fast turns DevLab into a race (§13).
     */
    private function speedBonus(Challenge $challenge, int $elapsedSeconds): int
    {
        $max = (int) config('devlab.scoring.bonus.speed_max');
        $estimate = $challenge->estimated_minutes * 60;

        // No usable estimate means no basis for a speed judgement. Award the
        // bonus rather than penalise the player for the author's omission.
        if ($estimate <= 0) {
            return $max;
        }

        $ceiling = $estimate * (float) config('devlab.scoring.speed_ceiling_ratio');
        $floor = $estimate * (float) config('devlab.scoring.speed_floor_ratio');

        if ($elapsedSeconds <= $ceiling) {
            return $max;
        }

        if ($elapsedSeconds >= $floor) {
            return 0;
        }

        $share = ($floor - $elapsedSeconds) / ($floor - $ceiling);

        return (int) round($max * $share);
    }

    private function accuracyBonus(EvaluationResult $evaluation): int
    {
        $max = (int) config('devlab.scoring.bonus.accuracy_max');
        $accuracy = max(0.0, min(1.0, $evaluation->accuracy));

        return (int) round($max * $accuracy);
    }

    /**
     * Streak length is a user statistic, and `user_statistics` is not populated
     * yet — that arrives with the XP and statistics slice (§56.8). Until then
     * callers pass 0 and this contributes nothing.
     *
     * The rule lives here now so the shape of the formula matches §13 and the
     * later slice adds a caller, not a new concept.
     */
    private function streakBonus(int $streakDays): int
    {
        if ($streakDays <= 1) {
            return 0;
        }

        $max = (int) config('devlab.scoring.bonus.streak_max');

        // Five consecutive days reaches the cap; beyond that it stops growing so
        // a long streak cannot outweigh solving anything correctly.
        return (int) round($max * min(1.0, ($streakDays - 1) / 4));
    }

    /**
     * The most this challenge can be worth, used for `max_score` so a score is
     * always interpretable against its own ceiling.
     */
    private function maxPossible(Challenge $challenge): int
    {
        $bonus = config('devlab.scoring.bonus');

        return $this->basePoints($challenge)
            + (int) $bonus['speed_max']
            + (int) $bonus['accuracy_max']
            + (int) $bonus['streak_max']
            + (int) $bonus['no_hint'];
    }
}
