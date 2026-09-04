<?php

namespace Database\Seeders;

use App\Models\Challenge;
use App\Models\Experience;
use Illuminate\Database\Seeder;

/**
 * Seed content for Git Simulator.
 *
 * Each challenge states a goal in terms of the FINISHED repository rather than
 * the command that gets there — "get feature's work onto main without a merge
 * commit" rather than "rebase feature onto main" — because naming the command
 * is writing the answer into the question.
 *
 * `allowed` narrows the command set where the challenge is about one operation.
 * A challenge about revert that also permits reset is a challenge about
 * whichever the player reaches for first.
 *
 * The goal is defined by replaying `solution.commands`, and a test replays them
 * through the validator: a solution that does not apply describes no reachable
 * state, and one that changes nothing makes the challenge already solved (§70).
 *
 * Idempotent by slug. Changing a starting graph or a solution must bump `version`.
 */
class GitSimulatorSeeder extends Seeder
{
    public function run(): void
    {
        $experience = Experience::query()->where('slug', 'git-simulator')->first();

        if ($experience === null) {
            return;
        }

        foreach ($this->challenges() as $challenge) {
            Challenge::query()->updateOrCreate(
                ['slug' => $challenge['slug']],
                [...$challenge, 'experience_id' => $experience->id],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function challenges(): array
    {
        return [
            [
                'slug' => 'rebase-instead-of-merge',
                'title' => 'A straight line, please',
                'description' => 'Two commits on a branch, and a reviewer who does not want a merge commit.',
                'objective' => 'Put the branch work on top of main, keeping history linear.',
                'difficulty' => 'easy',
                'type' => 'repository',
                'points' => 100,
                'estimated_minutes' => 6,
                'tags' => ['git', 'rebase'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'goal' => 'The work on feature should sit directly on top of main, with no '
                        .'merge commit anywhere in the history. main itself has moved on since '
                        .'feature was branched.',
                    'repository' => [
                        'commits' => [
                            ['id' => 'a1', 'message' => 'Initial commit', 'parents' => []],
                            ['id' => 'a2', 'message' => 'Add the router', 'parents' => ['a1']],
                            ['id' => 'a3', 'message' => 'Update the changelog', 'parents' => ['a2']],
                            ['id' => 'b1', 'message' => 'Add login form', 'parents' => ['a2']],
                            ['id' => 'b2', 'message' => 'Validate the login form', 'parents' => ['b1']],
                        ],
                        'branches' => ['main' => 'a3', 'feature' => 'b2'],
                        'head' => 'main',
                    ],
                    'allowed' => ['checkout', 'switch', 'rebase'],
                ],
                'solution' => [
                    'commands' => ['git checkout feature', 'git rebase main'],
                    'summary' => 'Rebase replays the branch commits on top of main.',
                ],
                'explanation' => 'Merging here would work and would record that the two lines of '
                    .'work happened separately — which is exactly what the reviewer does not want. '
                    .'Rebase replays feature\'s commits on top of main, producing new commits with '
                    .'the same messages and a single line of history. Note that you must be ON '
                    .'feature to rebase it: rebasing while on main would move main instead.',
            ],
            [
                'slug' => 'revert-not-reset',
                'title' => 'It is already pushed',
                'description' => 'A bad commit is on a branch three other people have pulled.',
                'objective' => 'Undo the change without rewriting shared history.',
                'difficulty' => 'easy',
                'type' => 'repository',
                'points' => 100,
                'estimated_minutes' => 5,
                'tags' => ['git', 'revert', 'history'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'goal' => 'Undo the effect of "Drop the users table" while leaving every '
                        .'existing commit exactly where it is. Three colleagues have already pulled '
                        .'this branch.',
                    'repository' => [
                        'commits' => [
                            ['id' => 'a1', 'message' => 'Initial commit', 'parents' => []],
                            ['id' => 'a2', 'message' => 'Add the users table', 'parents' => ['a1']],
                            ['id' => 'a3', 'message' => 'Drop the users table', 'parents' => ['a2']],
                            ['id' => 'a4', 'message' => 'Add the reports page', 'parents' => ['a3']],
                        ],
                        'branches' => ['main' => 'a4'],
                        'head' => 'main',
                    ],
                    'allowed' => ['revert'],
                ],
                'solution' => [
                    'commands' => ['git revert a3'],
                    'summary' => 'Revert adds a commit undoing the change, leaving history intact.',
                ],
                'explanation' => 'Reset would move the branch pointer backwards and discard the two '
                    .'commits after the bad one — on a branch other people have pulled, that means '
                    .'their next push reintroduces everything you removed. Revert goes forwards: a '
                    .'new commit that undoes the change, with the original still in the history '
                    .'where everybody else already has it. This challenge only permits revert, '
                    .'because the moment reset is available the interesting decision disappears.',
            ],
            [
                'slug' => 'detached-head-rescue',
                'title' => 'Work on no branch at all',
                'description' => 'Two good commits, and HEAD is not pointing at anything that will keep them.',
                'objective' => 'Get the work onto a branch before it is lost.',
                'difficulty' => 'medium',
                'type' => 'repository',
                'points' => 150,
                'estimated_minutes' => 8,
                'tags' => ['git', 'detached-head', 'branching'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'goal' => 'The two commits made while HEAD was detached must end up on a branch '
                        .'called rescue, and main must be left exactly where it is.',
                    'repository' => [
                        'commits' => [
                            ['id' => 'a1', 'message' => 'Initial commit', 'parents' => []],
                            ['id' => 'a2', 'message' => 'Add the parser', 'parents' => ['a1']],
                            ['id' => 'd1', 'message' => 'Fix the parser edge case', 'parents' => ['a2']],
                            ['id' => 'd2', 'message' => 'Add a test for the edge case', 'parents' => ['d1']],
                        ],
                        'branches' => ['main' => 'a2'],
                        'head' => 'd2',
                    ],
                    'allowed' => ['branch', 'checkout', 'switch'],
                ],
                'solution' => [
                    'commands' => ['git branch rescue', 'git checkout rescue'],
                    'summary' => 'A branch at the detached commit gives the work a name that survives.',
                ],
                'explanation' => 'A detached HEAD is a commit with no branch pointing at it. The '
                    .'commits are not lost — nothing in Git is lost until it is garbage collected — '
                    .'but nothing refers to them, so the next checkout leaves them unreachable. '
                    .'Creating a branch where you are standing gives them a name. `git checkout -b '
                    .'rescue` does both steps at once and reaches the same repository, which is why '
                    .'the evaluator compares shape rather than commands.',
            ],
            [
                'slug' => 'cherry-pick-one-commit',
                'title' => 'One fix, out of a branch that is not ready',
                'description' => 'The hotfix is on a feature branch with four other commits nobody wants yet.',
                'objective' => 'Get exactly one commit onto main.',
                'difficulty' => 'medium',
                'type' => 'repository',
                'points' => 150,
                'estimated_minutes' => 7,
                'tags' => ['git', 'cherry-pick'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'goal' => 'main must gain the fix in commit b2 and nothing else from feature. '
                        .'feature itself must be left untouched.',
                    'repository' => [
                        'commits' => [
                            ['id' => 'a1', 'message' => 'Initial commit', 'parents' => []],
                            ['id' => 'a2', 'message' => 'Add the checkout page', 'parents' => ['a1']],
                            ['id' => 'b1', 'message' => 'Start the redesign', 'parents' => ['a2']],
                            ['id' => 'b2', 'message' => 'Fix the tax rounding', 'parents' => ['b1']],
                            ['id' => 'b3', 'message' => 'Continue the redesign', 'parents' => ['b2']],
                        ],
                        'branches' => ['main' => 'a2', 'feature' => 'b3'],
                        'head' => 'main',
                    ],
                    'allowed' => ['cherry-pick', 'checkout', 'switch'],
                ],
                'solution' => [
                    'commands' => ['git cherry-pick b2'],
                    'summary' => 'Cherry-pick copies one commit onto the current branch.',
                ],
                'explanation' => 'Merging feature would bring the unfinished redesign with it. '
                    .'Cherry-pick copies a single commit: same message, new commit, parented on '
                    .'main. The original stays exactly where it is on feature, which is the part '
                    .'people most often expect to be otherwise — cherry-pick does not move a '
                    .'commit, it duplicates one, and the same change now exists twice in the '
                    .'repository.',
            ],
            [
                'slug' => 'reset-the-accidental-commit',
                'title' => 'Committed to the wrong branch',
                'description' => 'Two commits that were meant for a feature branch are sitting on main.',
                'objective' => 'Move them off main without losing them.',
                'difficulty' => 'hard',
                'type' => 'repository',
                'points' => 200,
                'estimated_minutes' => 10,
                'tags' => ['git', 'reset', 'branching'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'goal' => 'The two commits after "Release 1.4" belong on a branch called '
                        .'feature. main must point back at "Release 1.4", and the work must still '
                        .'be reachable. Nothing has been pushed.',
                    'repository' => [
                        'commits' => [
                            ['id' => 'a1', 'message' => 'Initial commit', 'parents' => []],
                            ['id' => 'a2', 'message' => 'Release 1.4', 'parents' => ['a1']],
                            ['id' => 'a3', 'message' => 'Add the export button', 'parents' => ['a2']],
                            ['id' => 'a4', 'message' => 'Wire up the export job', 'parents' => ['a3']],
                        ],
                        'branches' => ['main' => 'a4'],
                        'head' => 'main',
                    ],
                    'allowed' => ['branch', 'checkout', 'switch', 'reset'],
                ],
                'solution' => [
                    'commands' => ['git branch feature', 'git reset a2'],
                    'summary' => 'Name the work first, then move main back — order matters.',
                ],
                'explanation' => 'The order is the whole challenge. Creating the branch first gives '
                    .'the two commits a second pointer, so moving main back leaves them reachable '
                    .'through feature. Reset first and main is the only thing that referred to '
                    .'them: the commits still exist, but you now need the reflog to find them, and '
                    .'this simulator does not have one. Nothing was pushed, which is what makes '
                    .'moving a branch pointer acceptable here at all.',
            ],
            [
                'slug' => 'merge-that-should-fast-forward',
                'title' => 'No merge commit needed',
                'description' => 'main has not moved since the branch was made.',
                'objective' => 'Bring the branch into main with no extra commit.',
                'difficulty' => 'easy',
                'type' => 'repository',
                'points' => 100,
                'estimated_minutes' => 5,
                'tags' => ['git', 'merge', 'fast-forward'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'goal' => 'main should include the two commits from feature, and the history '
                        .'must contain no merge commit. You are currently on feature.',
                    'repository' => [
                        'commits' => [
                            ['id' => 'a1', 'message' => 'Initial commit', 'parents' => []],
                            ['id' => 'a2', 'message' => 'Add the API client', 'parents' => ['a1']],
                            ['id' => 'b1', 'message' => 'Add retries', 'parents' => ['a2']],
                            ['id' => 'b2', 'message' => 'Add a backoff', 'parents' => ['b1']],
                        ],
                        'branches' => ['main' => 'a2', 'feature' => 'b2'],
                        'head' => 'feature',
                    ],
                    'allowed' => ['checkout', 'switch', 'merge'],
                ],
                'solution' => [
                    'commands' => ['git checkout main', 'git merge feature'],
                    'summary' => 'main is an ancestor of feature, so the merge fast-forwards.',
                ],
                'explanation' => 'main has not moved since feature branched, so main is an ancestor '
                    .'of feature and there is nothing to reconcile. Git moves the pointer forwards '
                    .'instead of creating a merge commit — a fast-forward. Merging from the wrong '
                    .'branch is the usual mistake here: running `git merge main` while still on '
                    .'feature reports that everything is already up to date, because it is.',
            ],
            [
                'slug' => 'revert-the-revert',
                'title' => 'Somebody reverted your feature',
                'description' => 'The revert was right at the time. It is not right now.',
                'objective' => 'Get the change back without removing anything.',
                'difficulty' => 'expert',
                'type' => 'repository',
                'points' => 500,
                'estimated_minutes' => 12,
                'tags' => ['git', 'revert', 'history'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'goal' => 'The export feature must be part of main again. Every commit already '
                        .'in the history must stay exactly where it is - this branch is shared, and '
                        .'the revert is part of the record of what happened.',
                    'repository' => [
                        'commits' => [
                            ['id' => 'a1', 'message' => 'Initial commit', 'parents' => []],
                            ['id' => 'a2', 'message' => 'Add the export feature', 'parents' => ['a1']],
                            ['id' => 'a3', 'message' => 'Revert \"Add the export feature\"', 'parents' => ['a2']],
                            ['id' => 'a4', 'message' => 'Fix the invoice total', 'parents' => ['a3']],
                        ],
                        'branches' => ['main' => 'a4'],
                        'head' => 'main',
                    ],
                    'allowed' => ['revert', 'checkout', 'switch'],
                ],
                'solution' => [
                    'commands' => ['git revert a3'],
                    'summary' => 'Revert the revert. The original commit is not the one to undo.',
                ],
                'explanation' => 'Revert the REVERT, not the original.'
                    ."\n\n"
                    .'The instinct is to reach for a2, the commit that added the feature - but a2 is '
                    .'already in the history and already applied. What removed the feature was a3, '
                    ."so a3 is what has to be undone.\n\n"
                    .'Reverting a2 would produce a commit undoing a change that is not currently in '
                    .'effect, which in real Git fails to apply and in this model is simply the wrong '
                    ."history.\n\n"
                    .'The reason this matters beyond the puzzle is what happens on merges. Git '
                    .'compares content, not intent, so once a feature branch has been merged and the '
                    .'merge reverted, re-merging that branch brings across NOTHING - Git sees the '
                    .'commits are already ancestors and concludes there is nothing to do, while the '
                    .'code is absent because the revert removed it. The fix is the same one: revert '
                    .'the revert, then continue.',
            ],
        ];
    }
}
