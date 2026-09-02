<?php

namespace Database\Factories;

use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChallengeAttempt>
 */
class ChallengeAttemptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'challenge_id' => Challenge::factory(),
            'challenge_version' => 1,
            'status' => ChallengeAttempt::STATUS_STARTED,
            'started_at' => now(),
            'hints_used' => 0,
            'metadata' => [],
        ];
    }

    /**
     * An attempt opened far enough in the past to be eligible for expiry.
     */
    public function stale(): static
    {
        return $this->state(fn (array $attributes) => [
            'started_at' => now()->subMinutes(
                (int) config('devlab.attempts.expire_after_minutes') + 1
            ),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChallengeAttempt::STATUS_COMPLETED,
            'completed_at' => now(),
            'time_taken_seconds' => 120,
        ]);
    }

    public function abandoned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChallengeAttempt::STATUS_ABANDONED,
        ]);
    }
}
