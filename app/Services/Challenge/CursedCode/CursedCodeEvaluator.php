<?php

namespace App\Services\Challenge\CursedCode;

use App\Models\Challenge;
use App\Services\Challenge\ChallengeEvaluator;
use App\Services\Challenge\EvaluationResult;

/**
 * Grades a Cursed Code submission: one multiple choice, checked exactly.
 *
 * Deterministic and total — every input produces a verdict, and the same input
 * always produces the same one. It sees the challenge and the submission and
 * nothing else: not the user, not the timing, not the score, because none of
 * those may influence whether an answer is correct.
 */
class CursedCodeEvaluator implements ChallengeEvaluator
{
    public function evaluate(Challenge $challenge, array $submission): EvaluationResult
    {
        $answer = $submission['answer'] ?? null;
        $expected = $challenge->solution['answer'] ?? null;

        $keys = array_column($challenge->configuration['options'] ?? [], 'key');

        /*
         * An answer that is not one of the options is wrong, not an error. A
         * stale tab can legitimately post a key that a new challenge version no
         * longer offers, and throwing would turn that into a 500 on the player's
         * submission rather than a lost point.
         */
        if (! is_string($answer) || ! in_array($answer, $keys, true)) {
            return EvaluationResult::incorrect(
                feedback: 'That is not one of the options — the challenge may have changed since you opened it.',
                details: ['reason' => 'unknown_option'],
            );
        }

        // Exact string comparison. The keys are ours, not the user's, so there
        // is nothing to trim or case-fold and doing either would only create a
        // way for two options to collide.
        if ($answer === $expected) {
            return EvaluationResult::correct(
                accuracy: 1.0,
                feedback: 'Correct.',
                details: ['answer' => $answer],
            );
        }

        return EvaluationResult::incorrect(
            // Deliberately does not say which option was right. That is the
            // explanation's job, and the explanation says WHY — a bare letter
            // teaches nothing.
            feedback: 'Not this time.',
            details: ['answer' => $answer],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function submissionRules(Challenge $challenge): array
    {
        $keys = array_column($challenge->configuration['options'] ?? [], 'key');

        return [
            /*
             * `in:` the actual option keys, so a malformed submission is refused
             * by validation with a field error rather than reaching the
             * evaluator. The branch above still exists because validation runs
             * against the CURRENT version and an in-flight attempt may not be.
             */
            'answer' => ['required', 'string', 'in:'.implode(',', $keys)],
        ];
    }
}
