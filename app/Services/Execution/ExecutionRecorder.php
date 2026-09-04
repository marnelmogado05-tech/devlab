<?php

namespace App\Services\Execution;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Records what one execution cost.
 *
 * Two audiences, which is why it exists at all (§42, sandbox threat model S3):
 *
 *  - **Abuse.** A user whose submissions are consistently killed by the deadline
 *    or the memory limit is either stuck or probing, and neither is visible from
 *    an attempt row that only says "failed".
 *  - **Cost.** Execution is the first thing in DevLab that spends real money per
 *    request. A platform that cannot say what it spent cannot notice when that
 *    changes.
 *
 * This lives on the APPLICATION side rather than in the orchestrator, and that
 * is deliberate. The orchestrator has no database and no idea whose code it ran
 * — ADR 0007 keeps it that way on purpose, so it is not the component that can
 * attribute anything. Attribution belongs where identity already is.
 */
class ExecutionRecorder
{
    /**
     * What actually gets written.
     *
     * Note what is NOT here: the submission, the test bundle, and the output.
     * A log line is read by more people and kept longer than an attempt row, and
     * a player's code in it is both an answer-key leak and a copy of untrusted
     * bytes in a place nobody escapes on render.
     */
    public function record(User $user, ExecutionRequest $request, ExecutionOutcome $outcome): void
    {
        Log::channel(config('devlab.execution.log_channel'))->info('execution.finished', [
            'user_id' => $user->id,
            'runtime' => $request->runtime,
            'exit_code' => $outcome->exitCode,
            'duration_ms' => $outcome->durationMs,
            'killed_by' => $outcome->killedBy,
            'truncated' => $outcome->truncated,
            /*
             * Sizes rather than contents. How much a submission printed is the
             * cost and abuse signal; what it printed is not this file's business.
             */
            'stdout_bytes' => strlen($outcome->stdout),
            'stderr_bytes' => strlen($outcome->stderr),
            /*
             * The limits in force, so a line stays readable after they change.
             * A record of a kill is not interpretable without knowing what it
             * was killed against.
             */
            'limits' => $request->limits,
        ]);
    }

    /**
     * Record an execution that never ran.
     *
     * Separate from a failed one, because they are different events: this is the
     * platform declining, and a run that the platform declined must never be
     * counted against the player (S7).
     */
    public function recordUnavailable(User $user, ExecutionRequest $request, string $reason): void
    {
        Log::channel(config('devlab.execution.log_channel'))->warning('execution.unavailable', [
            'user_id' => $user->id,
            'runtime' => $request->runtime,
            'reason' => $reason,
        ]);
    }
}
