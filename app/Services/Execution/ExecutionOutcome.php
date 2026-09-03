<?php

namespace App\Services\Execution;

/**
 * What a sandbox run produced, in the shape the application is willing to read.
 *
 * Everything here crossed a trust boundary from code a stranger wrote. It is
 * data, never instructions: no field is interpreted as markup, deserialized, or
 * compared against something the user controls to select a code path (S5).
 *
 * Immutable, because a result that can be edited after the fact is a result
 * nobody can audit.
 */
final readonly class ExecutionOutcome
{
    public const KILLED_TIMEOUT = 'timeout';

    public const KILLED_MEMORY = 'memory';

    public const KILLED_OUTPUT = 'output';

    /**
     * @param  int  $exitCode  the sandbox's exit status; 0 does not mean "correct"
     * @param  string  $stdout  sanitised and capped
     * @param  string  $stderr  sanitised and capped
     * @param  int  $durationMs  wall clock, measured by the orchestrator
     * @param  string|null  $killedBy  why the run was stopped, if it was
     * @param  bool  $truncated  whether output hit the cap
     */
    public function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
        public int $durationMs,
        public ?string $killedBy = null,
        public bool $truncated = false,
    ) {}

    /**
     * Whether the process ran to completion under its own steam.
     *
     * Deliberately not called `passed`. Whether a submission is CORRECT is the
     * evaluator's decision from the test results, not a reading of the exit
     * code — a program can exit 0 having done nothing.
     */
    public function completed(): bool
    {
        return $this->killedBy === null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'exit_code' => $this->exitCode,
            'stdout' => $this->stdout,
            'stderr' => $this->stderr,
            'duration_ms' => $this->durationMs,
            'killed_by' => $this->killedBy,
            'truncated' => $this->truncated,
        ];
    }
}
