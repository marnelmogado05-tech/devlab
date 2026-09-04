<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Everything DevLab needs to be playable, and nothing that belongs to a player.
 *
 * Split out from {@see DatabaseSeeder} so it can run on every container boot.
 * `DatabaseSeeder` also creates a fixed-email demo user, which is right once and
 * a unique-constraint violation every time after — content is the opposite:
 * every seeder here is keyed `updateOrCreate` on a slug or key, so re-running it
 * refreshes the catalogue and picks up newly authored challenges.
 *
 * Order matters. Achievements and challenges both hang off experiences.
 */
class ContentSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            ExperienceSeeder::class,
            AchievementSeeder::class,
            // Content comes after the experiences it attaches to.
            CursedCodeSeeder::class,
            BugHunterSeeder::class,
            SystemDesignLabSeeder::class,
            DockerEscapeRoomSeeder::class,
            GitSimulatorSeeder::class,
            CodeArenaSeeder::class,
        ]);
    }
}
