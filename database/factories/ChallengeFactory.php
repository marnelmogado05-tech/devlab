<?php

namespace Database\Factories;

use App\Models\Challenge;
use App\Models\Experience;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Challenge>
 */
class ChallengeFactory extends Factory
{
    /**
     * Default state is a DRAFT challenge, for the same reason as ExperienceFactory.
     *
     * `solution` is populated by default and with a recognisable marker, so a
     * leak test has something specific to search the response for.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'experience_id' => Experience::factory(),
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 999_999),
            'title' => rtrim($title, '.'),
            'description' => fake()->paragraph(),
            'objective' => fake()->sentence(8),
            'rules' => null,
            'difficulty' => fake()->randomElement(config('devlab.difficulty.levels')),
            'type' => 'guess_output',
            'points' => 100,
            'estimated_minutes' => fake()->numberBetween(2, 15),
            'configuration' => ['snippet' => 'echo 0.1 + 0.2 == 0.3;'],
            'solution' => ['answer' => 'THE-ANSWER-KEY'],
            'explanation' => 'THE-EXPLANATION: floating point comparison is not exact.',
            'tags' => ['php', 'floating-point'],
            'status' => Challenge::STATUS_DRAFT,
            'version' => 1,
            'author_id' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Challenge::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Challenge::STATUS_ARCHIVED,
        ]);
    }

    public function difficulty(string $difficulty): static
    {
        return $this->state(fn (array $attributes) => [
            'difficulty' => $difficulty,
        ]);
    }
}
