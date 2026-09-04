<?php

declare(strict_types=1);

use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\ExecutionRun;
use App\Models\Experience;
use App\Models\User;
use App\Services\Challenge\CodeArena\CodeArenaConfiguration;
use App\Services\Challenge\CodeArena\CodeArenaEvaluator;
use Database\Seeders\CodeArenaSeeder;
use Database\Seeders\ExperienceSeeder;

/*
 * Code Arena is the first experience that runs a player's code (ADR 0008), and
 * almost none of these tests need a sandbox to say something true. The
 * separation is the point: execution produces VALUES, and everything about
 * whether those values are right is decided here, in PHP, against a key that
 * never left the server.
 *
 * What a real container actually does with the harness is tests/Sandbox's job.
 */

/**
 * @param  array<string, mixed>  $configuration
 * @param  array<string, mixed>  $solution
 */
function arenaChallenge(array $configuration = [], array $solution = []): Challenge
{
    $experience = Experience::query()->firstWhere('slug', 'code-arena')
        ?? Experience::factory()->published()->create(['slug' => 'code-arena']);

    return Challenge::factory()->published()->for($experience)->make([
        'configuration' => [
            'runtime' => 'php-8.4',
            'entry' => 'solve',
            'signature' => 'function solve(array $n): int',
            'brief' => 'Sum the list.',
            'starter' => "<?php\n\nfunction solve(array \$n): int {}\n",
            'cases' => [
                ['args' => [[1, 2, 3]], 'sample' => true, 'expected' => 6],
                ['args' => [[]], 'sample' => false],
                ['args' => [[7]], 'sample' => false],
            ],
            ...$configuration,
        ],
        'solution' => [
            'expected' => [6, 0, 7],
            'reference' => "<?php\nfunction solve(array \$n): int { return array_sum(\$n); }\n",
            ...$solution,
        ],
    ]);
}

function arenaConfiguration(): CodeArenaConfiguration
{
    return app(CodeArenaConfiguration::class);
}

/**
 * What a sandbox would have printed for a run returning these values, in order.
 *
 * @param  array<int, mixed>  $values
 * @return array<int, array<string, mixed>>
 */
function arenaObserved(array $values): array
{
    $observed = [];

    foreach ($values as $index => $value) {
        $observed[] = [
            'case' => $index,
            'status' => 'ok',
            'has_value' => true,
            'value' => $value,
            'output' => '',
            'ms' => 3,
        ];
    }

    return $observed;
}

