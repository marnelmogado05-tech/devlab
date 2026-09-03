<?php

namespace App\Services\Challenge\GitSimulator;

/**
 * A repository graph: commits, branch pointers and HEAD.
 *
 * Deliberately small. There is no working tree, no index, no remotes and no
 * conflicts — see ADR 0006 for what that excludes and why. What is modelled is
 * the thing §9.7 asks players to reason about: the shape of history and where
 * the pointers are.
 *
 * Mutable, because a command sequence is applied in order and copying the whole
 * graph per command buys nothing here.
 */
class GitRepository
{
    /** @var array<string, array{id: string, message: string, parents: array<int, string>}> */
    private array $commits = [];

    /** @var array<string, string> branch name => commit id */
    private array $branches = [];

    /** Either a branch name, or null when HEAD is detached. */
    private ?string $branch = null;

    /** The commit HEAD points at when detached. */
    private ?string $detachedAt = null;

    /** Names new commits deterministically, so a replay is reproducible. */
    private int $sequence = 0;

    /**
     * @param  array<int, array{id: string, message: string, parents?: array<int, string>}>  $commits
     * @param  array<string, string>  $branches
     */
    public static function fromState(array $commits, array $branches, string $head): self
    {
        $repository = new self;

        foreach ($commits as $commit) {
            $repository->commits[$commit['id']] = [
                'id' => $commit['id'],
                'message' => $commit['message'],
                'parents' => array_values($commit['parents'] ?? []),
            ];
        }

        $repository->branches = $branches;

        if (array_key_exists($head, $branches)) {
            $repository->branch = $head;
        } else {
            $repository->detachedAt = $head;
        }

        return $repository;
    }

    /**
     * The commit HEAD resolves to, or null in an empty repository.
     */
    public function head(): ?string
    {
        return $this->branch !== null
            ? ($this->branches[$this->branch] ?? null)
            : $this->detachedAt;
    }

    public function currentBranch(): ?string
    {
        return $this->branch;
    }

    public function isDetached(): bool
    {
        return $this->branch === null;
    }

    /**
     * @return array<string, string>
     */
    public function branches(): array
    {
        return $this->branches;
    }

    /**
     * @return array<string, array{id: string, message: string, parents: array<int, string>}>
     */
    public function commits(): array
    {
        return $this->commits;
    }

    public function hasCommit(string $id): bool
    {
        return array_key_exists($id, $this->commits);
    }

    /**
     * Resolve a reference: a branch name, a commit id, or HEAD.
     */
    public function resolve(string $reference): ?string
    {
        if ($reference === 'HEAD') {
            return $this->head();
        }

        if (array_key_exists($reference, $this->branches)) {
            return $this->branches[$reference];
        }

        return $this->hasCommit($reference) ? $reference : null;
    }

    /**
     * @param  array<int, string>  $parents
     */
    public function commit(string $message, array $parents): string
    {
        $id = 'n'.++$this->sequence;

        $this->commits[$id] = [
            'id' => $id,
            'message' => $message,
            'parents' => array_values($parents),
        ];

        $this->moveHead($id);

        return $id;
    }

    /**
     * Create a commit on a given parent WITHOUT moving HEAD.
     *
     * Rebase needs this: it replays a run of commits onto a new base and only
     * then moves the branch, so committing through HEAD would drag the pointer
     * along one commit at a time and leave it in the right place for the wrong
     * reason.
     */
    public function commitOnto(string $message, string $parent): string
    {
        $id = 'n'.++$this->sequence;

        $this->commits[$id] = [
            'id' => $id,
            'message' => $message,
            'parents' => [$parent],
        ];

        return $id;
    }

    /**
     * Move whatever HEAD is — the current branch, or HEAD itself when detached.
     */
    public function moveHead(string $commit): void
    {
        if ($this->branch !== null) {
            $this->branches[$this->branch] = $commit;

            return;
        }

        $this->detachedAt = $commit;
    }

    public function createBranch(string $name, string $at): void
    {
        $this->branches[$name] = $at;
    }

    public function deleteBranch(string $name): void
    {
        unset($this->branches[$name]);
    }

    public function checkoutBranch(string $name): void
    {
        $this->branch = $name;
        $this->detachedAt = null;
    }

    public function checkoutCommit(string $commit): void
    {
        $this->branch = null;
        $this->detachedAt = $commit;
    }

    public function setBranch(string $name, string $commit): void
    {
        $this->branches[$name] = $commit;
    }

    /**
     * Every commit reachable from a starting point, walking parents.
     *
     * @return array<int, string>
     */
    public function ancestry(?string $from): array
    {
        if ($from === null) {
            return [];
        }

        $seen = [];
        $queue = [$from];

        while ($queue !== []) {
            $id = array_shift($queue);

            if (array_key_exists($id, $seen) || ! $this->hasCommit($id)) {
                continue;
            }

            $seen[$id] = true;

            foreach ($this->commits[$id]['parents'] as $parent) {
                $queue[] = $parent;
            }
        }

        return array_keys($seen);
    }

    public function isAncestor(string $candidate, ?string $of): bool
    {
        return in_array($candidate, $this->ancestry($of), true);
    }

    /**
     * The structural fingerprint of the whole repository.
     *
     * A Merkle hash per commit — its message plus the sorted fingerprints of its
     * parents — so identity is derived from SHAPE rather than from the ids a
     * particular replay happened to allocate. Two different command sequences
     * that produce the same history compare equal, which is the point: there is
     * more than one way to solve most Git problems, and an evaluator that
     * accepted only the author's route would be grading typing (ADR 0006).
     *
     * @return array{branches: array<string, string>, head: string}
     */
    public function fingerprint(): array
    {
        $branches = [];

        foreach ($this->branches as $name => $commit) {
            $branches[$name] = $this->fingerprintOf($commit);
        }

        ksort($branches);

        /*
         * Where HEAD is matters, and being ON a branch is different from being
         * detached at the commit that branch points to — that distinction IS the
         * lesson in several challenges, so it is part of the fingerprint.
         */
        $head = $this->branch !== null
            ? 'branch:'.$this->branch
            : 'detached:'.$this->fingerprintOf($this->detachedAt);

        return ['branches' => $branches, 'head' => $head];
    }

    /**
     * @param  array<string, string>  $memo
     */
    private function fingerprintOf(?string $id, array &$memo = []): string
    {
        if ($id === null || ! $this->hasCommit($id)) {
            return 'none';
        }

        if (array_key_exists($id, $memo)) {
            return $memo[$id];
        }

        $parents = array_map(
            fn (string $parent) => $this->fingerprintOf($parent, $memo),
            $this->commits[$id]['parents'],
        );

        /*
         * Sorted, so a merge recorded as (main, feature) matches one recorded as
         * (feature, main). Real Git treats the first parent as special; this
         * model does not, because no command here depends on which side you were
         * standing on when you merged.
         */
        sort($parents);

        return $memo[$id] = substr(
            hash('sha256', $this->commits[$id]['message'].'|'.implode(',', $parents)),
            0,
            16,
        );
    }
}
