<?php

declare(strict_types=1);

use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\Experience;
use App\Models\User;
use App\Services\Challenge\GitSimulator\GitRepository;
use App\Services\Challenge\GitSimulator\GitSimulation;
use App\Services\Challenge\GitSimulator\GitSimulatorConfiguration;
use App\Services\Challenge\GitSimulator\GitSimulatorEvaluator;
use Database\Seeders\ExperienceSeeder;
use Database\Seeders\GitSimulatorSeeder;

/*
 * The PHP model is the authority (ADR 0006), which makes these tests the
 * specification of what the commands do. The TypeScript model in the browser is
 * written against the same contract document and decides nothing.
 */

/**
 * main at a2, feature at b2, forked from a1. HEAD on main.
 */
function forkedRepository(): GitRepository
{
    return GitRepository::fromState(
        [
            ['id' => 'a1', 'message' => 'Initial commit', 'parents' => []],
            ['id' => 'a2', 'message' => 'Second on main', 'parents' => ['a1']],
            ['id' => 'b1', 'message' => 'First on feature', 'parents' => ['a1']],
            ['id' => 'b2', 'message' => 'Second on feature', 'parents' => ['b1']],
        ],
        ['main' => 'a2', 'feature' => 'b2'],
        'main',
    );
}

function simulation(): GitSimulation
{
    return app(GitSimulation::class);
}

/**
 * @param  array<string, mixed>  $configuration
 * @param  array<string, mixed>  $solution
 */
function gitChallenge(array $configuration = [], array $solution = []): Challenge
{
    $experience = Experience::query()->firstWhere('slug', 'git-simulator')
        ?? Experience::factory()->published()->create(['slug' => 'git-simulator']);

    return Challenge::factory()->published()->for($experience)->make([
        'configuration' => [
            'goal' => 'Get feature into main.',
            'repository' => [
                'commits' => [
                    ['id' => 'a1', 'message' => 'Initial commit', 'parents' => []],
                    ['id' => 'a2', 'message' => 'Second on main', 'parents' => ['a1']],
                    ['id' => 'b1', 'message' => 'First on feature', 'parents' => ['a1']],
                    ['id' => 'b2', 'message' => 'Second on feature', 'parents' => ['b1']],
                ],
                'branches' => ['main' => 'a2', 'feature' => 'b2'],
                'head' => 'main',
            ],
            ...$configuration,
        ],
        'solution' => [
            'commands' => ['git merge feature'],
            'summary' => 'Merge it.',
            ...$solution,
        ],
    ]);
}