describe('the configuration validator', function () {
    it('accepts a well-formed challenge', function () {
        expect(arenaConfiguration()->problems(arenaChallenge()))->toBe([]);
    });

    it('refuses a key with fewer entries than there are cases', function () {
        /*
         * The failure this class exists to catch. It is silent: the missing
         * cases grade against null, so every correct submission fails them and
         * the challenge looks brutally hard rather than broken.
         */
        $challenge = arenaChallenge(
            // Four cases against a three-entry key. Not two — a shorter key
            // trips the `min` rule first, and the shape-level failure would mask
            // the consistency check this test is about.
            configuration: ['cases' => [
                ['args' => [[1, 2, 3]], 'sample' => true, 'expected' => 6],
                ['args' => [[]], 'sample' => false],
                ['args' => [[7]], 'sample' => false],
                ['args' => [[4, 4]], 'sample' => false],
            ]],
            solution: ['expected' => [6, 0, 7]],
        );

        expect(arenaConfiguration()->problems($challenge))
            ->toContain('The answer key has 3 entries for 4 cases; it must have exactly one per case.');
    });

    it('refuses a sample whose shown answer contradicts the key', function () {
        // The challenge would be lying in its own worked example.
        $challenge = arenaChallenge(configuration: ['cases' => [
            ['args' => [[1, 2, 3]], 'sample' => true, 'expected' => 99],
            ['args' => [[]], 'sample' => false],
            ['args' => [[7]], 'sample' => false],
        ]]);

        expect(arenaConfiguration()->problems($challenge))
            ->toContain('Sample case 0 shows an expected value that differs from the answer key.');
    });

    it('refuses a hidden case that carries its answer in the configuration', function () {
        /*
         * `configuration` is sent to the client in full. An expectation left in
         * a hidden case is the answer key published on the attempt page, and
         * nothing else in the stack would notice.
         */
        $challenge = arenaChallenge(configuration: ['cases' => [
            ['args' => [[1, 2, 3]], 'sample' => true, 'expected' => 6],
            ['args' => [[]], 'sample' => false, 'expected' => 0],
            ['args' => [[7]], 'sample' => false],
        ]]);

        expect(arenaConfiguration()->problems($challenge))
            ->toContain('Case 1 is hidden but carries its expected value in `configuration`, which is sent to the client. Move it to `solution`.');
    });

    it('refuses a challenge with no hidden case', function () {
        $challenge = arenaChallenge(configuration: ['cases' => [
            ['args' => [[1, 2, 3]], 'sample' => true, 'expected' => 6],
            ['args' => [[]], 'sample' => true, 'expected' => 0],
            ['args' => [[7]], 'sample' => true, 'expected' => 7],
        ]]);

        expect(arenaConfiguration()->problems($challenge))
            ->toContain('At least one case must be hidden. A challenge whose whole key is visible tests transcription, not implementation.');
    });

    it('refuses a key that a constant function satisfies', function () {
        // `return 6;` would score full marks without reading the brief.
        $challenge = arenaChallenge(
            configuration: ['cases' => [
                ['args' => [[6]], 'sample' => true, 'expected' => 6],
                ['args' => [[1, 5]], 'sample' => false],
                ['args' => [[2, 4]], 'sample' => false],
            ]],
            solution: ['expected' => [6, 6, 6]],
        );

        expect(arenaConfiguration()->problems($challenge))
            ->toContain('Every case expects the same value, so a function that ignores its input passes them all.');
    });

    it('refuses a float expectation', function () {
        /*
         * Grading is exact equality, and exact equality on floats is not a
         * promise the language makes — DevLab ships a Bug Hunter challenge about
         * precisely that. Refusing is honest; inventing an epsilon nobody chose
         * would not be.
         */
        $challenge = arenaChallenge(solution: ['expected' => [6, 0.1, 7]]);

        expect(arenaConfiguration()->problems($challenge)[0])
            ->toContain('Expected value 1 contains a float.');
    });

    it('refuses cases that do not all take the same arguments', function () {
        $challenge = arenaChallenge(configuration: ['cases' => [
            ['args' => [[1, 2, 3]], 'sample' => true, 'expected' => 6],
            ['args' => [[], 2], 'sample' => false],
            ['args' => [[7]], 'sample' => false],
        ]]);

        expect(arenaConfiguration()->problems($challenge))
            ->toContain('Case 1 takes 2 arguments; case 0 takes 1. Every case must call the same signature.');
    });

    it('refuses a starter that does not define the graded function', function () {
        $challenge = arenaChallenge(configuration: ['starter' => "<?php\n\n// nothing here\n"]);

        expect(arenaConfiguration()->problems($challenge))
            ->toContain('The starter code does not mention `solve`, which is the function every case calls.');
    });
});

describe('the harness', function () {
    it('carries the case inputs and never the answers', function () {
        /*
         * The property ADR 0008 turns on, asserted rather than described. If an
         * expectation ever reaches the payload, code inside the sandbox can read
         * it — and a verdict computed next to hostile code is a verdict hostile
         * code can write.
         */
        $challenge = arenaChallenge(
            configuration: ['cases' => [
                ['args' => [[1, 2, 3]], 'sample' => true, 'expected' => 6],
                ['args' => [[]], 'sample' => false],
                ['args' => [[7]], 'sample' => false],
            ]],
            solution: ['expected' => [6, 0, 4242]],
        );

        $harness = arenaConfiguration()->harness($challenge, 10);

        // The inputs are in there, base64'd along with everything else.
        expect(base64_decode(decodedCasesFrom($harness), true))->toBe('[[[1,2,3]],[[]],[[7]]]')
            // And the distinctive answer is nowhere in the payload, encoded or not.
            ->and($harness)->not->toContain('4242')
            ->and(base64_decode(decodedCasesFrom($harness), true))->not->toContain('4242');
    });

    it('is generated from the challenge, so an author cannot ship a broken one', function () {
        // Nothing in the seed data is a test harness; the shape is ours.
        expect(arenaConfiguration()->harness(arenaChallenge(), 10))
            ->toContain('/tmp/case.php')
            ->toContain('proc_open');
    });
});

