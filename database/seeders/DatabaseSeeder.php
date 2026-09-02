<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        /*
         * Experiences only. Challenge CONTENT is deliberately not seeded here:
         * the shape of `challenges.configuration` is defined per experience in
         * docs/experiences/<slug>.md and enforced by that experience's validator,
         * and neither exists yet. Seeding content first would invent a schema
         * that the validator then has to be built around, or migrate away from.
         *
         * Content arrives with each experience slice, in the order the
         * experience contract sets out: doc, then validator, then evaluator,
         * then challenges.
         */
        $this->call(ExperienceSeeder::class);
    }
}
