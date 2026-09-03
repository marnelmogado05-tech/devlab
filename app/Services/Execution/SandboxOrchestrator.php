<?php

namespace App\Services\Execution;

/**
 * The only way the application can cause code to run.
 *
 * One operation, deliberately. ADR 0007 splits privilege three ways, and this
 * interface is the seam between the first two: the application holds the
 * database and reaches the runtime only through here, while whatever implements
 * it holds container-creation privilege and no credentials.
 *
 * Implementations must not throw for a program that misbehaves — a timeout, an
 * OOM kill or a non-zero exit are OUTCOMES, and the whole point of the sandbox
 * is that they are ordinary. {@see SandboxUnavailable} is for the platform
 * failing, never for the submission failing.
 */
interface SandboxOrchestrator
{
    /**
     * @throws SandboxUnavailable when no sandbox could run it
     */
    public function run(ExecutionRequest $request): ExecutionOutcome;

    /**
     * Whether a submission could be run right now.
     *
     * Used to decide whether to accept work, not to decide a verdict.
     */
    public function available(): bool;
}
