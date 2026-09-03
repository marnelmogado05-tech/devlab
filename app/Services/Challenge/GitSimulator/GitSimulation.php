<?php

namespace App\Services\Challenge\GitSimulator;

/**
 * Applies Git commands to a {@see GitRepository}.
 *
 * The authority. Its TypeScript counterpart draws the same graph in the browser
 * so a player can see what they are doing, but decides nothing — ADR 0006
 * explains why the model exists twice and what keeps the duplication honest.
 *
 * Everything here is deterministic and total: a command either applies or is
 * refused with a reason. Nothing throws, because the input is a player's typing
 * and a 500 on a typo is a worse experience than being told the command is not
 * understood.
 */
class GitSimulation
{
    /** Every command this model understands. Anything else is refused. */
    public const COMMANDS = [
        'commit', 'branch', 'checkout', 'switch', 'merge',
        'reset', 'revert', 'cherry-pick', 'rebase',
    ];

    /** A bound on the work one submission can ask for. */
    public const MAX_COMMANDS = 30;

    /**
     * Replay a sequence, stopping at the first command that will not apply.
     *
     * Stopping rather than skipping: Git is stateful, and every command after a
     * failed one was typed against a repository that never existed. Carrying on
     * would grade a history the player never saw.
     *
     * @param  array<int, string>  $commands
     * @param  array<int, string>|null  $allowed  command names this challenge permits
     * @return array{repository: GitRepository, applied: int, error: string|null}
     */
    public function run(GitRepository $repository, array $commands, ?array $allowed = null): array
    {
        $applied = 0;

        foreach ($commands as $command) {
            $error = $this->apply($repository, (string) $command, $allowed);

            if ($error !== null) {
                return ['repository' => $repository, 'applied' => $applied, 'error' => $error];
            }

            $applied++;
        }

        return ['repository' => $repository, 'applied' => $applied, 'error' => null];
    }

    /**
     * @param  array<int, string>|null  $allowed
     * @return string|null the reason it was refused, or null when it applied
     */
    public function apply(GitRepository $repository, string $command, ?array $allowed = null): ?string
    {
        $tokens = $this->tokenise($command);

        if ($tokens === []) {
            return 'Empty command.';
        }

        if (array_shift($tokens) !== 'git') {
            return 'Commands must start with "git".';
        }

        $name = array_shift($tokens) ?? '';

        if (! in_array($name, self::COMMANDS, true)) {
            return "This simulator does not implement 'git {$name}'.";
        }

        if ($allowed !== null && ! in_array($name, $allowed, true)) {
            return "This challenge does not allow 'git {$name}'.";
        }

        /*
         * Exhaustive over self::COMMANDS, which the check above already
         * guarantees $name is one of. No default arm: adding a command to the
         * constant without adding an arm here should fail loudly in the tests
         * rather than quietly report it as unimplemented.
         */
        return match ($name) {
            'commit' => $this->commit($repository, $tokens),
            'branch' => $this->branch($repository, $tokens),
            'checkout', 'switch' => $this->checkout($repository, $tokens),
            'merge' => $this->merge($repository, $tokens),
            'reset' => $this->reset($repository, $tokens),
            'revert' => $this->revert($repository, $tokens),
            'cherry-pick' => $this->cherryPick($repository, $tokens),
            'rebase' => $this->rebase($repository, $tokens),
        };
    }

