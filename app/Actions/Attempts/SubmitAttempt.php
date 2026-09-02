<?php

namespace App\Actions\Attempts;

use App\Events\ChallengeCompleted;
use App\Models\ChallengeAttempt;
use App\Models\UserStatistic;
use App\Models\XpTransaction;
use App\Services\Challenge\EvaluatorRegistry;
use App\Services\Leaderboard\LeaderboardService;
use App\Services\Progression\AchievementUnlocker;
use App\Services\Progression\RefreshUserStatistics;
use App\Services\Progression\XpLedger;
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
        private readonly XpLedger $ledger,
        private readonly RefreshUserStatistics $statistics,
        private readonly AchievementUnlocker $achievements,
        private readonly LeaderboardService $leaderboards,
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
                streakDays: $this->currentStreakFor($fresh->user_id),
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

            /*
             * XP is written INSIDE this transaction, not in a queued listener.
             * ADR 0005 makes that binding: a dropped job must never mean lost XP,
             * which is what allows the Redis queue's lack of durability to be
             * acceptable elsewhere.
             */
            if ($evaluation->correct) {
                $this->grantCompletionXp($fresh);
            }

            // Recomputed from source, including the rows just written.
            $this->statistics->forUser($fresh->user);

            /*
             * Achievements are evaluated here rather than from a queued
             * listener. The progression skill's diagram enqueues this step, but
             * ADR 0005 is binding and more specific: an achievement grants XP,
             * and no reward may exist only in a job.
             *
             * Statistics are refreshed first, because the rules are read against
             * them — evaluating before the refresh would judge the user on the
             * state they were in before this completion.
             */
            $unlocked = $this->achievements->evaluateFor($fresh->user->refresh());

            if ($unlocked !== []) {
                // An unlock grants XP, which changes the totals the next rule
                // would read.
                $this->statistics->forUser($fresh->user);
            }

            return [$fresh, true];
        });

        if ($wasScored) {
            /*
             * AFTER the commit, and deliberately outside the transaction: the
             * sorted sets are a disposable index of data that is already durable
             * in PostgreSQL. Failing a committed completion because a cache
             * could not be updated would trade real work for a rebuildable one,
             * so LeaderboardService swallows and logs its own errors.
             */
            $this->leaderboards->sync($completed->user);

            // After commit, so no listener can observe uncommitted state.
            ChallengeCompleted::dispatch($completed);
        }

        return $completed;
    }

    /**
     * The user's streak as it stands right now.
     *
     * Read straight from the table rather than through the relation: the attempt
     * may have been loaded before anything touched statistics, and a stale
     * in-memory relation would quietly score against yesterday's streak.
     */
    private function currentStreakFor(int $userId): int
    {
        return (int) (UserStatistic::query()
            ->whereKey($userId)
            ->value('current_streak_days') ?? 0);
    }

    /**
     * The award for finishing a challenge for the first time.
     *
     * Keyed by CHALLENGE, not by attempt. The unique index on
     * (user_id, source_type, source_id) then means "one award per challenge,
     * ever" — keying it by attempt would pay a user again every time they
     * replayed the same challenge, which config/devlab.php explicitly rules out.
     */
    private function grantCompletionXp(ChallengeAttempt $attempt): void
    {
        $amount = (int) config("devlab.xp.{$attempt->challenge->difficulty}", 0);

        if ($amount <= 0) {
            return;
        }

        $this->ledger->grant(
            user: $attempt->user,
            amount: $amount,
            sourceType: XpTransaction::SOURCE_CHALLENGE_COMPLETION,
            sourceId: (string) $attempt->challenge_id,
            description: "Completed: {$attempt->challenge->title}",
            metadata: [
                'attempt_id' => $attempt->id,
                'challenge_version' => $attempt->challenge_version,
                'difficulty' => $attempt->challenge->difficulty,
            ],
        );
    }
}
