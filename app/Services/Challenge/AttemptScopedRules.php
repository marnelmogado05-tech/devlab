<?php

namespace App\Services\Challenge;

use App\Models\ChallengeAttempt;

/**
 * An evaluator whose submission payload names a row, and therefore needs the
 * attempt to say which rows are legitimately nameable.
 *
 * Additive rather than a change to `ChallengeEvaluator`: five experiences answer
 * with a value — a line number, an option key, a set of choices — and none of
 * them should grow a parameter they have no use for.
 *
 * The distinction it preserves is worth being explicit about. An evaluator still
 * decides only whether an answer is CORRECT, and still never sees the user, the
 * timing or the score. Whether the player is allowed to point at a particular
 * row is authorization (law 4), and belongs in validation, before evaluation
 * runs at all — which is exactly what implementing this puts there.
 */
interface AttemptScopedRules
{
    /**
     * Validation rules for a submission against THIS attempt.
     *
     * Used in place of `submissionRules()` when the evaluator implements this.
     *
     * @return array<string, mixed>
     */
    public function attemptSubmissionRules(ChallengeAttempt $attempt): array;
}