    /**
     * Split on whitespace, keeping quoted strings together.
     *
     * Written out rather than done with a regex because commit messages are the
     * one place a player types free text, and a message that loses its spaces
     * changes the commit's fingerprint — a wrong answer with no visible cause.
     *
     * @return array<int, string>
     */
    public function tokenise(string $command): array
    {
        $tokens = [];
        $current = '';
        $quote = null;
        $started = false;

        foreach (str_split(trim($command)) as $character) {
            if ($quote !== null) {
                if ($character === $quote) {
                    $quote = null;

                    continue;
                }

                $current .= $character;

                continue;
            }

            if ($character === '"' || $character === "'") {
                $quote = $character;
                $started = true;

                continue;
            }

            if ($character === ' ' || $character === "\t") {
                if ($current !== '' || $started) {
                    $tokens[] = $current;
                    $current = '';
                    $started = false;
                }

                continue;
            }

            $current .= $character;
        }

        if ($current !== '' || $started) {
            $tokens[] = $current;
        }

        return $tokens;
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function commit(GitRepository $repository, array $tokens): ?string
    {
        $message = $this->flagValue($tokens, ['-m', '--message']);

        if ($message === null || $message === '') {
            return 'git commit needs a message: git commit -m "..."';
        }

        $head = $repository->head();

        $repository->commit($message, $head === null ? [] : [$head]);

        return null;
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function branch(GitRepository $repository, array $tokens): ?string
    {
        $delete = in_array('-d', $tokens, true) || in_array('-D', $tokens, true);
        $names = $this->operands($tokens);

        if ($names === []) {
            return 'git branch needs a name.';
        }

        $name = $names[0];

        if ($delete) {
            if (! array_key_exists($name, $repository->branches())) {
                return "There is no branch '{$name}'.";
            }

            if ($name === $repository->currentBranch()) {
                return "Cannot delete '{$name}': it is the branch you are on.";
            }

            $repository->deleteBranch($name);

            return null;
        }

        if (array_key_exists($name, $repository->branches())) {
            return "Branch '{$name}' already exists.";
        }

        // `git branch <name> <start-point>` branches from somewhere other than
        // HEAD. Without this a player has to checkout first, which changes where
        // they end up and therefore what they are graded on.
        $at = isset($names[1]) ? $repository->resolve($names[1]) : $repository->head();

        if ($at === null) {
            return isset($names[1])
                ? "There is no branch or commit called '{$names[1]}'."
                : 'There is nothing to branch from yet.';
        }

        $repository->createBranch($name, $at);

        return null;
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function checkout(GitRepository $repository, array $tokens): ?string
    {
        $create = in_array('-b', $tokens, true) || in_array('-c', $tokens, true);
        $operands = $this->operands($tokens);

        if ($operands === []) {
            return 'git checkout needs a branch or commit.';
        }

        $target = $operands[0];

        if ($create) {
            if (array_key_exists($target, $repository->branches())) {
                return "Branch '{$target}' already exists.";
            }

            $at = isset($operands[1]) ? $repository->resolve($operands[1]) : $repository->head();

            if ($at === null) {
                return isset($operands[1])
                    ? "There is no branch or commit called '{$operands[1]}'."
                    : 'There is nothing to branch from yet.';
            }

            $repository->createBranch($target, $at);
            $repository->checkoutBranch($target);

            return null;
        }

        if (array_key_exists($target, $repository->branches())) {
            $repository->checkoutBranch($target);

            return null;
        }

        if ($repository->hasCommit($target)) {
            // Detached HEAD, and deliberately not warned about: noticing is the
            // lesson in more than one challenge.
            $repository->checkoutCommit($target);

            return null;
        }

        return "There is no branch or commit called '{$target}'.";
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function merge(GitRepository $repository, array $tokens): ?string
    {
        $operands = $this->operands($tokens);

        if ($operands === []) {
            return 'git merge needs something to merge.';
        }

        $target = $repository->resolve($operands[0]);
        $head = $repository->head();

        if ($target === null) {
            return "There is no branch or commit called '{$operands[0]}'.";
        }

        if ($head === null) {
            return 'There is nothing to merge into yet.';
        }

        if ($repository->isAncestor($target, $head)) {
            return "Already up to date: '{$operands[0]}' is already in this history.";
        }

        /*
         * A fast-forward when HEAD is an ancestor of the target and nothing has
         * diverged. Real Git does this unless told not to, and a model that
         * always created a merge commit would teach the wrong shape.
         */
        if ($repository->isAncestor($head, $target)) {
            $repository->moveHead($target);

            return null;
        }

        $repository->commit("Merge branch '{$operands[0]}'", [$head, $target]);

        return null;
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function reset(GitRepository $repository, array $tokens): ?string
    {
        $operands = $this->operands($tokens);

        if ($operands === []) {
            return 'git reset needs a target: git reset --hard <ref>';
        }

        $target = $repository->resolve($operands[0]);

        if ($target === null) {
            return "There is no branch or commit called '{$operands[0]}'.";
        }

        /*
         * --soft, --mixed and --hard all move the pointer, and this model has no
         * working tree or index for them to differ over (ADR 0006). The flag is
         * accepted and has no effect on the graph, which is the honest outcome
         * rather than pretending to a distinction the model cannot represent.
         */
        $repository->moveHead($target);

        return null;
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function revert(GitRepository $repository, array $tokens): ?string
    {
        $operands = $this->operands($tokens);

        if ($operands === []) {
            return 'git revert needs a commit.';
        }

        $target = $repository->resolve($operands[0]);
        $head = $repository->head();

        if ($target === null) {
            return "There is no branch or commit called '{$operands[0]}'.";
        }

        if ($head === null) {
            return 'There is nothing to revert onto yet.';
        }

        $message = $repository->commits()[$target]['message'];

        // Forward, never destructive — which is the entire difference between
        // revert and reset, and the reason revert is safe on shared history.
        $repository->commit("Revert \"{$message}\"", [$head]);

        return null;
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function cherryPick(GitRepository $repository, array $tokens): ?string
    {
        $operands = $this->operands($tokens);

        if ($operands === []) {
            return 'git cherry-pick needs a commit.';
        }

        $target = $repository->resolve($operands[0]);
        $head = $repository->head();

        if ($target === null) {
            return "There is no branch or commit called '{$operands[0]}'.";
        }

        if ($head === null) {
            return 'There is nothing to cherry-pick onto yet.';
        }

        // A copy: same message, new commit, new parent. That the original stays
        // where it is is the thing players most often expect to be otherwise.
        $repository->commit($repository->commits()[$target]['message'], [$head]);

        return null;
    }

    /**
     * Linear rebase: replay the commits unique to HEAD on top of the target.
     *
     * No interactive rebase, no conflict handling. What is modelled is the shape
     * change — a branch that forked becomes a branch that follows.
     *
     * @param  array<int, string>  $tokens
     */
    private function rebase(GitRepository $repository, array $tokens): ?string
    {
        $operands = $this->operands($tokens);

        if ($operands === []) {
            return 'git rebase needs something to rebase onto.';
        }

        $onto = $repository->resolve($operands[0]);
        $head = $repository->head();

        if ($onto === null) {
            return "There is no branch or commit called '{$operands[0]}'.";
        }

        if ($head === null) {
            return 'There is nothing to rebase yet.';
        }

        if ($repository->isAncestor($head, $onto)) {
            $repository->moveHead($onto);

            return null;
        }

        if ($repository->isAncestor($onto, $head)) {
            return "Already up to date: '{$operands[0]}' is already in this history.";
        }

        $unique = array_values(array_diff(
            $repository->ancestry($head),
            $repository->ancestry($onto),
        ));

        if (count($unique) > 1 && $this->hasMerge($repository, $unique)) {
            // Rebasing a history that contains a merge is where real Git starts
            // asking questions. Refusing is more honest than inventing an answer.
            return 'This simulator cannot rebase a history that contains a merge.';
        }

        /*
         * Oldest first. `ancestry` walks parents, so the natural order is
         * newest-first, and replaying in that order would invert the history.
         */
        $ordered = array_reverse($unique);

        $base = $onto;

        foreach ($ordered as $id) {
            $base = $repository->commitOnto($repository->commits()[$id]['message'], $base);
        }

        $repository->moveHead($base);

        return null;
    }

    /**
     * @param  array<int, string>  $ids
     */
    private function hasMerge(GitRepository $repository, array $ids): bool
    {
        foreach ($ids as $id) {
            if (count($repository->commits()[$id]['parents']) > 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * The value following a flag, e.g. -m "message".
     *
     * @param  array<int, string>  $tokens
     * @param  array<int, string>  $flags
     */
    private function flagValue(array $tokens, array $flags): ?string
    {
        foreach ($tokens as $index => $token) {
            if (in_array($token, $flags, true)) {
                return $tokens[$index + 1] ?? null;
            }

            foreach ($flags as $flag) {
                if (str_starts_with($token, $flag.'=')) {
                    return substr($token, strlen($flag) + 1);
                }
            }
        }

        return null;
    }

    /**
     * The tokens that are not flags.
     *
     * @param  array<int, string>  $tokens
     * @return array<int, string>
     */
    private function operands(array $tokens): array
    {
        return array_values(array_filter(
            $tokens,
            fn (string $token) => $token !== '' && ! str_starts_with($token, '-'),
        ));
    }
}
