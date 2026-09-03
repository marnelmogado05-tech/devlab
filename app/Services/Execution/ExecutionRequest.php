<?php

namespace App\Services\Execution;

/**
 * One thing to run, and the limits it runs under.
 *
 * The submission and the test bundle are the ONLY things that reach a sandbox.
 * No environment, no credentials, no configuration — S4's control is that there
 * is nothing in there worth exfiltrating, and that starts with what this object
 * is allowed to carry.
 *
 * Immutable, and deliberately without a user id: the orchestrator does not need
 * to know whose code it is running, and a component that cannot identify a user
 * cannot leak one.
 */
final readonly class ExecutionRequest
{
    /**
     * @param  string  $runtime  which sandbox image, e.g. 'php-8.4'
     * @param  string  $submission  the player's code, untrusted
     * @param  string  $tests  the challenge's test bundle
     * @param  array<string, int|float>  $limits  CPU, memory, timeout, PIDs, tmpfs
     */
    public function __construct(
        public string $runtime,
        public string $submission,
        public string $tests,
        public array $limits,
    ) {}

    /**
     * Build one with the configured limits.
     *
     * Limits come from config rather than from a caller, so a challenge cannot
     * ask for more CPU than the platform allows by being written to.
     */
    public static function for(string $runtime, string $submission, string $tests): self
    {
        /** @var array<string, int|float> $limits */
        $limits = config('devlab.execution.limits');

        return new self($runtime, $submission, $tests, $limits);
    }

    public function timeoutSeconds(): int
    {
        return (int) ($this->limits['timeout_seconds'] ?? 10);
    }
}
