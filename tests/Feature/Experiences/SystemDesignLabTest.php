<?php

declare(strict_types=1);

use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\Experience;
use App\Models\User;
use App\Services\Challenge\SystemDesignLab\SystemDesignLabConfiguration;
use App\Services\Challenge\SystemDesignLab\SystemDesignLabEvaluator;
use Database\Seeders\ExperienceSeeder;
use Database\Seeders\SystemDesignLabSeeder;

/**
 * A challenge in the documented shape, with two slots and two requirements.
 *
 * @param  array<string, mixed>  $configuration
 * @param  array<string, mixed>  $solution
 */
function designChallenge(array $configuration = [], array $solution = []): Challenge
{
    $experience = Experience::factory()->published()->create(['slug' => 'system-design-lab']);

    return Challenge::factory()->published()->for($experience)->make([
        'configuration' => [
            'scenario' => 'A thing that must work.',
            'requirements' => [
                ['key' => 'scale', 'text' => 'Handle the load'],
                ['key' => 'simple', 'text' => 'Stay simple'],
            ],
            'slots' => [
                [
                    'key' => 'cache',
                    'label' => 'Cache',
                    'options' => [
                        ['key' => 'none', 'text' => 'No cache'],
                        ['key' => 'redis', 'text' => 'Redis'],
                    ],
                ],
                [
                    'key' => 'infra',
                    'label' => 'Infrastructure',
                    'options' => [
                        ['key' => 'single', 'text' => 'One server'],
                        ['key' => 'kubernetes', 'text' => 'Kubernetes'],
                    ],
                ],
            ],
            ...$configuration,
        ],
        'solution' => [
            'rubric' => [
                [
                    'requirement' => 'scale',
                    'all_of' => ['cache=redis'],
                    'explanation' => 'It needs a cache.',
                ],
                [
                    'requirement' => 'simple',
                    'none_of' => ['infra=kubernetes'],
                    'explanation' => 'It does not need a cluster.',
                ],
            ],
            'reference' => ['cache' => 'redis', 'infra' => 'single'],
            'pass_mark' => 1.0,
            ...$solution,
        ],
    ]);
}

function designEvaluator(): SystemDesignLabEvaluator
{
    return app(SystemDesignLabEvaluator::class);
}

