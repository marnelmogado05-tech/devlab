<?php

declare(strict_types=1);

use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\Experience;
use App\Models\User;
use App\Models\XpTransaction;
use App\Services\Challenge\CursedCode\CursedCodeConfiguration;
use App\Services\Challenge\CursedCode\CursedCodeEvaluator;
use App\Services\Challenge\EvaluationResult;
use Database\Seeders\CursedCodeSeeder;
use Database\Seeders\ExperienceSeeder;

/**
 * @param  array<string, mixed>  $configuration
 * @param  array<string, mixed>  $solution
 */
function cursedChallenge(array $configuration = [], array $solution = ['answer' => 'b']): Challenge
{
    $experience = Experience::query()->firstWhere('slug', 'cursed-code')
        ?? Experience::factory()->published()->create(['slug' => 'cursed-code']);

    return Challenge::factory()->published()->for($experience)->create([
        'configuration' => [
            'language' => 'php',
            'mode' => 'guess_output',
            'snippet' => 'var_dump(0.1 + 0.2 == 0.3);',
            'options' => [
                ['key' => 'a', 'text' => 'bool(true)'],
                ['key' => 'b', 'text' => 'bool(false)'],
            ],
            ...$configuration,
        ],
        'solution' => $solution,
    ]);
}

describe('configuration validation', function () {
    it('accepts a well-formed challenge', function () {
        expect(app(CursedCodeConfiguration::class)->problems(cursedChallenge()))->toBe([]);
    });

    it('rejects an answer that matches no option', function () {
        /*
         * The dangerous one. Such a challenge is unsolvable, every attempt fails,
         * and the difficulty calibration built on that success rate is quietly
         * wrong — with nothing anywhere reporting an error.
         */
        $challenge = cursedChallenge(solution: ['answer' => 'z']);

        expect(app(CursedCodeConfiguration::class)->problems($challenge))
            ->toContain('The solution answer must match one of the option keys.');
    });

    it('rejects duplicate option keys', function () {
        $challenge = cursedChallenge([
            'options' => [
                ['key' => 'a', 'text' => 'one'],
                ['key' => 'a', 'text' => 'two'],
            ],
        ], ['answer' => 'a']);

        expect(app(CursedCodeConfiguration::class)->problems($challenge))
            ->toContain('Option keys must be unique.');
    });

    it('rejects two options that read identically', function () {
        // One of them is wrong however the player chooses.
        $challenge = cursedChallenge([
            'options' => [
                ['key' => 'a', 'text' => 'bool(false)'],
                ['key' => 'b', 'text' => 'bool(false)'],
            ],
        ]);

        expect(app(CursedCodeConfiguration::class)->problems($challenge))
            ->toContain('Option text must be unique.');
    });

    it('rejects an unknown mode', function () {
        $challenge = cursedChallenge(['mode' => 'interpretive_dance']);

        expect(app(CursedCodeConfiguration::class)->isValid($challenge))->toBeFalse();
    });

    it('requires at least two options', function () {
        $challenge = cursedChallenge([
            'options' => [['key' => 'b', 'text' => 'the only choice']],
        ]);

        expect(app(CursedCodeConfiguration::class)->isValid($challenge))->toBeFalse();
    });
});

describe('evaluation', function () {
    beforeEach(function () {
        $this->evaluator = new CursedCodeEvaluator;
        $this->challenge = cursedChallenge();
    });

    it('marks the right answer correct', function () {
        $result = $this->evaluator->evaluate($this->challenge, ['answer' => 'b']);

        expect($result->correct)->toBeTrue()
            ->and($result->accuracy)->toBe(1.0);
    });

    it('marks a wrong answer incorrect with no partial credit', function () {
        $result = $this->evaluator->evaluate($this->challenge, ['answer' => 'a']);

        expect($result->correct)->toBeFalse()
            ->and($result->accuracy)->toBe(0.0);
    });

    it('does not reveal the right answer in its feedback', function () {
        // Telling them the letter teaches nothing, and would spoil a replay.
        $result = $this->evaluator->evaluate($this->challenge, ['answer' => 'a']);

        expect($result->feedback)->not->toContain('b');
    });

    it('treats an option that no longer exists as wrong, not as an error', function () {
        // A stale tab can legitimately post a key a new version dropped.
        $result = $this->evaluator->evaluate($this->challenge, ['answer' => 'z']);

        expect($result->correct)->toBeFalse()
            ->and($result->details['reason'])->toBe('unknown_option');
    });

    it('is not fooled by case or whitespace', function () {
        // The keys are ours, not the user's, so there is nothing to normalise —
        // and normalising would only let two options collide.
        expect($this->evaluator->evaluate($this->challenge, ['answer' => 'B'])->correct)->toBeFalse()
            ->and($this->evaluator->evaluate($this->challenge, ['answer' => ' b'])->correct)->toBeFalse();
    });

    it('is deterministic', function () {
        $first = $this->evaluator->evaluate($this->challenge, ['answer' => 'b']);
        $second = $this->evaluator->evaluate($this->challenge, ['answer' => 'b']);

        expect($first->toArray())->toBe($second->toArray());
    });

    it('constrains a submission to the actual options', function () {
        expect($this->evaluator->submissionRules($this->challenge)['answer'])
            ->toContain('in:a,b');
    });

    it('returns an EvaluationResult', function () {
        expect($this->evaluator->evaluate($this->challenge, ['answer' => 'b']))
            ->toBeInstanceOf(EvaluationResult::class);
    });
});

