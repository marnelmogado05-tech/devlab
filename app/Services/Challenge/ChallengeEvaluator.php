<?php

namespace App\Services\Challenge;

use App\Models\Challenge;

/**
 * One evaluator per experience — the fifth of the seven pieces every experience
 * implements (§72).
 *
 * An interface rather than one growing conditional: adding an experience must
 * not mean editing a switch that every other experience also depends on.
 *
 * Implementations must be DETERMINISTIC and must not mutate anything. They are
 * handed the challenge and the raw submission, and answer only "what happened".
 * They never see the user, the attempt timing or the score, because none of that
 * may influence whether an answer is correct.
 */
interface ChallengeEvaluator
{
    /**
     * @param  array<string, mixed>  $submission  validated, but user-controlled:
     *                                            treat every value as untrusted
     */
    public function evaluate(Challenge $challenge, array $submission): EvaluationResult;

    /**
     * Validation rules for this experience's submission payload.
     *
     * The evaluator owns them because it is the only thing that knows the shape
     * it can read. Anything not described here is rejected before evaluation.
     *
     * @return array<string, mixed>
     */
    public function submissionRules(Challenge $challenge): array;
}