describe('the configuration validator', function () {
    it('accepts a challenge in the documented shape', function () {
        expect(app(SystemDesignLabConfiguration::class)->problems(designChallenge()))->toBe([]);
    });

    it('refuses a rubric condition naming an option that does not exist', function () {
        /*
         * The check the whole class exists for. An unsatisfiable condition makes
         * every attempt fail while nothing reports an error, because players
         * failing is exactly what this system expects to see.
         */
        $challenge = designChallenge(solution: [
            'rubric' => [
                ['requirement' => 'scale', 'all_of' => ['cache=memcached'], 'explanation' => '...'],
                ['requirement' => 'simple', 'none_of' => ['infra=kubernetes'], 'explanation' => '...'],
            ],
        ]);

        expect(app(SystemDesignLabConfiguration::class)->problems($challenge))
            ->toContain("Condition 'cache=memcached' in 'scale' names option 'memcached', which slot 'cache' does not offer.");
    });

    it('refuses a rubric condition naming a slot that does not exist', function () {
        $challenge = designChallenge(solution: [
            'rubric' => [
                ['requirement' => 'scale', 'all_of' => ['queue=sqs'], 'explanation' => '...'],
                ['requirement' => 'simple', 'none_of' => ['infra=kubernetes'], 'explanation' => '...'],
            ],
        ]);

        expect(app(SystemDesignLabConfiguration::class)->problems($challenge))
            ->toContain("Condition 'queue=sqs' in 'scale' names slot 'queue', which does not exist.");
    });

    it('refuses a reference design that fails its own rubric', function () {
        /*
         * The only check that proves a rubric is achievable rather than merely
         * well-formed, and it proves it by running it.
         */
        $challenge = designChallenge(solution: ['reference' => ['cache' => 'none', 'infra' => 'single']]);

        expect(app(SystemDesignLabConfiguration::class)->problems($challenge))
            ->toContain('The reference design fails its own rubric: scale.');
    });

    it('refuses a reference design that leaves a slot undecided', function () {
        $challenge = designChallenge(solution: ['reference' => ['cache' => 'redis']]);

        expect(app(SystemDesignLabConfiguration::class)->problems($challenge))
            ->toContain("The reference design does not choose anything for slot 'infra'.");
    });

    it('refuses a requirement the rubric never scores', function () {
        // Shown to the player as a goal, then silently ignored — which makes the
        // brief a lie about what is being measured.
        $challenge = designChallenge(solution: [
            'rubric' => [
                ['requirement' => 'scale', 'all_of' => ['cache=redis'], 'explanation' => '...'],
            ],
        ]);

        expect(app(SystemDesignLabConfiguration::class)->problems($challenge))
            ->toContain("Requirement 'simple' is shown to the player but never scored.");
    });

    it('refuses a rubric entry with no conditions at all', function () {
        $challenge = designChallenge(solution: [
            'rubric' => [
                ['requirement' => 'scale', 'all_of' => ['cache=redis'], 'explanation' => '...'],
                ['requirement' => 'simple', 'explanation' => '...'],
            ],
        ]);

        expect(app(SystemDesignLabConfiguration::class)->problems($challenge))
            ->toContain("Requirement 'simple' has no conditions, so every design satisfies it.");
    });

    it('refuses duplicate option keys within a slot', function () {
        $challenge = designChallenge(configuration: [
            'slots' => [
                [
                    'key' => 'cache',
                    'label' => 'Cache',
                    'options' => [
                        ['key' => 'redis', 'text' => 'Redis'],
                        ['key' => 'redis', 'text' => 'Redis again'],
                    ],
                ],
                [
                    'key' => 'infra',
                    'label' => 'Infrastructure',
                    'options' => [
                        ['key' => 'single', 'text' => 'One server'],
                        ['key' => 'kubernetes', 'text' => 'Kubernetes'],
                    ],
                ],
            ],
        ]);

        expect(app(SystemDesignLabConfiguration::class)->problems($challenge))
            ->toContain("Option keys in slot 'cache' must be unique.");
    });

    it('refuses a single-slot challenge, which is a quiz rather than a design', function () {
        $challenge = designChallenge(configuration: [
            'slots' => [
                [
                    'key' => 'cache',
                    'label' => 'Cache',
                    'options' => [['key' => 'none', 'text' => 'No'], ['key' => 'redis', 'text' => 'Yes']],
                ],
            ],
        ]);

        expect(app(SystemDesignLabConfiguration::class)->isValid($challenge))->toBeFalse();
    });
});

describe('the evaluator', function () {
    it('accepts a design that satisfies every requirement', function () {
        $result = designEvaluator()->evaluate(
            designChallenge(),
            ['choices' => ['cache' => 'redis', 'infra' => 'single']],
        );

        expect($result->correct)->toBeTrue()
            ->and($result->accuracy)->toBe(1.0)
            ->and($result->details['unmet'])->toBe([]);
    });

    it('gives partial credit, which is the point of this experience', function () {
        /*
         * The first non-binary evaluator in DevLab. A design meeting one
         * requirement of two is a real if incomplete answer, and collapsing that
         * to "wrong" throws away the only interesting thing here.
         */
        $result = designEvaluator()->evaluate(
            designChallenge(),
            ['choices' => ['cache' => 'redis', 'infra' => 'kubernetes']],
        );

        expect($result->correct)->toBeFalse()
            ->and($result->accuracy)->toBe(0.5)
            ->and($result->details['unmet'])->toBe(['simple']);
    });

    it('completes below full marks when the pass mark allows it', function () {
        $result = designEvaluator()->evaluate(
            designChallenge(solution: ['pass_mark' => 0.5]),
            ['choices' => ['cache' => 'redis', 'infra' => 'kubernetes']],
        );

        expect($result->correct)->toBeTrue()
            ->and($result->accuracy)->toBe(0.5);
    });

    it('names the unmet requirements without naming the components that would meet them', function () {
        $result = designEvaluator()->evaluate(
            designChallenge(),
            ['choices' => ['cache' => 'none', 'infra' => 'kubernetes']],
        );

        expect($result->feedback)->toContain('scale')
            ->and($result->feedback)->toContain('simple')
            // The answer key never appears in feedback, before or after.
            ->and($result->feedback)->not->toContain('redis');
    });

    it('treats an option the challenge does not offer as no choice at all', function () {
        // A stale tab can post an option a newer version removed. That is wrong,
        // not an error.
        $result = designEvaluator()->evaluate(
            designChallenge(),
            ['choices' => ['cache' => 'memcached', 'infra' => 'single']],
        );

        expect($result->correct)->toBeFalse()
            ->and($result->details['choices'])->toBe(['infra' => 'single'])
            ->and($result->details['unmet'])->toBe(['scale']);
    });

    it('satisfies an any_of requirement from a single matching choice', function () {
        $challenge = designChallenge(solution: [
            'rubric' => [
                [
                    'requirement' => 'scale',
                    'any_of' => ['cache=redis', 'infra=kubernetes'],
                    'explanation' => '...',
                ],
                ['requirement' => 'simple', 'none_of' => ['infra=kubernetes'], 'explanation' => '...'],
            ],
        ]);

        $result = designEvaluator()->evaluate($challenge, [
            'choices' => ['cache' => 'redis', 'infra' => 'single'],
        ]);

        expect($result->accuracy)->toBe(1.0);
    });

    it('requires every slot to be answered', function () {
        // A partial design would be scored against requirements it never tried
        // to meet, which reads as the rubric being unfair rather than the
        // submission being incomplete.
        $rules = designEvaluator()->submissionRules(designChallenge());

        expect($rules)->toHaveKeys(['choices', 'choices.cache', 'choices.infra'])
            ->and($rules['choices.cache'])->toContain('required');
    });
});