describe('the whole loop', function () {
    beforeEach(function () {
        $this->seed(ExperienceSeeder::class);
        $this->seed(CursedCodeSeeder::class);

        $this->user = User::factory()->create();
        $this->challenge = Challenge::query()->firstWhere('slug', 'php-float-equality');
    });

    it('seeds at least five playable challenges', function () {
        // A fresh clone has to be worth opening (§70 seed targets).
        $experience = Experience::query()->firstWhere('slug', 'cursed-code');

        expect($experience->challenges()->published()->count())->toBeGreaterThanOrEqual(5);
    });

    it('seeds only challenges that pass their own validator', function () {
        $validator = app(CursedCodeConfiguration::class);
        $experience = Experience::query()->firstWhere('slug', 'cursed-code');

        foreach ($experience->challenges as $challenge) {
            expect($validator->problems($challenge))
                ->toBe([], "{$challenge->slug} does not satisfy the Cursed Code schema");
        }
    });

    it('plays through from start to XP', function () {
        $this->actingAs($this->user)
            ->post(route('attempts.store', $this->challenge))
            ->assertRedirect();

        $attempt = ChallengeAttempt::query()->open()->sole();

        $this->actingAs($this->user)
            ->post(route('attempts.submit', $attempt), [
                'submission' => ['answer' => 'b'],
            ])
            ->assertRedirect(route('attempts.show', $attempt));

        $attempt->refresh();

        expect($attempt->status)->toBe(ChallengeAttempt::STATUS_COMPLETED)
            ->and($attempt->score)->toBeGreaterThan(0)
            ->and(XpTransaction::query()->where('user_id', $this->user->id)->sum('amount'))
            ->toBe((int) config('devlab.xp.easy'))
            ->and($this->user->refresh()->statistic->challenges_completed)->toBe(1);
    });

    it('fails an attempt with the wrong answer but still explains it', function () {
        $this->actingAs($this->user)->post(route('attempts.store', $this->challenge));
        $attempt = ChallengeAttempt::query()->open()->sole();

        $this->actingAs($this->user)->post(route('attempts.submit', $attempt), [
            'submission' => ['answer' => 'a'],
        ]);

        expect($attempt->refresh()->status)->toBe(ChallengeAttempt::STATUS_FAILED);

        // Getting it wrong and learning why is the point.
        $this->actingAs($this->user)
            ->get(route('attempts.show', $attempt))
            ->assertInertia(fn ($page) => $page
                ->where('result.correct', false)
                ->whereNot('result.explanation', null)
            );
    });

    it('rejects a submission that is not one of the options', function () {
        $this->actingAs($this->user)->post(route('attempts.store', $this->challenge));
        $attempt = ChallengeAttempt::query()->open()->sole();

        $this->actingAs($this->user)
            ->post(route('attempts.submit', $attempt), ['submission' => ['answer' => 'zzz']])
            ->assertSessionHasErrors('submission.answer');

        expect($attempt->refresh()->isOpen())->toBeTrue();
    });

    it('never sends the answer key while playing', function () {
        $this->actingAs($this->user)->post(route('attempts.store', $this->challenge));
        $attempt = ChallengeAttempt::query()->open()->sole();

        $this->actingAs($this->user)
            ->get(route('attempts.show', $attempt))
            ->assertInertia(fn ($page) => $page
                ->missing('challenge.solution')
                ->where('result', null)
            );
    });

    it('never sends the answer key after completing either', function () {
        // The explanation is the reveal; the key itself is never sent at all.
        $this->actingAs($this->user)->post(route('attempts.store', $this->challenge));
        $attempt = ChallengeAttempt::query()->open()->sole();

        $this->actingAs($this->user)->post(route('attempts.submit', $attempt), [
            'submission' => ['answer' => 'b'],
        ]);

        $this->actingAs($this->user)
            ->get(route('attempts.show', $attempt))
            ->assertInertia(fn ($page) => $page->missing('challenge.solution'));
    });

    it('is reachable from the Bored button', function () {
        // An experience is not shipped until the button can actually hand it to
        // you (§10). Dev Roulette reveals the assignment rather than redirecting,
        // so this asserts the revealed challenge is a real published one.
        $slugs = Challenge::query()->published()->pluck('slug')->all();

        $this->get(route('bored'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('roulette/index')
                ->where('assignment.slug', fn ($slug) => in_array($slug, $slugs, true))
            );
    });
});
