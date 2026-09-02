<?php

namespace App\Actions\Attempts;

use App\Events\ChallengeCompleted;
use App\Models\ChallengeAttempt;
use App\Services\Challenge\EvaluatorRegistry;
use App\Services\Scoring\ScoreCalculator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Evaluate a submission, score it, and close the attempt.
 *
 * This is the platform's first reward-granting path, so it answers the §67
 * checklist explicitly:
 *
 *  1. Submitted twice?          The second submission finds a closed attempt and
 *                               returns it unchanged. It does not re-evaluate,
 *                               and it cannot produce a second score.
 *  2. Retried after failure?    Everything is inside one transaction, so a
 *                               partial failure leaves the attempt open and the
 *                               retry is simply the first successful run.
 *  3. What makes it impossible? The row is locked FOR UPDATE and the status is
 *                               re-read inside the lock. This is not a PHP
 *                               existence check that races: concurrent
 *                               transactions serialise on the lock, so exactly
 *                               one sees `started`. The XP ledger's unique
 *                               constraint is the second guard when that lands.
 *  4. Replay an old attempt id? An attempt that is not open is never re-scored,
 *                               whatever id is presented — and the policy
 *                               already restricts it to its owner.
 *  5. Is there a test?          Yes, several. See SubmitAttemptTest.
 *
 * The score is computed here from server-held state. Nothing in the request body
 * reaches it: elapsed time comes from `started_at`, hint count from the attempt
 * row, correctness from the evaluator (law 1).
 */
class SubmitAttempt
{
    public function __construct(
        private readonly EvaluatorRegistry $evaluators,
        private readonly ScoreCalculator $calculator,
    ) {}

    /**
     * @param  array<string, mixed>  $submission
     */
    public function handle(ChallengeAttempt $attempt, array $submission): ChallengeAttempt
    {
        [$completed, $wasScored] = DB::transaction(function () use ($attempt, $submission): array {
            $fresh = ChallengeAttempt::query()
                ->whereKey($attempt->getKey())
                ->lockForUpdate()
                ->first();

            if ($fresh === null) {
                throw new RuntimeException('The attempt no longer exists.');
            }

            // Already closed — by a duplicate submit, an abandon, or the expiry
            // sweep. Whatever it settled on stands.
            if (! $fresh->isOpen()) {
                return [$fresh, false];
            }

            $fresh->load('challenge.experience');

            $evaluation = $this->evaluators
                ->for($fresh->challenge)
                ->evaluate($fresh->challenge, $submission);

            $elapsed = $fresh->elapsedSeconds();

            $score = $this->calculator->calculate(
                challenge: $fresh->challenge,
                evaluation: $evaluation,
                elapsedSeconds: $elapsed,
                hintsUsed: $fresh->hints_used,
                // Streaks arrive with user statistics (§56.8).
                streakDays: 0,
            );

            $fresh->update([
                /*
                 * `failed` rather than `completed` when the answer is wrong: the
                 * attempt is over either way, but the two must stay
                 * distinguishable or every success-rate figure — and therefore
                 * the difficulty calibration built on it — is wrong.
                 */
                'status' => $evaluation->correct
                    ? ChallengeAttempt::STATUS_COMPLETED
                    : ChallengeAttempt::STATUS_FAILED,
                'completed_at' => now(),
                'time_taken_seconds' => $elapsed,
                'score' => $score->total,
                'max_score' => $score->maxPossible,
                'submission' => $submission,
                'evaluation' => [
                    ...$evaluation->toArray(),
                    'score_breakdown' => $score->toArray(),
                ],
            ]);

            return [$fresh, true];
        });

        // After commit, so no listener can observe uncommitted state.
        if ($wasScored) {
            ChallengeCompleted::dispatch($completed);
        }

        return $completed;
    }
}