describe('the seeded content', function () {
    beforeEach(function () {
        $this->seed(ExperienceSeeder::class);
        $this->seed(SystemDesignLabSeeder::class);
    });

    it('is all valid against the contract', function () {
        /*
         * This is the test that makes `reference` worth requiring: it scores
         * every author's own design against their own rubric. A rubric typo that
         * makes a challenge unsolvable fails here rather than showing up months
         * later as an unexplained 0% success rate.
         */
        $validator = app(SystemDesignLabConfiguration::class);

        $challenges = Challenge::query()
            ->whereRelation('experience', 'slug', 'system-design-lab')
            ->get();

        expect($challenges)->toHaveCount(6);

        foreach ($challenges as $challenge) {
            expect($validator->problems($challenge))->toBe([], "{$challenge->slug} is invalid");
        }
    });

    it('can be played end to end', function () {
        $user = User::factory()->create();
        $challenge = Challenge::query()
            ->whereRelation('experience', 'slug', 'system-design-lab')
            ->where('slug', 'url-shortener-read-heavy')
            ->sole();

        $this->actingAs($user)->post(route('attempts.store', $challenge))->assertRedirect();

        $attempt = ChallengeAttempt::query()->open()->sole();

        $this->actingAs($user)
            ->post(route('attempts.submit', $attempt), [
                'submission' => ['choices' => $challenge->solution['reference']],
            ])
            ->assertRedirect(route('attempts.show', $attempt));

        expect($attempt->refresh()->status)->toBe(ChallengeAttempt::STATUS_COMPLETED)
            ->and($attempt->score)->toBeGreaterThan(0);
    });

    it('shows a closed attempt the design that was submitted', function () {
        // The submission is a map rather than a scalar, so the controller has to
        // echo it back as JSON for the module to rebuild the board.
        $user = User::factory()->create();
        $challenge = Challenge::query()
            ->whereRelation('experience', 'slug', 'system-design-lab')
            ->where('slug', 'url-shortener-read-heavy')
            ->sole();

        $this->actingAs($user)->post(route('attempts.store', $challenge));
        $attempt = ChallengeAttempt::query()->open()->sole();

        $this->actingAs($user)->post(route('attempts.submit', $attempt), [
            'submission' => ['choices' => $challenge->solution['reference']],
        ]);

        $this->actingAs($user)
            ->get(route('attempts.show', $attempt))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where(
                'attempt.submitted_answer',
                json_encode($challenge->solution['reference']),
            ));
    });

    it('never sends the rubric to an open attempt', function () {
        /*
         * The client cannot show a live "requirements met" indicator without the
         * rubric — and the rubric is the answer key. This asserts nobody has
         * added one by widening what the page sends.
         */
        $user = User::factory()->create();
        $challenge = Challenge::query()
            ->whereRelation('experience', 'slug', 'system-design-lab')
            ->first();

        $this->actingAs($user)->post(route('attempts.store', $challenge));
        $attempt = ChallengeAttempt::query()->open()->sole();

        $response = $this->actingAs($user)->get(route('attempts.show', $attempt))->assertOk();

        $props = json_encode($response->viewData('page')['props']);

        expect($props)->not->toContain('rubric')
            ->and($props)->not->toContain('reference')
            ->and($props)->not->toContain('pass_mark');
    });
});
