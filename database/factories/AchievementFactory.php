<?php

namespace Database\Factories;

use App\Models\Achievement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Achievement>
 */
class AchievementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'achievement_'.fake()->unique()->numberBetween(1, 999_999),
            'name' => 'Test Achievement',
            'description' => 'Awarded in a test.',
            'icon' => 'Trophy',
            'category' => 'testing',
            'tier' => Achievement::TIER_BRONZE,
            'xp_bonus' => 0,
            // No rule by default, so a factory-made achievement never unlocks by
            // accident and a test asserting an unlock has to state its rule.
            'criteria' => [],
            'is_secret' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $conditions
     */
    public function requiringAll(array $conditions): static
    {
        return $this->state(fn (array $attributes) => [
            'criteria' => ['all' => $conditions],
        ]);
    }

    public function afterCompleting(int $count): static
    {
        return $this->requiringAll([
            ['stat' => 'challenges_completed', 'gte' => $count],
        ]);
    }

    public function worth(int $xp): static
    {
        return $this->state(fn (array $attributes) => ['xp_bonus' => $xp]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function secret(): static
    {
        return $this->state(fn (array $attributes) => ['is_secret' => true]);
    }
}
