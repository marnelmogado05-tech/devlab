<?php

namespace Database\Factories;

use App\Models\Challenge;
use App\Models\ChallengeReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChallengeReport>
 */
class ChallengeReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'challenge_id' => Challenge::factory(),
            'challenge_version' => 1,
            'user_id' => User::factory(),
            'reason' => ChallengeReport::REASON_WRONG_ANSWER,
            'details' => null,
            'status' => ChallengeReport::STATUS_OPEN,
        ];
    }

    public function reason(string $reason): static
    {
        return $this->state(fn (array $attributes) => ['reason' => $reason]);
    }

    public function resolved(string $note = 'Fixed and version bumped.'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChallengeReport::STATUS_RESOLVED,
            'resolution_note' => $note,
            'resolved_at' => now(),
        ]);
    }

    public function dismissed(string $note = 'Not a defect.'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChallengeReport::STATUS_DISMISSED,
            'resolution_note' => $note,
            'resolved_at' => now(),
        ]);
    }
}
