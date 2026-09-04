<?php

namespace App\Jobs;

use App\Actions\Execution\RunSubmission;
use App\Models\ExecutionRun;
use App\Services\Challenge\CodeArena\CodeArenaConfiguration;
use App\Services\Execution\ExecutionRequest;
use App\Services\Execution\SandboxUnavailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Carry one recorded run through the sandbox (ADR 0008).
 *
 * This is the only place in DevLab that reaches the execution boundary, and it
 * is a queued job because a sandbox run takes seconds and can be refused. Doing
 * it in the request would hold a worker for the duration of a stranger's
 * infinite loop; doing it inside `SubmitAttempt` would hold a row lock as well.
 *
 * It writes to exactly one row — its own. It never touches the attempt, never
 * scores, never awards, and never decides whether the code was right. Grading
 * happens later, in the evaluator, against a key that never entered the sandbox.
 * That separation is what allows this job to be retried freely: a re-run
 * produces execution, not rewards, so law 5's double-award question does not
 * arise here at all.
 */
class ExecuteSubmission implements ShouldQueue
{
    use Queueable;

    /**
     * Retries are for reaching the orchestrator, not for re-running code.
     *
     * The status guard below means only one attempt can ever get past `queued`,
     * so a retry after a successful run is a no-op rather than a second
     * container. What retries actually buy is the case where the job died
     * BEFORE claiming the run.
     */
    public int $tries = 2;

    public function __construct(public readonly int $runId) {}

    public function handle(RunSubmission $runner, CodeArenaConfiguration $configuration): void
    {
        $run = $this->claim();

        if ($run === null) {
            // Someone else has it, or it is already finished. Not an error: this
            // is what makes a duplicated or retried job harmless.
            return;
        }

        $run->loadMissing('attempt.challenge', 'user');

        $challenge = $run->attempt->challenge;

        $request = ExecutionRequest::for(
            runtime: $run->runtime,
            submission: $run->source,
            /*
             * Generated per run from the challenge's case INPUTS. The expected
             * outputs are not assembled here, are not passed here, and have no
             * route into the container (ADR 0008).
             */
            tests: $configuration->harness($challenge, $this->budgetSeconds()),
        );

        try {
            $outcome = $runner->handle($run->user, $request);
        } catch (SandboxUnavailable $e) {
            /*
             * The platform could not run it. This is emphatically not a verdict
             * on the code: the run is marked unavailable, the ATTEMPT IS LEFT
             * OPEN, and the run is not submittable — so nothing downstream can
             * turn a capacity failure into a failed answer (S7).
             */
            $this->markUnavailable($run, $e->reason());

            return;
        }

        $run->update([
            'status' => ExecutionRun::STATUS_FINISHED,
            'exit_code' => $outcome->exitCode,
            'duration_ms' => $outcome->durationMs,
            'killed_by' => $outcome->killedBy,
            'truncated' => $outcome->truncated,
            'observed' => $this->parse($outcome->stdout),
            /*
             * The harness's own complaints, kept for the player to read. Already
             * sanitised and capped by the orchestrator — it crossed the boundary
             * from a process that shared memory with hostile code, so it is
             * stored as text and rendered as text, never as markup (S5).
             */
            'stderr' => $outcome->stderr === '' ? null : $outcome->stderr,
            'finished_at' => now(),
        ]);
    }

    /**
     * A job that gave up entirely.
     *
     * Without this the row sits in `running` forever and the interface tells the
     * player to keep waiting for something nobody is doing.
     */
    public function failed(?Throwable $e): void
    {
        $run = ExecutionRun::query()->find($this->runId);

        if ($run !== null && $run->isPending()) {
            $this->markUnavailable($run, ExecutionRun::REASON_UNAVAILABLE);
        }
    }

    /**
     * Take the run, or discover somebody already has.
     *
     * A conditional UPDATE rather than a read followed by a write: two workers
     * handed the same job would both see `queued` and both start a container,
     * which is the pool exhaustion S7 is about, caused by us rather than by an
     * attacker. Exactly one UPDATE matches, so exactly one job proceeds.
     */
    private function claim(): ?ExecutionRun
    {
        $claimed = DB::table('execution_runs')
            ->where('id', $this->runId)
            ->where('status', ExecutionRun::STATUS_QUEUED)
            ->update([
                'status' => ExecutionRun::STATUS_RUNNING,
                'started_at' => now(),
                'updated_at' => now(),
            ]);

        return $claimed === 1 ? ExecutionRun::query()->find($this->runId) : null;
    }

    private function markUnavailable(ExecutionRun $run, string $reason): void
    {
        $run->update([
            'status' => ExecutionRun::STATUS_UNAVAILABLE,
            'failure_reason' => $reason,
            'finished_at' => now(),
        ]);
    }

    /**
     * Read the harness's output: one JSON object per line, one line per case.
     *
     * Every byte of this came from a process containing hostile code, so it is
     * parsed defensively and never trusted to be well-formed. Anything that is
     * not a decodable object is dropped rather than repaired — a line the
     * application cannot read is a case with no result, which grades as a
     * failure, which is the correct outcome for code that broke its own harness.
     *
     * Nothing here decides whether a case passed. These are values.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parse(string $stdout): array
    {
        $observed = [];

        foreach (explode("\n", $stdout) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);

            if (is_array($decoded) && isset($decoded['case']) && is_int($decoded['case'])) {
                $observed[] = $decoded;
            }
        }

        return $observed;
    }

    /**
     * How long the harness has, in seconds.
     *
     * The sandbox's own timeout, which the orchestrator enforces from outside
     * with a longer deadline of its own. The harness budgets itself under this
     * so that a slow case costs one case rather than the whole run — a container
     * killed from outside loses every result it had not yet printed.
     */
    private function budgetSeconds(): int
    {
        return (int) config('devlab.execution.limits.timeout_seconds', 10);
    }
}
