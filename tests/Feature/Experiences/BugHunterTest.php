<?php

declare(strict_types=1);

use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\Experience;
use App\Models\User;
use App\Models\XpTransaction;
use App\Services\Challenge\BugHunter\BugHunterConfiguration;
use App\Services\Challenge\BugHunter\BugHunterEvaluator;
use Database\Seeders\BugHunterSeeder;
use Database\Seeders\ExperienceSeeder;

/**
 * @param  array<string, mixed>  $configuration
 * @param  array<string, mixed>  $solution
 */
function buggyChallenge(array $configuration = [], array $solution = ['lines' => [2]]): Challenge
{
    $experience = Experience::query()->firstWhere('slug', 'bug-hunter')
        ?? Experience::factory()->published()->create(['slug' => 'bug-hunter']);

    return Challenge::factory()->published()->for($experience)->create([
        'configuration' => [
            'language' => 'php',
            'mode' => 'locate',
            'context' => 'Adds two numbers.',
            'snippet' => "function add(int \$a, int \$b): int\n{\n    return \$a - \$b;\n}",
            ...$configuration,
        ],
        'solution' => $solution,
    ]);
}

describe('configuration validation', function () {
    it('accepts a well-formed challenge', function () {
        expect(app(BugHunterConfiguration::class)->problems(buggyChallenge(solution: ['lines' => [3]])))
            ->toBe([]);
    });

    it('rejects a solution line past the end of the snippet', function () {
        /*
         * The dangerous one, and the reason this class exists. Such a challenge
         * is unsolvable: every attempt fails, and the difficulty calibration
         * built on that success rate is quietly wrong.
         */
        expect(app(BugHunterConfiguration::class)->problems(buggyChallenge(solution: ['lines' => [99]])))
            ->toContain('Solution line 99 is beyond the end of the snippet (4 lines).');
    });

    it('rejects duplicate solution lines', function () {
        expect(app(BugHunterConfiguration::class)->problems(buggyChallenge(solution: ['lines' => [3, 3]])))
            ->toContain('Solution lines must be unique.');
    });

    it('rejects a snippet too short to hide anything', function () {
        $challenge = buggyChallenge(['snippet' => "one\ntwo"], ['lines' => [1]]);

        expect(app(BugHunterConfiguration::class)->problems($challenge))
            ->toContain('The snippet must be at least 3 lines long.');
    });

    it('rejects a mode that does not exist yet', function () {
        // "fix" needs the Phase 3 sandbox; accepting it now would publish a
        // challenge nothing can grade.
        expect(app(BugHunterConfiguration::class)->isValid(buggyChallenge(['mode' => 'fix'])))
            ->toBeFalse();
    });

    it('counts lines the same way whatever wrote the snippet', function () {
        // A snippet authored on Windows must not number differently from the
        // same snippet authored on Linux, or every line answer shifts.
        $unix = buggyChallenge(['snippet' => "a\nb\nc"], ['lines' => [1]]);
        $windows = buggyChallenge(['snippet' => "a\r\nb\r\nc"], ['lines' => [1]]);

        $configuration = app(BugHunterConfiguration::class);

        expect($configuration->lineCount($unix))->toBe(3)
            ->and($configuration->lineCount($windows))->toBe(3);
    });
});

describe('evaluation', function () {
    beforeEach(function () {
        $this->evaluator = app(BugHunterEvaluator::class);
        $this->challenge = buggyChallenge(solution: ['lines' => [3]]);
    });

    it('accepts the guilty line', function () {
        $result = $this->evaluator->evaluate($this->challenge, ['line' => 3]);

        expect($result->correct)->toBeTrue()
            ->and($result->accuracy)->toBe(1.0);
    });

    it('rejects an innocent line', function () {
        $result = $this->evaluator->evaluate($this->challenge, ['line' => 1]);

        expect($result->correct)->toBeFalse()
            ->and($result->accuracy)->toBe(0.0);
    });

    it('gives no hint about how close the guess was', function () {
        // Telling a player they are warm turns debugging into a guessing game.
        $result = $this->evaluator->evaluate($this->challenge, ['line' => 2]);

        expect($result->feedback)->not->toContain('3')
            ->and($result->feedback)->not->toContain('close');
    });

    it('accepts any line of a defect that spans several', function () {
        // A player who found the defect should not lose for picking its other half.
        $challenge = buggyChallenge(solution: ['lines' => [2, 3]]);

        expect($this->evaluator->evaluate($challenge, ['line' => 2])->correct)->toBeTrue()
            ->and($this->evaluator->evaluate($challenge, ['line' => 3])->correct)->toBeTrue();
    });

    it('treats a line beyond the snippet as wrong, not as an error', function () {
        $result = $this->evaluator->evaluate($this->challenge, ['line' => 999]);

        expect($result->correct)->toBeFalse()
            ->and($result->details['reason'])->toBe('out_of_range');
    });

    it('refuses a submission that is not a line at all', function () {
        expect($this->evaluator->evaluate($this->challenge, ['line' => 'the third one'])->details['reason'])
            ->toBe('not_a_line');
    });

    it('accepts a numeric string, because that is what a form posts', function () {
        expect($this->evaluator->evaluate($this->challenge, ['line' => '3'])->correct)->toBeTrue();
    });

    it('bounds a submission by the snippet length', function () {
        expect($this->evaluator->submissionRules($this->challenge)['line'])->toContain('max:4');
    });

    it('is deterministic', function () {
        expect($this->evaluator->evaluate($this->challenge, ['line' => 3])->toArray())
            ->toBe($this->evaluator->evaluate($this->challenge, ['line' => 3])->toArray());
    });
});

