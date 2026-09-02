<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\XpTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<XpTransaction>
 */
class XpTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => 100,
            'source_type' => XpTransaction::SOURCE_CHALLENGE_COMPLETION,
            'source_id' => (string) fake()->unique()->numberBetween(1, 999_999),
            'description' => 'Completed a challenge',
            'metadata' => [],
        ];
    }
}