describe('the command model', function () {
    it('commits onto the current branch and moves it', function () {
        $repository = forkedRepository();

        expect(simulation()->apply($repository, 'git commit -m "Third on main"'))->toBeNull();

        $head = $repository->head();

        expect($repository->commits()[$head]['message'])->toBe('Third on main')
            ->and($repository->commits()[$head]['parents'])->toBe(['a2'])
            ->and($repository->branches()['main'])->toBe($head);
    });

    it('keeps a quoted commit message whole', function () {
        /*
         * The message is part of a commit's fingerprint, so a message that loses
         * its spaces is a wrong answer with no visible cause.
         */
        $repository = forkedRepository();

        simulation()->apply($repository, 'git commit -m "fix the thing properly"');

        expect($repository->commits()[$repository->head()]['message'])
            ->toBe('fix the thing properly');
    });

    it('fast-forwards a merge when nothing has diverged', function () {
        // main is an ancestor of feature here, so there is nothing to reconcile
        // and a merge commit would teach the wrong shape.
        $repository = GitRepository::fromState(
            [
                ['id' => 'a1', 'message' => 'Initial commit', 'parents' => []],
                ['id' => 'b1', 'message' => 'On feature', 'parents' => ['a1']],
            ],
            ['main' => 'a1', 'feature' => 'b1'],
            'main',
        );

        expect(simulation()->apply($repository, 'git merge feature'))->toBeNull()
            ->and($repository->branches()['main'])->toBe('b1')
            // No new commit was created.
            ->and($repository->commits())->toHaveCount(2);
    });

    it('creates a merge commit with two parents when histories have diverged', function () {
        $repository = forkedRepository();

        simulation()->apply($repository, 'git merge feature');

        $head = $repository->head();

        expect($repository->commits()[$head]['parents'])->toBe(['a2', 'b2'])
            ->and($repository->commits()[$head]['message'])->toBe("Merge branch 'feature'");
    });

    it('refuses a merge of something already in the history', function () {
        $repository = forkedRepository();

        simulation()->apply($repository, 'git checkout feature');

        expect(simulation()->apply($repository, 'git merge a1'))
            ->toBe("Already up to date: 'a1' is already in this history.");
    });

    it('detaches HEAD when checking out a commit', function () {
        $repository = forkedRepository();

        expect(simulation()->apply($repository, 'git checkout b1'))->toBeNull()
            ->and($repository->isDetached())->toBeTrue()
            ->and($repository->head())->toBe('b1');
    });

    it('commits onto a detached HEAD without moving any branch', function () {
        // The failure mode the detached-head challenge is about: the work is
        // real, and nothing refers to it.
        $repository = forkedRepository();

        simulation()->apply($repository, 'git checkout b1');
        simulation()->apply($repository, 'git commit -m "Work in the dark"');

        expect($repository->branches())->toBe(['main' => 'a2', 'feature' => 'b2'])
            ->and($repository->head())->not->toBe('b2');
    });

    it('reverts forwards rather than moving anything backwards', function () {
        $repository = forkedRepository();

        simulation()->apply($repository, 'git revert a2');

        $head = $repository->head();

        expect($repository->commits()[$head]['message'])->toBe('Revert "Second on main"')
            ->and($repository->commits()[$head]['parents'])->toBe(['a2'])
            ->and($repository->hasCommit('a2'))->toBeTrue();
    });

    it('cherry-picks a copy and leaves the original where it is', function () {
        $repository = forkedRepository();

        simulation()->apply($repository, 'git cherry-pick b2');

        $head = $repository->head();

        expect($repository->commits()[$head]['message'])->toBe('Second on feature')
            ->and($repository->commits()[$head]['parents'])->toBe(['a2'])
            // The original is untouched, which is the part people expect to be
            // otherwise: cherry-pick duplicates, it does not move.
            ->and($repository->branches()['feature'])->toBe('b2');
    });

    it('rebases by replaying the unique commits, oldest first', function () {
        $repository = forkedRepository();

        simulation()->apply($repository, 'git checkout feature');
        expect(simulation()->apply($repository, 'git rebase main'))->toBeNull();

        $head = $repository->head();
        $second = $repository->commits()[$head];
        $first = $repository->commits()[$second['parents'][0]];

        expect($second['message'])->toBe('Second on feature')
            ->and($first['message'])->toBe('First on feature')
            // Replayed onto main's tip, in the original order.
            ->and($first['parents'])->toBe(['a2']);
    });

    it('refuses to rebase a history containing a merge', function () {
        // Where real Git starts asking questions. Refusing is more honest than
        // inventing an answer (ADR 0006).
        $repository = forkedRepository();

        simulation()->apply($repository, 'git checkout feature');
        simulation()->apply($repository, 'git merge main');
        simulation()->apply($repository, 'git commit -m "After the merge"');
        simulation()->apply($repository, 'git checkout main');
        simulation()->apply($repository, 'git commit -m "Moved on"');
        simulation()->apply($repository, 'git checkout feature');

        expect(simulation()->apply($repository, 'git rebase main'))
            ->toBe('This simulator cannot rebase a history that contains a merge.');
    });

    it('will not delete the branch you are standing on', function () {
        $repository = forkedRepository();

        expect(simulation()->apply($repository, 'git branch -d main'))
            ->toBe("Cannot delete 'main': it is the branch you are on.");
    });

    it('refuses a command it does not implement, by name', function () {
        expect(simulation()->apply(forkedRepository(), 'git bisect start'))
            ->toBe("This simulator does not implement 'git bisect'.");
    });

    it('refuses a command the challenge does not allow', function () {
        expect(simulation()->apply(forkedRepository(), 'git reset a1', ['merge', 'checkout']))
            ->toBe("This challenge does not allow 'git reset'.");
    });

    it('stops replaying at the first command that will not apply', function () {
        /*
         * Git is stateful: every command after a failed one was typed against a
         * repository that never existed, so grading them would grade a history
         * the player never saw.
         */
        $result = simulation()->run(forkedRepository(), [
            'git checkout feature',
            'git checkout nowhere',
            'git commit -m "Never happened"',
        ]);

        expect($result['applied'])->toBe(1)
            ->and($result['error'])->toBe("There is no branch or commit called 'nowhere'.")
            ->and($result['repository']->commits())->toHaveCount(4);
    });
});