describe('the whole loop', function () {
    beforeEach(function () {
        $this->seed(ExperienceSeeder::class);
        $this->seed(BugHunterSeeder::class);

        $this->user = User::factory()->create();
        $this->challenge = Challenge::query()->firstWhere('slug', 'php-average-off-by-one');
    });

    it('seeds at least five playable challenges', function () {
        $experience = Experience::query()->firstWhere('slug', 'bug-hunter');

        expect($experience->challenges()->published()->count())->toBeGreaterThanOrEqual(5);
    });

    it('seeds only challenges that pass their own validator', function () {
        $validator = app(BugHunterConfiguration::class);
        $experience = Experience::query()->firstWhere('slug', 'bug-hunter');

        foreach ($experience->challenges as $challenge) {
            expect($validator->problems($challenge))
                ->toBe([], "{$challenge->slug} does not satisfy the Bug Hunter schema");
        }
    });

    it('points every seeded answer at a line that is actually there', function () {
        /*
         * The failure mode this guards is drift: someone edits a snippet, every
         * line below the edit shifts, and the recorded answer silently points at
         * the wrong code. The validator catches an out-of-range line; this
         * catches a line that exists but is blank, which is never a defect.
         */
        $configuration = app(BugHunterConfiguration::class);
        $experience = Experience::query()->firstWhere('slug', 'bug-hunter');

        foreach ($experience->challenges as $challenge) {
            $lines = $configuration->lines($challenge);

            foreach ($challenge->solution['lines'] as $line) {
                expect(trim($lines[$line - 1]))
                    ->not->toBe('', "{$challenge->slug} line {$line} is blank");
            }
        }
    });

    it('plays through from start to XP', function () {
        $this->actingAs($this->user)->post(route('attempts.store', $this->challenge));

        $attempt = ChallengeAttempt::query()->open()->sole();

        $this->actingAs($this->user)
            ->post(route('attempts.submit', $attempt), ['submission' => ['line' => 5]])
            ->assertRedirect(route('attempts.show', $attempt));

        $attempt->refresh();

        expect($attempt->status)->toBe(ChallengeAttempt::STATUS_COMPLETED)
            ->and($attempt->score)->toBeGreaterThan(0)
            ->and(XpTransaction::query()->where('user_id', $this->user->id)->sum('amount'))
            ->toBe((int) config('devlab.xp.easy'));
    });

    it('fails a wrong line but still explains the defect', function () {
        $this->actingAs($this->user)->post(route('attempts.store', $this->challenge));
        $attempt = ChallengeAttempt::query()->open()->sole();

        $this->actingAs($this->user)
            ->post(route('attempts.submit', $attempt), ['submission' => ['line' => 3]]);

        expect($attempt->refresh()->status)->toBe(ChallengeAttempt::STATUS_FAILED);

        $this->actingAs($this->user)
            ->get(route('attempts.show', $attempt))
            ->assertInertia(fn ($page) => $page
                ->where('result.correct', false)
                ->whereNot('result.explanation', null)
            );
    });

    it('never sends the defect location, before or after', function () {
        $this->actingAs($this->user)->post(route('attempts.store', $this->challenge));
        $attempt = ChallengeAttempt::query()->open()->sole();

        $this->actingAs($this->user)
            ->get(route('attempts.show', $attempt))
            ->assertInertia(fn ($page) => $page->missing('challenge.solution'));

        $this->actingAs($this->user)
            ->post(route('attempts.submit', $attempt), ['submission' => ['line' => 5]]);

        $this->actingAs($this->user)
            ->get(route('attempts.show', $attempt))
            ->assertInertia(fn ($page) => $page->missing('challenge.solution'));
    });

    it('refuses a line outside the snippet at validation', function () {
        $this->actingAs($this->user)->post(route('attempts.store', $this->challenge));
        $attempt = ChallengeAttempt::query()->open()->sole();

        $this->actingAs($this->user)
            ->post(route('attempts.submit', $attempt), ['submission' => ['line' => 9999]])
            ->assertSessionHasErrors('submission.line');

        expect($attempt->refresh()->isOpen())->toBeTrue();
    });
});
