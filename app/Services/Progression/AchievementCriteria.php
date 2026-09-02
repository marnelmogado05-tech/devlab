<?php

namespace App\Services\Progression;

use App\Models\UserStatistic;

/**
 * Interprets an achievement's declarative `criteria` against a user's statistics.
 *
 * The whole point of §15 is that adding an achievement must not mean editing
 * code. So the rule is data, and this is the only thing that reads it.
 *
 * Shape:
 *
 *   {"all": [ <condition>, ... ]}   every condition must hold (the default)
 *   {"any": [ <condition>, ... ]}   at least one must hold
 *
 * A condition is one of:
 *
 *   {"stat": "challenges_completed", "gte": 10}
 *       compares a user_statistics column
 *
 *   {"experience": "bug-hunter", "stat": "completed", "gte": 100}
 *       compares a per-experience figure from user_statistics.per_experience
 *
 * Supported comparisons: gte, gt, lte, lt, eq.
 *
 * An unknown stat, experience or comparison evaluates to FALSE rather than
 * throwing. A typo in seed data should mean "nobody has earned this yet", not a
 * 500 on every completion for every user.
 */
class AchievementCriteria
{
    /**
     * @param  array<string, mixed>  $criteria
     */
    public function isMetBy(array $criteria, UserStatistic $statistic): bool
    {
        // Empty criteria never unlocks. An achievement with no rule is unfinished
        // content, and awarding it to everyone would be worse than awarding it to
        // nobody.
        if ($criteria === []) {
            return false;
        }

        if (isset($criteria['any']) && is_array($criteria['any'])) {
            foreach ($criteria['any'] as $condition) {
                if ($this->conditionHolds($condition, $statistic)) {
                    return true;
                }
            }

            return false;
        }

        $conditions = $criteria['all'] ?? null;

        if (! is_array($conditions) || $conditions === []) {
            return false;
        }

        foreach ($conditions as $condition) {
            if (! $this->conditionHolds($condition, $statistic)) {
                return false;
            }
        }

        return true;
    }

    private function conditionHolds(mixed $condition, UserStatistic $statistic): bool
    {
        if (! is_array($condition)) {
            return false;
        }

        $actual = isset($condition['experience'])
            ? $this->perExperienceValue($condition, $statistic)
            : $this->statValue($condition, $statistic);

        if ($actual === null) {
            return false;
        }

        return $this->compare($condition, $actual);
    }

    private function statValue(mixed $condition, UserStatistic $statistic): ?int
    {
        $stat = $condition['stat'] ?? null;

        if (! is_string($stat) || ! in_array($stat, $this->comparableStats(), true)) {
            return null;
        }

        return (int) $statistic->getAttribute($stat);
    }

    private function perExperienceValue(mixed $condition, UserStatistic $statistic): ?int
    {
        $slug = $condition['experience'] ?? null;
        $stat = $condition['stat'] ?? 'completed';

        if (! is_string($slug) || ! is_string($stat)) {
            return null;
        }

        $breakdown = $statistic->per_experience[$slug] ?? null;

        if (! is_array($breakdown)) {
            // Never played it. Zero rather than null, so "complete 0 of these"
            // is answerable and a gte:1 rule correctly fails.
            return 0;
        }

        return (int) ($breakdown[$stat] ?? 0);
    }

    private function compare(mixed $condition, int $actual): bool
    {
        foreach (['gte', 'gt', 'lte', 'lt', 'eq'] as $operator) {
            if (! array_key_exists($operator, $condition)) {
                continue;
            }

            $expected = (int) $condition[$operator];

            return match ($operator) {
                'gte' => $actual >= $expected,
                'gt' => $actual > $expected,
                'lte' => $actual <= $expected,
                'lt' => $actual < $expected,
                'eq' => $actual === $expected,
            };
        }

        return false;
    }

    /**
     * The columns a rule may compare against.
     *
     * An allow-list, not `$statistic->$stat`: criteria is data, and one day it
     * will be data a contributor submitted. Reflecting arbitrary strings onto a
     * model is how a rule ends up reading something it should not.
     *
     * @return array<int, string>
     */
    private function comparableStats(): array
    {
        return [
            'total_xp',
            'level',
            'challenges_started',
            'challenges_completed',
            'challenges_failed',
            'challenges_abandoned',
            'total_time_seconds',
            'current_streak_days',
            'longest_streak_days',
            'experiences_played',
            'achievements_unlocked',
        ];
    }
}