describe('the fingerprint', function () {
    it('matches two different routes to the same history', function () {
        /*
         * The reason comparison is structural. There is more than one way to
         * solve most Git problems, and an evaluator that accepted only the
         * author's route would be grading typing (ADR 0006).
         */
        $viaTwoSteps = forkedRepository();
        simulation()->run($viaTwoSteps, ['git checkout b1', 'git branch rescue']);

        $viaOneStep = forkedRepository();
        simulation()->run($viaOneStep, ['git checkout -b rescue b1']);

        // Not identical routes, so not necessarily identical states — the
        // one-step form leaves HEAD on the new branch.
        expect($viaTwoSteps->fingerprint())->not->toBe($viaOneStep->fingerprint());

        $matching = forkedRepository();
        simulation()->run($matching, ['git checkout b1', 'git branch rescue', 'git checkout rescue']);

        expect($matching->fingerprint())->toBe($viaOneStep->fingerprint());
    });

    it('ignores the ids a particular replay happened to allocate', function () {
        $first = forkedRepository();
        simulation()->run($first, ['git commit -m "Same work"']);

        $second = forkedRepository();
        simulation()->run($second, ['git commit -m "Throwaway"', 'git reset a2', 'git commit -m "Same work"']);

        // Different ids, different sequence numbers, same shape and messages.
        expect($first->fingerprint())->toBe($second->fingerprint());
    });

    it('tells being on a branch apart from being detached at the same commit', function () {
        // That distinction is the lesson in more than one challenge, so it has
        // to survive into the comparison.
        $onBranch = forkedRepository();
        simulation()->run($onBranch, ['git checkout feature']);

        $detached = forkedRepository();
        simulation()->run($detached, ['git checkout b2']);

        expect($onBranch->fingerprint())->not->toBe($detached->fingerprint());
    });

    it('does not care which order a merge recorded its parents in', function () {
        /*
         * Sorted parents, so a merge commit built as (main, feature) matches one
         * built as (feature, main). Real Git treats the first parent as special;
         * nothing in this model depends on which side you were standing on.
         *
         * Note this is NOT the same as merging from the other branch, which
         * produces a different MESSAGE — "Merge branch 'feature'" against
         * "Merge branch 'main'" — and is therefore genuinely a different history.
         */
        $left = GitRepository::fromState(
            [
                ['id' => 'a1', 'message' => 'Initial commit', 'parents' => []],
                ['id' => 'a2', 'message' => 'On main', 'parents' => ['a1']],
                ['id' => 'b1', 'message' => 'On feature', 'parents' => ['a1']],
                ['id' => 'm1', 'message' => 'Merge', 'parents' => ['a2', 'b1']],
            ],
            ['main' => 'm1'],
            'main',
        );

        $right = GitRepository::fromState(
            [
                ['id' => 'a1', 'message' => 'Initial commit', 'parents' => []],
                ['id' => 'a2', 'message' => 'On main', 'parents' => ['a1']],
                ['id' => 'b1', 'message' => 'On feature', 'parents' => ['a1']],
                ['id' => 'm1', 'message' => 'Merge', 'parents' => ['b1', 'a2']],
            ],
            ['main' => 'm1'],
            'main',
        );

        expect($left->fingerprint())->toBe($right->fingerprint());
    });
});

