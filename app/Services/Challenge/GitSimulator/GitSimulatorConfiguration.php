<?php

namespace App\Services\Challenge\GitSimulator;

use App\Models\Challenge;
use Illuminate\Support\Facades\Validator;

/**
 * Validates the shape of `challenges.configuration` and `solution` for Git
 * Simulator.
 *
 * The contract is in docs/experiences/git-simulator.md; the reason the model
 * exists twice is ADR 0006.
 *
 * The checks that justify this class all concern the author's own solution.
 * A goal is DEFINED by replaying `solution.commands`, so a solution that does
 * not apply cleanly does not describe a reachable state — it describes nothing,
 * and every attempt would fail against a goal that is really just the starting
 * position.
 */
class GitSimulatorConfiguration
{
    public function __construct(private readonly GitSimulation $simulation) {}

    /**
     * @return array<int, string> the problems found; empty means valid
     */
    public function problems(Challenge $challenge): array
    {
        $validator = Validator::make(
            ['configuration' => $challenge->configuration, 'solution' => $challenge->solution],
            [
                'configuration.goal' => ['required', 'string', 'max:500'],
                'configuration.repository' => ['required', 'array'],
                'configuration.repository.commits' => ['required', 'array', 'min:1', 'max:30'],
                'configuration.repository.commits.*.id' => ['required', 'string', 'max:20'],
                'configuration.repository.commits.*.message' => ['required', 'string', 'max:120'],
                'configuration.repository.commits.*.parents' => ['nullable', 'array', 'max:2'],
                'configuration.repository.branches' => ['required', 'array', 'min:1', 'max:8'],
                'configuration.repository.head' => ['required', 'string', 'max:40'],
                'configuration.allowed' => ['nullable', 'array'],
                'configuration.allowed.*' => ['string', 'in:'.implode(',', GitSimulation::COMMANDS)],

                'solution.commands' => ['required', 'array', 'min:1', 'max:'.GitSimulation::MAX_COMMANDS],
                'solution.commands.*' => ['required', 'string', 'max:200'],
                'solution.summary' => ['nullable', 'string', 'max:500'],
            ],
        );

        $problems = array_merge(...array_values($validator->errors()->toArray())) ?: [];

        if ($problems !== []) {
            return $problems;
        }

        return [
            ...$this->repositoryProblems($challenge),
            ...$this->solutionProblems($challenge),
        ];
    }

    public function isValid(Challenge $challenge): bool
    {
        return $this->problems($challenge) === [];
    }

    /**
     * The starting repository, rebuilt from configuration.
     *
     * The single definition of where a challenge begins, used by the validator,
     * the evaluator and — through the same JSON — the React module, so all three
     * start from the same graph.
     */
    public function startingRepository(Challenge $challenge): GitRepository
    {
        /** @var array<string, mixed> $state */
        $state = $challenge->configuration['repository'] ?? [];

        return GitRepository::fromState(
            $state['commits'] ?? [],
            $state['branches'] ?? [],
            (string) ($state['head'] ?? ''),
        );
    }

    /**
     * The command names this challenge permits, or null for all of them.
     *
     * @return array<int, string>|null
     */
    public function allowed(Challenge $challenge): ?array
    {
        $allowed = $challenge->configuration['allowed'] ?? null;

        return is_array($allowed) && $allowed !== [] ? array_map(strval(...), $allowed) : null;
    }

    /**
     * @return array<int, string>
     */
    private function repositoryProblems(Challenge $challenge): array
    {
        $problems = [];

        /** @var array<string, mixed> $state */
        $state = $challenge->configuration['repository'];

        $ids = array_map(fn (array $commit) => (string) $commit['id'], $state['commits']);

        if (count($ids) !== count(array_unique($ids))) {
            $problems[] = 'Commit ids must be unique.';
        }

        foreach ($state['commits'] as $commit) {
            foreach ($commit['parents'] ?? [] as $parent) {
                if (! in_array((string) $parent, $ids, true)) {
                    $problems[] = "Commit '{$commit['id']}' has parent '{$parent}', which does not exist.";
                }
            }
        }

        foreach ($state['branches'] as $name => $at) {
            if (! in_array((string) $at, $ids, true)) {
                $problems[] = "Branch '{$name}' points at '{$at}', which is not a commit in this repository.";
            }
        }

        $head = (string) $state['head'];

        if (! array_key_exists($head, $state['branches']) && ! in_array($head, $ids, true)) {
            $problems[] = "HEAD is '{$head}', which is neither a branch nor a commit here.";
        }

        /*
         * New commits are named n1, n2, ... during a replay. An author reusing
         * those ids in the starting graph would have a player's first commit
         * silently overwrite a commit that was already there.
         */
        foreach ($ids as $id) {
            if (preg_match('/^n\d+$/', $id) === 1) {
                $problems[] = "Commit id '{$id}' is reserved: replayed commits are named n1, n2 and so on.";
            }
        }

        return $problems;
    }

    /**
     * @return array<int, string>
     */
    private function solutionProblems(Challenge $challenge): array
    {
        /** @var array<int, string> $commands */
        $commands = $challenge->solution['commands'];

        $allowed = $this->allowed($challenge);

        $start = $this->startingRepository($challenge);
        $result = $this->simulation->run($this->startingRepository($challenge), $commands, $allowed);

        if ($result['error'] !== null) {
            $failed = $commands[$result['applied']] ?? '?';

            return ["The solution does not apply: '{$failed}' — {$result['error']}"];
        }

        if ($result['repository']->fingerprint() === $start->fingerprint()) {
            /*
             * A solution that changes nothing makes the goal identical to the
             * starting position, so every attempt succeeds without doing
             * anything — the exact inverse of an unsatisfiable rubric, and just
             * as invisible at runtime.
             */
            return ['The solution leaves the repository exactly as it started, so the challenge is already solved.'];
        }

        return [];
    }
}
