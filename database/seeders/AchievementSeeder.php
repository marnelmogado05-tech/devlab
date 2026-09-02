<?php

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Seeder;

/**
 * The starting achievement set.
 *
 * Idempotent by key: re-running updates rather than duplicates, so a wording fix
 * is safe on an existing database. The key is also what the XP ledger uses as
 * the bonus source id, so it must never change once an achievement has shipped —
 * renaming it would pay every holder a second time.
 *
 * Every rule here is expressible against `user_statistics`. Two of the plan's
 * §15 examples are NOT yet: "Regex Wizard" (20 regex challenges) needs per-TAG
 * counts and "Explorer" (one from every category) needs per-CATEGORY counts, and
 * `user_statistics` tracks neither. They arrive with the statistic that can
 * answer them rather than as a rule nobody can evaluate.
 */
class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->achievements() as $achievement) {
            Achievement::query()->updateOrCreate(
                ['key' => $achievement['key']],
                $achievement,
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function achievements(): array
    {
        return [
            [
                'key' => 'first_blood',
                'name' => 'First Blood',
                'description' => 'Complete your first challenge.',
                'icon' => 'Sparkles',
                'category' => 'progression',
                'tier' => Achievement::TIER_BRONZE,
                'xp_bonus' => 25,
                'criteria' => ['all' => [['stat' => 'challenges_completed', 'gte' => 1]]],
                'sort_order' => 10,
            ],
            [
                'key' => 'getting_somewhere',
                'name' => 'Getting Somewhere',
                'description' => 'Complete ten challenges.',
                'icon' => 'TrendingUp',
                'category' => 'progression',
                'tier' => Achievement::TIER_BRONZE,
                'xp_bonus' => 50,
                'criteria' => ['all' => [['stat' => 'challenges_completed', 'gte' => 10]]],
                'sort_order' => 20,
            ],
            [
                'key' => 'centurion',
                'name' => 'Centurion',
                'description' => 'Complete a hundred challenges.',
                'icon' => 'Medal',
                'category' => 'progression',
                'tier' => Achievement::TIER_GOLD,
                'xp_bonus' => 250,
                'criteria' => ['all' => [['stat' => 'challenges_completed', 'gte' => 100]]],
                'sort_order' => 30,
            ],
            [
                'key' => 'curious_mind',
                'name' => 'Curious Mind',
                'description' => 'Try three different experiences.',
                'icon' => 'Compass',
                'category' => 'exploration',
                'tier' => Achievement::TIER_BRONZE,
                'xp_bonus' => 50,
                /*
                 * The plan says ten (§15). There are three experiences, so ten
                 * would be unreachable — an achievement nobody can earn is worse
                 * than one that is slightly easy. Raise it as the catalogue grows.
                 */
                'criteria' => ['all' => [['stat' => 'experiences_played', 'gte' => 3]]],
                'sort_order' => 40,
            ],
            [
                'key' => 'bug_whisperer',
                'name' => 'Bug Whisperer',
                'description' => 'Find a hundred planted bugs.',
                'icon' => 'Bug',
                'category' => 'mastery',
                'tier' => Achievement::TIER_GOLD,
                'xp_bonus' => 250,
                'criteria' => ['all' => [
                    ['experience' => 'bug-hunter', 'stat' => 'completed', 'gte' => 100],
                ]],
                'sort_order' => 50,
            ],
            [
                'key' => 'cursed_scholar',
                'name' => 'Cursed Scholar',
                'description' => 'Survive twenty-five Cursed Code challenges.',
                'icon' => 'Ghost',
                'category' => 'mastery',
                'tier' => Achievement::TIER_SILVER,
                'xp_bonus' => 100,
                'criteria' => ['all' => [
                    ['experience' => 'cursed-code', 'stat' => 'completed', 'gte' => 25],
                ]],
                'sort_order' => 60,
            ],
            [
                'key' => 'persistent',
                'name' => 'Persistent',
                'description' => 'Finish a challenge on seven consecutive days.',
                'icon' => 'Flame',
                'category' => 'dedication',
                'tier' => Achievement::TIER_SILVER,
                'xp_bonus' => 100,
                'criteria' => ['all' => [['stat' => 'longest_streak_days', 'gte' => 7]]],
                'sort_order' => 70,
            ],
            [
                'key' => 'undeterred',
                'name' => 'Undeterred',
                'description' => 'Get it wrong twenty-five times and keep going.',
                'icon' => 'HeartCrack',
                'category' => 'dedication',
                'tier' => Achievement::TIER_BRONZE,
                'xp_bonus' => 50,
                /*
                 * A failed attempt is data, not a verdict. Rewarding failure
                 * outright says so more convincingly than any copy on the page.
                 */
                'criteria' => ['all' => [['stat' => 'challenges_failed', 'gte' => 25]]],
                'sort_order' => 80,
            ],
        ];
    }
}
