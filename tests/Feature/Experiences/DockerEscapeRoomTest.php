<?php

declare(strict_types=1);

use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\Experience;
use App\Models\User;
use App\Services\Challenge\DockerEscapeRoom\DockerEscapeRoomConfiguration;
use App\Services\Challenge\DockerEscapeRoom\DockerEscapeRoomEvaluator;
use Database\Seeders\DockerEscapeRoomSeeder;
use Database\Seeders\ExperienceSeeder;

/**
 * A challenge in the documented shape: two selectable panels, one unselectable
 * log, three fixes, and a fault on line 3 of the Dockerfile.
 *
 * @param  array<string, mixed>  $configuration
 * @param  array<string, mixed>  $solution
 */
function dockerChallenge(array $configuration = [], array $solution = []): Challenge
{
    // Reused rather than recreated: several tests build two challenges to
    // compare verdicts, and a second factory call would violate the slug index.
    $experience = Experience::query()->firstWhere('slug', 'docker-escape-room')
        ?? Experience::factory()->published()->create(['slug' => 'docker-escape-room']);

    return Challenge::factory()->published()->for($experience)->make([
        'configuration' => [
            'symptom' => 'It will not start.',
            'evidence' => [
                [
                    'key' => 'dockerfile',
                    'label' => 'Dockerfile',
                    'language' => 'dockerfile',
                    'content' => "FROM alpine\nWORKDIR /app\nCMD sh run.sh",
                ],
                [
                    'key' => 'compose',
                    'label' => 'docker-compose.yml',
                    'language' => 'yaml',
                    'content' => "services:\n    api:\n        build: .",
                ],
                [
                    'key' => 'logs',
                    'label' => 'Container logs',
                    'language' => 'text',
                    'selectable' => false,
                    'content' => "starting\nexited with code 1",
                ],
            ],
            'fixes' => [
                ['key' => 'exec_form', 'text' => 'Use the exec form'],
                ['key' => 'publish', 'text' => 'Publish the port'],
                ['key' => 'rebuild', 'text' => 'Rebuild without cache'],
            ],
            ...$configuration,
        ],
        'solution' => [
            'evidence' => 'dockerfile',
            'line' => 3,
            'fix' => 'exec_form',
            'summary' => 'Shell form.',
            ...$solution,
        ],
    ]);
}

function dockerEvaluator(): DockerEscapeRoomEvaluator
{
    return app(DockerEscapeRoomEvaluator::class);
}

describe('the configuration validator', function () {
    it('accepts a challenge in the documented shape', function () {
        expect(app(DockerEscapeRoomConfiguration::class)->problems(dockerChallenge()))->toBe([]);
    });

    it('refuses a fault on a panel the challenge does not include', function () {
        expect(app(DockerEscapeRoomConfiguration::class)->problems(
            dockerChallenge(solution: ['evidence' => 'entrypoint'])
        ))->toContain("The solution names evidence 'entrypoint', which this challenge does not include.");
    });

    it('refuses a fault beyond the end of its panel', function () {
        /*
         * Bug Hunter's line-range check, now across several files — which is
         * where it stops being obvious, because a line number can be valid for
         * one panel and not for the one the solution names.
         */
        expect(app(DockerEscapeRoomConfiguration::class)->problems(
            dockerChallenge(solution: ['line' => 40])
        ))->toContain("Solution line 40 is beyond the end of 'dockerfile' (3 lines).");
    });

    it('refuses a fault on a blank line', function () {
        // Usually means the content was edited and the line number was not.
        $challenge = dockerChallenge(
            configuration: [
                'evidence' => [
                    [
                        'key' => 'dockerfile',
                        'label' => 'Dockerfile',
                        'language' => 'dockerfile',
                        'content' => "FROM alpine\n\nCMD sh run.sh",
                    ],
                    [
                        'key' => 'compose',
                        'label' => 'docker-compose.yml',
                        'language' => 'yaml',
                        'content' => "services:\n    api:\n        build: .",
                    ],
                ],
            ],
            solution: ['line' => 2],
        );

        expect(app(DockerEscapeRoomConfiguration::class)->problems($challenge))
            ->toContain("Solution line 2 of 'dockerfile' is blank.");
    });

    it('refuses a fault recorded on an unselectable panel', function () {
        /*
         * Logs are evidence you read, not code you fix. Recording a fault there
         * asks for a line number the interface deliberately never offers, so the
         * challenge would be unsolvable.
         */
        expect(app(DockerEscapeRoomConfiguration::class)->problems(
            dockerChallenge(solution: ['evidence' => 'logs', 'line' => 1])
        ))->toContain("Evidence 'logs' is not selectable, so no player can point at the fault on it.");
    });

    it('refuses a fix the challenge does not offer', function () {
        expect(app(DockerEscapeRoomConfiguration::class)->problems(
            dockerChallenge(solution: ['fix' => 'host_network'])
        ))->toContain("The solution names fix 'host_network', which this challenge does not offer.");
    });

    it('refuses a single evidence panel, which is Bug Hunter', function () {
        $challenge = dockerChallenge(configuration: [
            'evidence' => [
                [
                    'key' => 'dockerfile',
                    'label' => 'Dockerfile',
                    'language' => 'dockerfile',
                    'content' => "FROM alpine\nWORKDIR /app\nCMD sh run.sh",
                ],
            ],
        ]);

        expect(app(DockerEscapeRoomConfiguration::class)->isValid($challenge))->toBeFalse();
    });

    it('refuses two candidate fixes, which is a coin toss', function () {
        $challenge = dockerChallenge(configuration: [
            'fixes' => [
                ['key' => 'exec_form', 'text' => 'Use the exec form'],
                ['key' => 'publish', 'text' => 'Publish the port'],
            ],
        ]);

        expect(app(DockerEscapeRoomConfiguration::class)->isValid($challenge))->toBeFalse();
    });

    it('numbers lines the same way whatever wrote the file', function () {
        $challenge = dockerChallenge(configuration: [
            'evidence' => [
                [
                    'key' => 'dockerfile',
                    'label' => 'Dockerfile',
                    'language' => 'dockerfile',
                    'content' => "FROM alpine\r\nWORKDIR /app\r\nCMD sh run.sh",
                ],
                [
                    'key' => 'compose',
                    'label' => 'compose',
                    'language' => 'yaml',
                    'content' => "a\nb\nc",
                ],
            ],
        ]);

        expect(app(DockerEscapeRoomConfiguration::class)->lineCount($challenge, 'dockerfile'))->toBe(3);
    });
});

