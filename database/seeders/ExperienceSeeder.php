<?php

namespace Database\Seeders;

use App\Models\Experience;
use Illuminate\Database\Seeder;

/**
 * The three MVP experiences (plan §48).
 *
 * Idempotent by slug: running it twice updates rather than duplicates, so it is
 * safe on an existing database and safe to re-run after editing a tagline.
 */
class ExperienceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->experiences() as $experience) {
            Experience::query()->updateOrCreate(
                ['slug' => $experience['slug']],
                $experience,
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function experiences(): array
    {
        return [
            [
                'slug' => 'cursed-code',
                'name' => 'Cursed Code',
                'tagline' => 'Predict what this horrifying snippet actually does.',
                'description' => 'Type coercion, floating point, regex, and the parts of your '
                    .'favourite language that only make sense if you were in the room. Read the '
                    .'snippet, predict the output, find out why you were wrong.',
                'icon' => 'Ghost',
                'category' => 'puzzle',
                'status' => Experience::STATUS_PUBLISHED,
                'default_difficulty' => 'medium',
                'estimated_minutes' => 5,
                'available_in_bored' => true,
                'sort_order' => 10,
                'config' => [],
            ],
            [
                'slug' => 'bug-hunter',
                'name' => 'Bug Hunter',
                'tagline' => 'Someone planted a defect. Find it.',
                'description' => 'A piece of code that looks fine and is not. Off-by-one, null '
                    .'handling, a race, a query that works until it does not. Locate the bug and '
                    .'say what it breaks.',
                'icon' => 'Bug',
                'category' => 'debugging',
                'status' => Experience::STATUS_PUBLISHED,
                'default_difficulty' => 'medium',
                'estimated_minutes' => 10,
                'available_in_bored' => true,
                'sort_order' => 20,
                'config' => [],
            ],
            [
                /*
                 * Dev Roulette is the "I'm Bored" dispatcher (plan §9.1, §10) — it
                 * assigns you something rather than holding challenges of its own.
                 *
                 * It therefore cannot appear in its own recommendation pool: an
                 * experience that can recommend itself produces a button that
                 * sometimes does nothing but ask you to press it again.
                 *
                 * Left as a draft until the recommender exists. Publishing it now
                 * would put a catalogue entry in front of visitors that leads to an
                 * empty page — the seeder's job is a clone that works, not a clone
                 * that looks finished (§77).
                 */
                'slug' => 'dev-roulette',
                'name' => 'Dev Roulette',
                'tagline' => 'Press the button. Take what you are given.',
                'description' => 'You do not choose. That is the point.',
                'icon' => 'Dices',
                'category' => 'meta',
                'status' => Experience::STATUS_DRAFT,
                'default_difficulty' => 'medium',
                'estimated_minutes' => 10,
                'available_in_bored' => false,
                'sort_order' => 0,
                'config' => [],
            ],
        ];
    }
}
