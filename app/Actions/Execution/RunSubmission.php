<?php

namespace App\Actions\Execution;

use App\Models\User;
use App\Services\Execution\ExecutionOutcome;
use App\Services\Execution\ExecutionQuota;
use App\Services\Execution\ExecutionRecorder;
use App\Services\Execution\ExecutionRequest;
use App\Services\Execution\SandboxOrchestrator;
use App\Services\Execution\SandboxUnavailable;

/**
 * Run one submission in the sandbox, under quota, and record what it cost.
 *
 * The whole application-side pipeline from ADR 0007, in one place: take a slot,
 * run, record, give the slot back. Nothing calls it yet — no experience submits
 * code — but it is the seam an experience will use, and it exists now so that
 * the first one to arrive inherits the quota and the telemetry rather than
 * discovering it needs them.
 *
 * It deliberately does NOT touch the attempt. Whether an execution result means
 * the challenge was solved is an evaluator's decision, and whether that closes
 * an attempt is SubmitAttempt's — both already have owners, and a third writer
 * on the same row is how double-awards get invented (law 5).
 */
class RunSubmission
{
    public function __construct(
        private readonly SandboxOrchestrator $orchestrator,
        private readonly ExecutionQuota $quota,
        private readonly ExecutionRecorder $recorder,
    ) {}

    /**
     * @throws SandboxUnavailable when the platform could not run it
     */
    public function handle(User $user, ExecutionRequest $request): ExecutionOutcome
    {
        /*
         * The slot is taken BEFORE the run and released in `finally`, so a
         * throwing orchestrator cannot leak one. The quota's TTL is the backstop
         * for the case this cannot cover — the worker dying outright.
         */
        try {
            $this->quota->acquire($user);
        } catch (SandboxUnavailable $e) {
            $this->recorder->recordUnavailable($user, $request, $e->reason());

            throw $e;
        }

        try {
            $outcome = $this->orchestrator->run($request);
        } catch (SandboxUnavailable $e) {
            /*
             * A capacity failure, never a verdict. The caller must leave the
             * attempt OPEN and retry: failing somebody's answer because the
             * platform ran out of room is the platform lying about their work
             * (S7). This action refuses to convert one into the other by not
             * returning an outcome at all — there is no ExecutionOutcome that
             * could describe "we did not try".
             */
            $this->recorder->recordUnavailable($user, $request, $e->reason());

            throw $e;
        } finally {
            $this->quota->release($user);
        }

        $this->recorder->record($user, $request, $outcome);

        return $outcome;
    }
}
