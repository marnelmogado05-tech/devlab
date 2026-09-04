<?php

namespace App\Http\Controllers;

use App\Actions\Execution\QueueSubmissionRun;
use App\Http\Requests\Execution\StoreExecutionRunRequest;
use App\Models\ChallengeAttempt;
use App\Models\ExecutionRun;
use App\Services\Challenge\CodeArena\CodeArenaConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Runs against one attempt: start one, read them back.
 *
 * JSON rather than Inertia, because this is polled. A run is asynchronous by
 * design (ADR 0008) and the interface has to ask whether it has finished; doing
 * that through a page visit would re-render the editor under the player's
 * cursor every two seconds.
 *
 * Nothing here scores anything. A run is evidence; the verdict is
 * `SubmitAttempt`'s, once the player chooses to submit one.
 */
class ExecutionRunController extends Controller
{
    /**
     * GET /attempts/{attempt}/runs
     */
    public function index(ChallengeAttempt $attempt, CodeArenaConfiguration $configuration): JsonResponse
    {
        Gate::authorize('view', $attempt);

        $attempt->load('challenge');

        $runs = ExecutionRun::query()
            ->where('challenge_attempt_id', $attempt->id)
            ->orderByDesc('id')
            ->limit($this->budget())
            ->get();

        return response()->json([
            'runs' => $runs->map(fn (ExecutionRun $run) => $this->toArray($run, $attempt, $configuration)),
            'remaining' => max(0, $this->budget() - $runs->count()),
        ]);
    }

    /**
     * POST /attempts/{attempt}/runs
     *
     * Authorization and validation both happen in the form request: this cannot
     * be reached without an owned, OPEN attempt and a submission within the size
     * cap.
     */
    public function store(
        StoreExecutionRunRequest $request,
        ChallengeAttempt $attempt,
        QueueSubmissionRun $queue,
        CodeArenaConfiguration $configuration,
    ): JsonResponse {
        $run = $queue->handle($attempt, $request->source());

        $attempt->load('challenge');

        // 202: accepted, not done. The response says a run exists, never what it
        // produced — nothing has run yet.
        return response()->json(
            ['run' => $this->toArray($run, $attempt, $configuration)],
            JsonResponse::HTTP_ACCEPTED,
        );
    }

    /**
     * One run, as its own author may see it.
     *
     * Three kinds of thing are in here and only three: what the platform did
     * (status, timing), what the player wrote, and what the player's code
     * returned. The answer key is not among them — `results` carries an expected
     * value only for cases the challenge already publishes as samples (§72).
     *
     * @return array<string, mixed>
     */
    private function toArray(
        ExecutionRun $run,
        ChallengeAttempt $attempt,
        CodeArenaConfiguration $configuration,
    ): array {
        $finished = $run->isSubmittable();

        return [
            'id' => $run->id,
            /*
             * A pending run that has been pending too long is reported as stale
             * rather than corrected. Nothing needs correcting — the player just
             * needs to be told to run again instead of watching a spinner for a
             * worker that died.
             */
            'status' => $run->isStale() ? 'stale' : $run->status,
            'failure_reason' => $run->failure_reason,
            'killed_by' => $run->killed_by,
            'truncated' => $run->truncated,
            'duration_ms' => $run->duration_ms,
            'exit_code' => $run->exit_code,
            'created_at' => $run->created_at->toIso8601String(),
            // Their own code, so a closed attempt can show what was submitted.
            'source' => $run->source,
            'stderr' => $run->stderr,
            /*
             * Graded at read time by the same code the evaluator uses. Storing a
             * verdict on the row would let the two drift, and the one the player
             * saw would not be the one that scored them.
             */
            'results' => $finished ? $configuration->grade($attempt->challenge, $run->observed) : null,
        ];
    }

    private function budget(): int
    {
        return (int) config('devlab.execution.runs_per_attempt', 25);
    }
}
