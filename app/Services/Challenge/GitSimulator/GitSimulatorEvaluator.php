<?php

namespace App\Services\Challenge\GitSimulator;

use App\Models\Challenge;
use App\Services\Challenge\ChallengeEvaluator;
use App\Services\Challenge\EvaluationResult;

/**
 * Grades a Git Simulator submission: a sequence of commands, replayed here.
 *
 * The submission is what the player TYPED, never the graph they ended up
 * looking at. The browser draws a graph so the work is visible; it does not get
 * a vote on whether the work was right (law 1, ADR 0006).
 *
 * The goal is the author's own command sequence, replayed by the same model, and
 * comparison is structural — so any route that produces the same history passes.
 * There is more than one way to solve most Git problems, and an evaluator that
 * accepted only the author's route would be grading typing.
 */
class GitSimulatorEvaluator implements ChallengeEvaluator
{
    public function __construct(
        private readonly GitSimulatorConfiguration $configuration,
        private readonly GitSimulation $simulation,
    ) {}

    public function evaluate(Challenge $challenge, array $submission): EvaluationResult
    {
        $commands = $this->commands($submission);

        if ($commands === []) {
            return EvaluationResult::incorrect(
                feedback: 'No commands were submitted.',
                details: ['reason' => 'empty'],
            );
        }

        $allowed = $this->configuration->allowed($challenge);

        $result = $this->simulation->run(
            $this->configuration->startingRepository($challenge),
            $commands,
            $allowed,
        );

        if ($result['error'] !== null) {
            /*
             * A command that will not apply is a wrong answer, not an error. It
             * is also the most useful feedback this experience can give: which
             * command Git would have refused, and why.
             */
            return EvaluationResult::incorrect(
                feedback: "'".($commands[$result['applied']] ?? '?')."' would not apply: ".$result['error'],
                details: [
                    'commands' => $commands,
                    'applied' => $result['applied'],
                    'error' => $result['error'],
                ],
            );
        }

        $goal = $this->goalFingerprint($challenge);
        $reached = $result['repository']->fingerprint();

        $details = [
            'commands' => $commands,
            'applied' => $result['applied'],
            'error' => null,
        ];

        if ($reached === $goal) {
            return EvaluationResult::correct(
                accuracy: 1.0,
                feedback: count($commands) === 1
                    ? 'Solved, in one command.'
                    : 'Solved, in '.count($commands).' commands.',
                details: $details,
            );
        }

        return EvaluationResult::incorrect(
            feedback: $this->difference($goal, $reached),
            details: $details,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function submissionRules(Challenge $challenge): array
    {
        return [
            'commands' => ['required', 'array', 'min:1', 'max:'.GitSimulation::MAX_COMMANDS],
            'commands.*' => ['required', 'string', 'max:200'],
        ];
    }

    /**
     * The state the author's own solution reaches.
     *
     * Replayed rather than stored. An author cannot write a Merkle fingerprint
     * by hand, and a goal defined by reaching it cannot be a goal nobody can
     * reach.
     *
     * @return array{branches: array<string, string>, head: string}
     */
    private function goalFingerprint(Challenge $challenge): array
    {
        $result = $this->simulation->run(
            $this->configuration->startingRepository($challenge),
            array_map(strval(...), $challenge->solution['commands'] ?? []),
            $this->configuration->allowed($challenge),
        );

        return $result['repository']->fingerprint();
    }

    /**
     * Say what is wrong with the shape without saying what would fix it.
     *
     * Naming a branch that is in the wrong place is the Git equivalent of "the
     * code on that line is fine": it tells the player where to look and leaves
     * the thinking to them.
     *
     * @param  array{branches: array<string, string>, head: string}  $goal
     * @param  array{branches: array<string, string>, head: string}  $reached
     */
    private function difference(array $goal, array $reached): string
    {
        $missing = array_diff(array_keys($goal['branches']), array_keys($reached['branches']));
        $extra = array_diff(array_keys($reached['branches']), array_keys($goal['branches']));

        if ($missing !== []) {
            return 'The history is not there yet: no branch called '.implode(', ', $missing).'.';
        }

        if ($extra !== []) {
            return 'There is a branch the finished repository should not have: '.implode(', ', $extra).'.';
        }

        $wrong = [];

        foreach ($goal['branches'] as $name => $fingerprint) {
            if (($reached['branches'][$name] ?? null) !== $fingerprint) {
                $wrong[] = $name;
            }
        }

        if ($wrong !== []) {
            return 'The history under '.implode(', ', $wrong).' is not the shape it should be.';
        }

        if ($goal['head'] !== $reached['head']) {
            // Worth its own message: being detached at a commit and being on the
            // branch that points to it look identical until something moves.
            return 'The history is right, but HEAD is not where it should be.';
        }

        return 'That is not the repository the challenge asked for.';
    }

    /**
     * @param  array<string, mixed>  $submission
     * @return array<int, string>
     */
    private function commands(array $submission): array
    {
        $commands = $submission['commands'] ?? null;

        if (! is_array($commands)) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                fn (mixed $command) => is_string($command) ? trim($command) : '',
                $commands,
            ),
            fn (string $command) => $command !== '',
        ));
    }
}
