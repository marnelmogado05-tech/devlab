<?php

namespace Database\Factories;

use App\Models\Experience;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Experience>
 */
class ExperienceFactory extends Factory
{
    /**
     * Default state is a DRAFT experience.
     *
     * Deliberate: a test that wants a visible experience must say ->published(),
     * so a test asserting something is visible cannot pass by accident.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Two word() calls rather than words(2): Faker declares words() as
        // array|string whichever way it is called, and word() as string.
        $name = fake()->word().' '.fake()->word();

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999_999),
            'name' => Str::title($name),
            'tagline' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'icon' => 'Sparkles',
            'category' => fake()->randomElement(['puzzle', 'debugging', 'infrastructure']),
            'status' => Experience::STATUS_DRAFT,
            'default_difficulty' => 'medium',
            'estimated_minutes' => fake()->numberBetween(3, 20),
            'available_in_bored' => true,
            'sort_order' => 0,
            'config' => [],
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Experience::STATUS_PUBLISHED,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Experience::STATUS_ARCHIVED,
        ]);
    }

    /**
     * Pulled out of the "I'm Bored" pool without being unpublished — the escape
     * hatch for an experience that is broken but should stay browsable.
     */
    public function hiddenFromBored(): static
    {
        return $this->state(fn (array $attributes) => [
            'available_in_bored' => false,
        ]);
    }
}