describe('the evaluator', function () {
    it('marks a run that returned every expected value correct', function () {
        $challenge = arenaChallenge();
        $run = arenaRunFor($challenge, arenaObserved([6, 0, 7]));

        $result = app(CodeArenaEvaluator::class)->evaluate($challenge, ['run_id' => $run->id]);

        expect($result->correct)->toBeTrue()
            ->and($result->accuracy)->toBe(1.0);
    });

    it('gives partial credit rather than nothing', function () {
        // Code that handles the ordinary cases and misses the empty one is a
        // real answer, and scoring it zero teaches nothing about which half.
        $challenge = arenaChallenge();
        $run = arenaRunFor($challenge, arenaObserved([6, 99, 7]));

        $result = app(CodeArenaEvaluator::class)->evaluate($challenge, ['run_id' => $run->id]);

        expect($result->correct)->toBeFalse()
            ->and($result->accuracy)->toBe(2 / 3);
    });

    it('ignores key order when comparing arrays', function () {
        /*
         * `===` on arrays compares key order, so a correct answer built by a
         * different route would fail for a reason with nothing to do with the
         * problem.
         */
        $challenge = arenaChallenge(solution: ['expected' => [['b' => 2, 'a' => 1], 0, 7]]);
        $run = arenaRunFor($challenge, arenaObserved([['a' => 1, 'b' => 2], 0, 7]));

        expect(app(CodeArenaEvaluator::class)->evaluate($challenge, ['run_id' => $run->id])->correct)
            ->toBeTrue();
    });

    it('does not accept a loose match for a strict one', function () {
        // A challenge whose answer is 0 must not be satisfied by false.
        $challenge = arenaChallenge(solution: ['expected' => [6, 0, 7]]);
        $run = arenaRunFor($challenge, arenaObserved([6, false, 7]));

        expect(app(CodeArenaEvaluator::class)->evaluate($challenge, ['run_id' => $run->id])->correct)
            ->toBeFalse();
    });

    it('fails a case the sandbox reported nothing for', function () {
        // A run that produced no line for case 2 fails case 2. It does not
        // shorten the test.
        $challenge = arenaChallenge();
        $run = arenaRunFor($challenge, arenaObserved([6, 0]));

        $result = app(CodeArenaEvaluator::class)->evaluate($challenge, ['run_id' => $run->id]);

        expect($result->correct)->toBeFalse()
            ->and($result->details['cases'][2]['status'])->toBe('missing');
    });

    it('takes the first line a run reported for a case, not the last', function () {
        /*
         * The harness emits one line per case. A second line for a case already
         * reported is either a bug or an attempt to overwrite an answer, and
         * neither should replace what was recorded first.
         */
        $challenge = arenaChallenge();

        $run = arenaRunFor($challenge, [
            ...arenaObserved([6, 0, 7]),
            ['case' => 1, 'status' => 'ok', 'has_value' => true, 'value' => 0],
            ['case' => 0, 'status' => 'ok', 'has_value' => true, 'value' => 999],
        ]);

        expect(app(CodeArenaEvaluator::class)->evaluate($challenge, ['run_id' => $run->id])->correct)
            ->toBeTrue();
    });

    it('separates running out of time from getting it wrong', function () {
        // The code may well be right and too slow, and "wrong" would send the
        // player looking in the wrong place.
        $challenge = arenaChallenge();

        $run = arenaRunFor($challenge, [
            ['case' => 0, 'status' => 'ok', 'has_value' => true, 'value' => 6],
            ['case' => 1, 'status' => 'timeout', 'has_value' => false],
            ['case' => 2, 'status' => 'ok', 'has_value' => true, 'value' => 7],
        ]);

        expect(app(CodeArenaEvaluator::class)->evaluate($challenge, ['run_id' => $run->id])->feedback)
            ->toContain('ran out of time');
    });

    it('never puts a hidden expectation in the feedback', function () {
        $challenge = arenaChallenge(solution: ['expected' => [6, 31337, 7]]);
        $run = arenaRunFor($challenge, arenaObserved([6, 1, 7]));

        expect(app(CodeArenaEvaluator::class)->evaluate($challenge, ['run_id' => $run->id])->feedback)
            ->not->toContain('31337');
    });
});

