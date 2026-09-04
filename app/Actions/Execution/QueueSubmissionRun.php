<?php

namespace App\Actions\Execution;

use App\Jobs\ExecuteSubmission;
use App\Models\ChallengeAttempt;
use App\Models\ExecutionRun;
use App\Services\Challenge\CodeArena\CodeArenaConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Record a run and hand it to the queue (ADR 0008).
 *
 * The row is written before the job is dispatched, and the job carries only its
 * id. A job that arrives before its transaction commits would find nothing —
 * hence `afterCommit` — and a job that is lost leaves a row a human can still
 * see, rather than a submission that vanished.
 *
 * Nothing here executes anything, and nothing here touches the attempt. Creating
 * a run does not close it, does not score it, and does not count against it: a
 * player may run their code as many times as the budget allows and submit none
 * of them.
 */
class QueueSubmissionRun
{
    public function __construct(private readonly CodeArenaConfiguration $configuration) {}

    /**
     * @throws ValidationException when the attempt has spent its run budget
     */
    public function handle(ChallengeAttempt $attempt, string $source): ExecutionRun
    {
        $attempt->loadMissing('challenge');

        $run = DB::transaction(function () use ($attempt, $source): ExecutionRun {
            /*
             * The ATTEMPT is locked, and the runs are then counted under that
             * lock. Not `lockForUpdate()->count()` — PostgreSQL refuses FOR
             * UPDATE with an aggregate, and there is nothing to lock anyway: the
             * rows that would beat this budget do not exist yet.
             *
             * Locking the parent is what serialises concurrent creations for one
             * attempt, and the budget is the only thing standing between one
             * attempt and an unbounded compute bill. A check that races is a
             * budget that can be beaten by holding the button down.
             */
            ChallengeAttempt::query()->whereKey($attempt->id)->lockForUpdate()->first();

            $spent = ExecutionRun::query()
                ->where('challenge_attempt_id', $attempt->id)
                ->count();

            if ($spent >= $this->budget()) {
                throw ValidationException::withMessages([
                    'source' => "This attempt has used all {$this->budget()} of its runs.",
                ]);
            }

            return ExecutionRun::query()->create([
                'challenge_attempt_id' => $attempt->id,
                'user_id' => $attempt->user_id,
                'runtime' => $this->configuration->runtime($attempt->challenge),
                'source' => $source,
                'status' => ExecutionRun::STATUS_QUEUED,
            ]);
        });

        // After commit, so the worker cannot beat the row it is looking for.
        ExecuteSubmission::dispatch($run->id)->afterCommit();

        return $run;
    }

    private function budget(): int
    {
        return (int) config('devlab.execution.runs_per_attempt', 25);
    }
}