describe('the evaluator', function () {
    it('completes only when the fault and the remedy are both right', function () {
        $result = dockerEvaluator()->evaluate(dockerChallenge(), [
            'evidence' => 'dockerfile',
            'line' => 3,
            'fix' => 'exec_form',
        ]);

        expect($result->correct)->toBeTrue()
            ->and($result->accuracy)->toBe(1.0);
    });

    it('gives half credit for the right place and the wrong remedy', function () {
        $result = dockerEvaluator()->evaluate(dockerChallenge(), [
            'evidence' => 'dockerfile',
            'line' => 3,
            'fix' => 'rebuild',
        ]);

        expect($result->correct)->toBeFalse()
            ->and($result->accuracy)->toBe(0.5)
            ->and($result->details['located'])->toBeTrue()
            ->and($result->feedback)->toBe('Right place, wrong remedy.');
    });

    it('gives half credit for the right remedy found in the wrong place', function () {
        /*
         * Worth its own feedback line: it usually means the player recognised
         * the failure mode from experience without reading the evidence, which
         * is a real thing to learn about your own debugging.
         */
        $result = dockerEvaluator()->evaluate(dockerChallenge(), [
            'evidence' => 'compose',
            'line' => 1,
            'fix' => 'exec_form',
        ]);

        expect($result->accuracy)->toBe(0.5)
            ->and($result->feedback)->toBe('That would fix it, but the fault is not where you pointed.');
    });

    it('treats the right line of the wrong file as finding nothing', function () {
        // Line 3 exists in both panels. Only one of them is the fault.
        $result = dockerEvaluator()->evaluate(dockerChallenge(), [
            'evidence' => 'compose',
            'line' => 3,
            'fix' => 'rebuild',
        ]);

        expect($result->details['located'])->toBeFalse()
            ->and($result->accuracy)->toBe(0.0);
    });

    it('treats a stale panel or line as wrong rather than as an error', function () {
        // A tab open across a content edit can post either.
        $unknownPanel = dockerEvaluator()->evaluate(dockerChallenge(), [
            'evidence' => 'gone',
            'line' => 3,
            'fix' => 'exec_form',
        ]);

        $outOfRange = dockerEvaluator()->evaluate(dockerChallenge(), [
            'evidence' => 'dockerfile',
            'line' => 99,
            'fix' => 'exec_form',
        ]);

        expect($unknownPanel->details['located'])->toBeFalse()
            ->and($outOfRange->details['located'])->toBeFalse()
            // The remedy half still scores: the two are independent.
            ->and($outOfRange->accuracy)->toBe(0.5);
    });

    it('does not bound the line in its rules, because the panel is chosen in the same payload', function () {
        $rules = dockerEvaluator()->submissionRules(dockerChallenge());

        expect($rules['line'])->toBe(['required', 'integer', 'min:1'])
            ->and($rules)->toHaveKeys(['evidence', 'fix']);
    });

    it('never names the answer in its feedback', function () {
        foreach ([
            ['evidence' => 'dockerfile', 'line' => 3, 'fix' => 'rebuild'],
            ['evidence' => 'compose', 'line' => 1, 'fix' => 'exec_form'],
            ['evidence' => 'compose', 'line' => 1, 'fix' => 'rebuild'],
        ] as $submission) {
            $feedback = dockerEvaluator()->evaluate(dockerChallenge(), $submission)->feedback ?? '';

            expect($feedback)->not->toContain('dockerfile')
                ->and($feedback)->not->toContain('exec_form');
        }
    });
});

