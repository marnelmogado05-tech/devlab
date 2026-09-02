<?php

namespace App\Services\Challenge\BugHunter;

use App\Models\Challenge;
use App\Services\Challenge\ChallengeEvaluator;
use App\Services\Challenge\EvaluationResult;

/**
 * Grades a Bug Hunter submission: one line number, checked against the recorded
 * defect location.
 *
 * Nothing here executes anything. Running a player's code needs the Phase 3
 * sandbox, its own ADR and a dedicated security review (§25) — and this
 * experience is built so that it never has to.
 */
class BugHunterEvaluator implements ChallengeEvaluator
{
    public function __construct(private readonly BugHunterConfiguration $configuration) {}

    public function evaluate(Challenge $challenge, array $submission): EvaluationResult
    {
        $line = $submission['line'] ?? null;

        if (! is_int($line) && ! (is_string($line) && ctype_digit($line))) {
            return EvaluationResult::incorrect(
                feedback: 'Pick a line from the snippet.',
                details: ['reason' => 'not_a_line'],
            );
        }

        $line = (int) $line;
        $count = $this->configuration->lineCount($challenge);

        /*
         * Out of range is wrong, not an error. A stale tab can post a line that
         * a shorter new version of the snippet no longer has, and throwing would
         * turn that into a 500 on the player's submission.
         */
        if ($line < 1 || $line > $count) {
            return EvaluationResult::incorrect(
                feedback: 'That line is not in the snippet — it may have changed since you opened it.',
                details: ['reason' => 'out_of_range', 'line' => $line],
            );
        }

        /** @var array<int, int> $defective */
        $defective = array_map(intval(...), $challenge->solution['lines'] ?? []);

        if (in_array($line, $defective, true)) {
            return EvaluationResult::correct(
                accuracy: 1.0,
                feedback: 'Found it.',
                details: ['line' => $line],
            );
        }

        return EvaluationResult::incorrect(
            /*
             * No "close" and no distance hint. Being one line away from a null
             * check is not partial understanding, and telling a player they are
             * warm turns a debugging exercise into a guessing game.
             */
            feedback: 'The code on that line is fine.',
            details: ['line' => $line],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function submissionRules(Challenge $challenge): array
    {
        return [
            'line' => ['required', 'integer', 'min:1', 'max:'.$this->configuration->lineCount($challenge)],
        ];
    }
}
