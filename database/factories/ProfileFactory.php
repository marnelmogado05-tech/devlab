<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'username' => 'dev'.fake()->unique()->numberBetween(1, 999_999),
            'display_name' => fake()->name(),
            'preferences' => [],
            'is_public' => true,
        ];
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes) => ['is_public' => false]);
    }
}
