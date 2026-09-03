<?php

namespace Database\Seeders;

use App\Models\Experience;
use Illuminate\Database\Seeder;

/**
 * DevLab's experiences: the three from the MVP (plan §48), then Phase 2's (§49)
 * as each becomes playable.
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
                'slug' => 'system-design-lab',
                'name' => 'System Design Lab',
                'tagline' => 'Assemble an architecture that survives the requirements.',
                'description' => 'A brief, a list of things the system must do, and a set of '
                    .'decisions to make. Pick a cache, a database, a queue — or decide you do not '
                    .'need one. The first experience here that gives partial credit, because an '
                    .'architecture that meets four requirements out of five is a real answer.',
                'icon' => 'Network',
                'category' => 'architecture',
                'status' => Experience::STATUS_PUBLISHED,
                'default_difficulty' => 'medium',
                'estimated_minutes' => 8,
                'available_in_bored' => true,
                'sort_order' => 30,
                'config' => [],
            ],
            [
                'slug' => 'docker-escape-room',
                'name' => 'Docker Escape Room',
                'tagline' => 'The container will not start. The evidence is in front of you.',
                'description' => 'A Dockerfile, a compose file, the logs, and a symptom that makes '
                    .'no sense. Read across all of it, find the fault, and say what fixes it — the '
                    .'second half being the one that separates recognising a failure from '
                    .'understanding it.',
                'icon' => 'Container',
                'category' => 'operations',
                'status' => Experience::STATUS_PUBLISHED,
                'default_difficulty' => 'medium',
                'estimated_minutes' => 9,
                'available_in_bored' => true,
                'sort_order' => 40,
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