describe('the seeded content', function () {
    beforeEach(function () {
        $this->seed(ExperienceSeeder::class);
        $this->seed(CodeArenaSeeder::class);
    });

    it('seeds challenges that all validate', function () {
        $challenges = Challenge::query()
            ->whereRelation('experience', 'slug', 'code-arena')
            ->get();

        expect($challenges)->not->toBeEmpty();

        foreach ($challenges as $challenge) {
            expect(arenaConfiguration()->problems($challenge))
                ->toBe([], "{$challenge->slug} is not a valid Code Arena challenge");
        }
    });

    it('gives every challenge a reference solution that defines the graded function', function () {
        /*
         * The validator checks this too. Asserting it over the SEEDED rows is
         * the difference between "the rule exists" and "the content obeys it" —
         * and an unsolvable challenge is invisible until somebody fails it.
         */
        $challenges = Challenge::query()
            ->whereRelation('experience', 'slug', 'code-arena')
            ->get();

        foreach ($challenges as $challenge) {
            expect($challenge->solution['reference'])
                ->toContain('function '.$challenge->configuration['entry']);
        }
    });

    it('never publishes a hidden case answer in the configuration', function () {
        // The thing an attacker wants, in the payload the client is sent.
        $challenges = Challenge::query()
            ->whereRelation('experience', 'slug', 'code-arena')
            ->get();

        foreach ($challenges as $challenge) {
            foreach ($challenge->configuration['cases'] as $index => $case) {
                if (($case['sample'] ?? false) !== true) {
                    expect($case)->not->toHaveKey(
                        'expected',
                        "{$challenge->slug} case {$index} publishes its answer",
                    );
                }
            }
        }
    });

    it('is kept out of the "I\'m Bored" pool', function () {
        /*
         * The button's promise is that pressing it gives you something to do.
         * Code Arena's playability depends on whether the deployment has
         * execution enabled, so handing it out at random breaks that promise in
         * the one place DevLab cannot afford to.
         */
        expect(Experience::query()->where('slug', 'code-arena')->value('available_in_bored'))
            ->toBeFalse();
    });
});