describe('the seeded content', function () {
    beforeEach(function () {
        $this->seed(ExperienceSeeder::class);
        $this->seed(DockerEscapeRoomSeeder::class);
    });

    it('is all valid against the contract', function () {
        $validator = app(DockerEscapeRoomConfiguration::class);

        $challenges = Challenge::query()
            ->whereRelation('experience', 'slug', 'docker-escape-room')
            ->get();

        expect($challenges)->toHaveCount(6);

        foreach ($challenges as $challenge) {
            expect($validator->problems($challenge))->toBe([], "{$challenge->slug} is invalid");
        }
    });

    it('can be played end to end', function () {
        $user = User::factory()->create();
        $challenge = Challenge::query()
            ->whereRelation('experience', 'slug', 'docker-escape-room')
            ->where('slug', 'bound-to-loopback')
            ->sole();

        $this->actingAs($user)->post(route('attempts.store', $challenge))->assertRedirect();

        $attempt = ChallengeAttempt::query()->open()->sole();

        $this->actingAs($user)
            ->post(route('attempts.submit', $attempt), [
                'submission' => [
                    'evidence' => $challenge->solution['evidence'],
                    'line' => $challenge->solution['line'],
                    'fix' => $challenge->solution['fix'],
                ],
            ])
            ->assertRedirect(route('attempts.show', $attempt));

        expect($attempt->refresh()->status)->toBe(ChallengeAttempt::STATUS_COMPLETED)
            ->and($attempt->score)->toBeGreaterThan(0);
    });

    it('never sends the answer to an open attempt', function () {
        $user = User::factory()->create();
        $challenge = Challenge::query()
            ->whereRelation('experience', 'slug', 'docker-escape-room')
            ->where('slug', 'bound-to-loopback')
            ->sole();

        $this->actingAs($user)->post(route('attempts.store', $challenge));
        $attempt = ChallengeAttempt::query()->open()->sole();

        $response = $this->actingAs($user)->get(route('attempts.show', $attempt))->assertOk();

        $props = json_encode($response->viewData('page')['props']);

        /*
         * Not a search for the string 'bind_all': the fix keys are offered to
         * the player on purpose, and one of them is necessarily the right one.
         * What must never appear is which one — `solution` and its summary.
         */
        expect($props)->not->toContain('own loopback')
            ->and($props)->not->toContain('solution')
            ->and($props)->not->toContain($challenge->explanation);
    });

    it('echoes the whole diagnosis back to a closed attempt', function () {
        // Three fields rather than one scalar, so the controller has to report
        // the shape it found rather than a named field.
        $user = User::factory()->create();
        $challenge = Challenge::query()
            ->whereRelation('experience', 'slug', 'docker-escape-room')
            ->where('slug', 'bound-to-loopback')
            ->sole();

        $this->actingAs($user)->post(route('attempts.store', $challenge));
        $attempt = ChallengeAttempt::query()->open()->sole();

        $this->actingAs($user)->post(route('attempts.submit', $attempt), [
            'submission' => ['evidence' => 'compose', 'line' => 2, 'fix' => 'publish'],
        ]);

        $this->actingAs($user)
            ->get(route('attempts.show', $attempt))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where(
                'attempt.submitted_answer',
                /*
                 * Decoded and compared loosely: the key order is the request's
                 * rather than ours, and `===` on arrays compares order too.
                 */
                fn (string $answer) => json_decode($answer, true) == [
                    'evidence' => 'compose',
                    'line' => 2,
                    'fix' => 'publish',
                ],
            ));
    });
});
