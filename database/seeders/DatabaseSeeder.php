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
         * Experiences, then achievements, then content — in that order, because
         * each depends on the one before it.
         *
         * Challenge content arrives with its experience, once that experience's
         * contract document and configuration validator exist to define and
         * enforce the shape of `challenges.configuration`. Cursed Code has both;
         * Bug Hunter and Dev Roulette do not yet, so they seed no content.
         */
        $this->call([
            ExperienceSeeder::class,
            AchievementSeeder::class,
            // Content comes after the experiences it attaches to.
            CursedCodeSeeder::class,
        ]);
    }
}
