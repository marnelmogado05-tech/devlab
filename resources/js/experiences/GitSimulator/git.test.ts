import { describe, expect, it } from 'vitest';
import { replay, tokenise, type RepositoryState } from './git';

/*
 * The browser model is a rendering, not a verdict (ADR 0006) — but a graph that
 * disagrees with the server is still a bad experience, so the behaviours the two
 * must share are pinned here in the same order as the PHP tests.
 */

function forked(): RepositoryState {
    return {
        commits: [
            { id: 'a1', message: 'Initial commit', parents: [] },
            { id: 'a2', message: 'Second on main', parents: ['a1'] },
            { id: 'b1', message: 'First on feature', parents: ['a1'] },
            { id: 'b2', message: 'Second on feature', parents: ['b1'] },
        ],
        branches: { main: 'a2', feature: 'b2' },
        head: 'main',
    };
}

describe('tokenise', () => {
    it('keeps a quoted message whole', () => {
        // The message is part of a commit's identity on the server, so losing
        // its spaces here would draw a graph the server does not agree with.
        expect(tokenise('git commit -m "fix the thing"')).toEqual([
            'git',
            'commit',
            '-m',
            'fix the thing',
        ]);
    });

    it('keeps an empty quoted string as an argument', () => {
        expect(tokenise('git commit -m ""')).toEqual([
            'git',
            'commit',
            '-m',
            '',
        ]);
    });
});

describe('the browser model', () => {
    it('commits onto the current branch and moves it', () => {
        const { repository, error } = replay(forked(), [
            'git commit -m "Third on main"',
        ]);

        expect(error).toBeNull();
        expect(repository.branches().main).toBe(repository.head());
        expect(repository.allCommits()).toHaveLength(5);
    });

    it('fast-forwards when nothing has diverged', () => {
        const { repository } = replay(
            {
                commits: [
                    { id: 'a1', message: 'Initial commit', parents: [] },
                    { id: 'b1', message: 'On feature', parents: ['a1'] },
                ],
                branches: { main: 'a1', feature: 'b1' },
                head: 'main',
            },
            ['git merge feature'],
        );

        expect(repository.branches().main).toBe('b1');
        expect(repository.allCommits()).toHaveLength(2);
    });

    it('creates a merge commit when histories have diverged', () => {
        const { repository } = replay(forked(), ['git merge feature']);
        const head = repository.head() as string;
        const merge = repository.allCommits().find((c) => c.id === head);

        expect(merge?.parents).toEqual(['a2', 'b2']);
    });

    it('detaches HEAD on a commit checkout', () => {
        const { repository } = replay(forked(), ['git checkout b1']);

        expect(repository.isDetached()).toBe(true);
        expect(repository.head()).toBe('b1');
    });

    it('leaves every branch alone when committing on a detached HEAD', () => {
        const { repository } = replay(forked(), [
            'git checkout b1',
            'git commit -m "Work in the dark"',
        ]);

        expect(repository.branches()).toEqual({ main: 'a2', feature: 'b2' });
    });

    it('branches from a start point rather than from HEAD when given one', () => {
        const { repository } = replay(forked(), ['git branch rescue b1']);

        expect(repository.branches().rescue).toBe('b1');
    });

    it('reverts forwards, leaving the original commit in place', () => {
        const { repository } = replay(forked(), ['git revert a2']);
        const head = repository.head() as string;

        expect(
            repository.allCommits().find((c) => c.id === head)?.message,
        ).toBe('Revert "Second on main"');
        expect(repository.allCommits().some((c) => c.id === 'a2')).toBe(true);
    });

    it('cherry-picks a copy and leaves the original where it is', () => {
        const { repository } = replay(forked(), ['git cherry-pick b2']);

        expect(repository.branches().feature).toBe('b2');
        expect(
            repository
                .allCommits()
                .filter((c) => c.message === 'Second on feature'),
        ).toHaveLength(2);
    });

    it('rebases oldest commit first', () => {
        const { repository, error } = replay(forked(), [
            'git checkout feature',
            'git rebase main',
        ]);

        expect(error).toBeNull();

        const head = repository.head() as string;
        const second = repository.allCommits().find((c) => c.id === head);
        const first = repository
            .allCommits()
            .find((c) => c.id === second?.parents[0]);

        expect(second?.message).toBe('Second on feature');
        expect(first?.message).toBe('First on feature');
        expect(first?.parents).toEqual(['a2']);
    });

    it('refuses to rebase a history containing a merge', () => {
        const { error } = replay(forked(), [
            'git checkout feature',
            'git merge main',
            'git commit -m "After the merge"',
            'git checkout main',
            'git commit -m "Moved on"',
            'git checkout feature',
            'git rebase main',
        ]);

        expect(error).toBe(
            'This simulator cannot rebase a history that contains a merge.',
        );
    });

    it('will not delete the branch you are on', () => {
        const { error } = replay(forked(), ['git branch -d main']);

        expect(error).toBe(
            "Cannot delete 'main': it is the branch you are on.",
        );
    });

    it('refuses a command it does not implement, by name', () => {
        const { error } = replay(forked(), ['git bisect start']);

        expect(error).toBe("This simulator does not implement 'git bisect'.");
    });

    it('refuses a command the challenge does not allow', () => {
        const { error } = replay(
            forked(),
            ['git reset a1'],
            ['merge', 'checkout'],
        );

        expect(error).toBe("This challenge does not allow 'git reset'.");
    });

    it('stops at the first command that will not apply', () => {
        // Git is stateful: every command after a failed one was typed against a
        // repository that never existed.
        const { applied, error, repository } = replay(forked(), [
            'git checkout feature',
            'git checkout nowhere',
            'git commit -m "Never happened"',
        ]);

        expect(applied).toBe(1);
        expect(error).toBe("There is no branch or commit called 'nowhere'.");
        expect(repository.allCommits()).toHaveLength(4);
    });
});
