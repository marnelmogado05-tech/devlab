<?php

namespace App\Services\Execution;

/**
 * The orchestrator bound when execution is disabled: it refuses.
 *
 * This is the default, and that is the point. The obvious alternative — a fake
 * that returns a plausible outcome — would mean a misconfigured deployment
 * silently grading submissions against nothing, which is worse than an outage
 * because it looks like it works.
 *
 * Nothing in DevLab executes user code today (law 2). This class is what makes
 * that true by construction rather than by nobody having written the call yet.
 */
class UnavailableOrchestrator implements SandboxOrchestrator
{
    public function run(ExecutionRequest $request): ExecutionOutcome
    {
        throw SandboxUnavailable::notConfigured();
    }

    public function available(): bool
    {
        return false;
    }
}