describe('the attempt', function () {
    beforeEach(function () {
        $this->seed(ExperienceSeeder::class);
        $this->seed(CodeArenaSeeder::class);

        $this->user = User::factory()->create();
        $this->challenge = Challenge::query()
            ->whereRelation('experience', 'slug', 'code-arena')
            ->where('slug', 'parse-a-semver')
            ->sole();
    });

    it('can be played end to end from a finished run', function () {
        $this->actingAs($this->user)->post(route('attempts.store', $this->challenge))->assertRedirect();

        $attempt = ChallengeAttempt::query()->open()->sole();

        // Stands in for the sandbox: the run is what execution PRODUCES, and
        // everything from here is the application's own decision.
        $run = ExecutionRun::query()->create([
            'challenge_attempt_id' => $attempt->id,
            'user_id' => $this->user->id,
            'runtime' => 'php-8.4',
            'source' => '<?php',
            'status' => ExecutionRun::STATUS_FINISHED,
            'observed' => arenaObserved($this->challenge->solution['expected']),
        ]);

        $this->actingAs($this->user)
            ->post(route('attempts.submit', $attempt), ['submission' => ['run_id' => $run->id]])
            ->assertRedirect(route('attempts.show', $attempt));

        expect($attempt->refresh()->status)->toBe(ChallengeAttempt::STATUS_COMPLETED);
    });

    it('refuses a run belonging to somebody else', function () {
        /*
         * Run ids are sequential, so this is guessable by construction. Without
         * the attempt scope in the validation rule, a player could submit
         * another player's passing run and be scored for it.
         */
        $this->actingAs($this->user)->post(route('attempts.store', $this->challenge));
        $mine = ChallengeAttempt::query()->open()->sole();

        $other = User::factory()->create();
        $this->actingAs($other)->post(route('attempts.store', $this->challenge));
        $theirs = ChallengeAttempt::query()->open()->where('user_id', $other->id)->sole();

        $theirRun = ExecutionRun::query()->create([
            'challenge_attempt_id' => $theirs->id,
            'user_id' => $other->id,
            'runtime' => 'php-8.4',
            'source' => '<?php',
            'status' => ExecutionRun::STATUS_FINISHED,
            'observed' => arenaObserved($this->challenge->solution['expected']),
        ]);

        $this->actingAs($this->user)
            ->post(route('attempts.submit', $mine), ['submission' => ['run_id' => $theirRun->id]])
            ->assertSessionHasErrors('submission.run_id');

        expect($mine->refresh()->status)->toBe(ChallengeAttempt::STATUS_STARTED);
    });

    it('refuses a run the platform could not complete', function () {
        /*
         * S7, at the last possible moment. A capacity failure must never become
         * a verdict on somebody's code — so an unavailable run is not
         * submittable at all, and the attempt stays open.
         */
        $this->actingAs($this->user)->post(route('attempts.store', $this->challenge));
        $attempt = ChallengeAttempt::query()->open()->sole();

        $run = ExecutionRun::query()->create([
            'challenge_attempt_id' => $attempt->id,
            'user_id' => $this->user->id,
            'runtime' => 'php-8.4',
            'source' => '<?php',
            'status' => ExecutionRun::STATUS_UNAVAILABLE,
            'failure_reason' => ExecutionRun::REASON_UNAVAILABLE,
        ]);

        $this->actingAs($this->user)
            ->post(route('attempts.submit', $attempt), ['submission' => ['run_id' => $run->id]])
            ->assertSessionHasErrors('submission.run_id');

        expect($attempt->refresh()->status)->toBe(ChallengeAttempt::STATUS_STARTED);
    });

    it('never sends an answer key to an open attempt', function () {
        $this->actingAs($this->user)->post(route('attempts.store', $this->challenge));
        $attempt = ChallengeAttempt::query()->open()->sole();

        $response = $this->actingAs($this->user)->get(route('attempts.show', $attempt))->assertOk();

        $props = json_encode($response->viewData('page')['props']);

        // The sample answers ARE sent — they are the worked examples. The hidden
        // ones, and the reference solution, are not.
        expect($props)->not->toContain('preg_match')
            ->and($props)->not->toContain('solution')
            ->and($props)->not->toContain('"major":10');
    });
});

/**
 * A finished run against a fresh attempt for this challenge.
 *
 * @param  array<int, array<string, mixed>>  $observed
 */
function arenaRunFor(Challenge $challenge, array $observed): ExecutionRun
{
    $challenge->save();

    $attempt = ChallengeAttempt::factory()->for($challenge)->create();

    return ExecutionRun::query()->create([
        'challenge_attempt_id' => $attempt->id,
        'user_id' => $attempt->user_id,
        'runtime' => 'php-8.4',
        'source' => '<?php',
        'status' => ExecutionRun::STATUS_FINISHED,
        'observed' => $observed,
    ]);
}

/** The base64 case payload baked into a generated harness. */
function decodedCasesFrom(string $harness): string
{
    preg_match("/\\\$cases = json_decode\\(base64_decode\\('([^']+)'\\)/", $harness, $matches);

    return $matches[1] ?? '';
}