describe('the configuration validator', function () {
    it('accepts a challenge in the documented shape', function () {
        expect(app(GitSimulatorConfiguration::class)->problems(gitChallenge()))->toBe([]);
    });

    it('refuses a solution that does not apply', function () {
        /*
         * The goal IS the replayed solution, so a solution that fails describes
         * no reachable state — every attempt would be compared against the
         * starting position instead.
         */
        expect(app(GitSimulatorConfiguration::class)->problems(
            gitChallenge(solution: ['commands' => ['git checkout nowhere']])
        ))->toContain("The solution does not apply: 'git checkout nowhere' — There is no branch or commit called 'nowhere'.");
    });

    it('refuses a solution that changes nothing', function () {
        // The inverse of an unsatisfiable rubric: every attempt succeeds without
        // doing anything, and nothing reports an error.
        expect(app(GitSimulatorConfiguration::class)->problems(
            gitChallenge(solution: ['commands' => ['git checkout main']])
        ))->toContain('The solution leaves the repository exactly as it started, so the challenge is already solved.');
    });

    it('refuses a solution using a command the challenge forbids', function () {
        expect(app(GitSimulatorConfiguration::class)->problems(
            gitChallenge(configuration: ['allowed' => ['rebase']], solution: ['commands' => ['git merge feature']])
        ))->toContain("The solution does not apply: 'git merge feature' — This challenge does not allow 'git merge'.");
    });

    it('refuses a branch pointing at a commit that does not exist', function () {
        expect(app(GitSimulatorConfiguration::class)->problems(gitChallenge(configuration: [
            'repository' => [
                'commits' => [['id' => 'a1', 'message' => 'Initial commit', 'parents' => []]],
                'branches' => ['main' => 'a1', 'feature' => 'zz'],
                'head' => 'main',
            ],
        ])))->toContain("Branch 'feature' points at 'zz', which is not a commit in this repository.");
    });

    it('refuses a starting commit id reserved for replayed commits', function () {
        /*
         * Replayed commits are named n1, n2, ... — an author reusing those would
         * have a player's first commit silently overwrite one already there.
         */
        expect(app(GitSimulatorConfiguration::class)->problems(gitChallenge(configuration: [
            'repository' => [
                'commits' => [['id' => 'n1', 'message' => 'Initial commit', 'parents' => []]],
                'branches' => ['main' => 'n1'],
                'head' => 'main',
            ],
        ])))->toContain("Commit id 'n1' is reserved: replayed commits are named n1, n2 and so on.");
    });
});

