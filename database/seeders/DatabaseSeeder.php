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
         * The catalogue lives in ContentSeeder, which the container entrypoint
         * also runs on every boot. Keeping one list means a newly authored
         * experience cannot reach a fresh clone but miss a running one.
         *
         * Challenge content arrives with its experience, once that experience's
         * contract document and configuration validator exist to define and
         * enforce the shape of `challenges.configuration`. Cursed Code and Bug
         * Hunter have both; Dev Roulette is the dispatcher and holds no content
         * of its own.
         */
        $this->call(ContentSeeder::class);
    }
}