describe('the evaluator', function () {
    it('accepts the author\'s own solution', function () {
        $result = app(GitSimulatorEvaluator::class)
            ->evaluate(gitChallenge(), ['commands' => ['git merge feature']]);

        expect($result->correct)->toBeTrue()
            ->and($result->accuracy)->toBe(1.0);
    });

    it('reports the command Git would have refused, and why', function () {
        $result = app(GitSimulatorEvaluator::class)
            ->evaluate(gitChallenge(), ['commands' => ['git checkout nowhere']]);

        expect($result->correct)->toBeFalse()
            ->and($result->feedback)->toContain('would not apply')
            ->and($result->feedback)->toContain("no branch or commit called 'nowhere'")
            ->and($result->details['applied'])->toBe(0);
    });

    it('says the history is the wrong shape without saying what would fix it', function () {
        $result = app(GitSimulatorEvaluator::class)
            ->evaluate(gitChallenge(), ['commands' => ['git commit -m "Not this"']]);

        expect($result->correct)->toBeFalse()
            ->and($result->feedback)->toContain('main')
            ->and($result->feedback)->not->toContain('merge feature');
    });

    it('separates a right history from a wrong HEAD', function () {
        $challenge = gitChallenge(solution: ['commands' => ['git checkout feature']]);

        $result = app(GitSimulatorEvaluator::class)
            ->evaluate($challenge, ['commands' => ['git checkout b2']]);

        expect($result->correct)->toBeFalse()
            ->and($result->feedback)->toBe('The history is right, but HEAD is not where it should be.');
    });

    it('refuses an empty submission', function () {
        $result = app(GitSimulatorEvaluator::class)->evaluate(gitChallenge(), ['commands' => []]);

        expect($result->correct)->toBeFalse()
            ->and($result->details['reason'])->toBe('empty');
    });

    it('bounds how much work one submission can ask for', function () {
        $rules = app(GitSimulatorEvaluator::class)->submissionRules(gitChallenge());

        expect($rules['commands'])->toContain('max:'.GitSimulation::MAX_COMMANDS);
    });
});

describe('the seeded content', function () {
    beforeEach(function () {
        $this->seed(ExperienceSeeder::class);
        $this->seed(GitSimulatorSeeder::class);
    });

    it('is all valid against the contract', function () {
        /*
         * This replays every author's own solution: a solution that does not
         * apply, or that changes nothing, fails here rather than showing up as
         * an unexplained success rate months later.
         */
        $validator = app(GitSimulatorConfiguration::class);

        $challenges = Challenge::query()
            ->whereRelation('experience', 'slug', 'git-simulator')
            ->get();

        expect($challenges)->toHaveCount(6);

        foreach ($challenges as $challenge) {
            expect($validator->problems($challenge))->toBe([], "{$challenge->slug} is invalid");
        }
    });

    it('accepts checkout -b as an alternative route on the detached-head challenge', function () {
        // The explanation tells players this works. It had better.
        $challenge = Challenge::query()
            ->whereRelation('experience', 'slug', 'git-simulator')
            ->where('slug', 'detached-head-rescue')
            ->sole();

        $result = app(GitSimulatorEvaluator::class)
            ->evaluate($challenge, ['commands' => ['git checkout -b rescue']]);

        expect($result->correct)->toBeTrue();
    });

    it('can be played end to end', function () {
        $user = User::factory()->create();
        $challenge = Challenge::query()
            ->whereRelation('experience', 'slug', 'git-simulator')
            ->where('slug', 'revert-not-reset')
            ->sole();

        $this->actingAs($user)->post(route('attempts.store', $challenge))->assertRedirect();

        $attempt = ChallengeAttempt::query()->open()->sole();

        $this->actingAs($user)
            ->post(route('attempts.submit', $attempt), [
                'submission' => ['commands' => $challenge->solution['commands']],
            ])
            ->assertRedirect(route('attempts.show', $attempt));

        expect($attempt->refresh()->status)->toBe(ChallengeAttempt::STATUS_COMPLETED);
    });

    it('never sends the solution to an open attempt', function () {
        // The browser has a Git model and no idea what the goal graph is.
        $user = User::factory()->create();
        $challenge = Challenge::query()
            ->whereRelation('experience', 'slug', 'git-simulator')
            ->where('slug', 'revert-not-reset')
            ->sole();

        $this->actingAs($user)->post(route('attempts.store', $challenge));
        $attempt = ChallengeAttempt::query()->open()->sole();

        $response = $this->actingAs($user)->get(route('attempts.show', $attempt))->assertOk();

        $props = json_encode($response->viewData('page')['props']);

        expect($props)->not->toContain('git revert')
            ->and($props)->not->toContain('solution');
    });
});
