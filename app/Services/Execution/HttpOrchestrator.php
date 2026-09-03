<?php

namespace App\Services\Execution;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Talks to the orchestrator service over the internal network.
 *
 * This is the application's entire relationship with the container runtime
 * (ADR 0007). It can ask for one payload to be run. It cannot create a
 * container, inspect one, list them, or reach the Docker socket — those live on
 * the other side of an HTTP boundary held by a service with no database
 * credentials.
 *
 * Everything that comes back crosses a trust boundary. It is re-capped and
 * re-sanitised here rather than trusted: the orchestrator already caps output,
 * and doing it twice costs nothing while removing the assumption that the
 * component nearest the hostile code got it right (S5).
 */
class HttpOrchestrator implements SandboxOrchestrator
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $connectTimeout = 2,
    ) {}

    public static function fromConfig(): self
    {
        return new self((string) config('devlab.execution.orchestrator_url'));
    }

    public function run(ExecutionRequest $request): ExecutionOutcome
    {
        try {
            $response = Http::connectTimeout($this->connectTimeout)
                /*
                 * Longer than the sandbox's own limit AND longer than the
                 * orchestrator's deadline, which includes its container-start
                 * allowance. A timeout here means the orchestrator itself is
                 * wedged — a platform failure — rather than a slow submission,
                 * which is an outcome.
                 */
                ->timeout($request->timeoutSeconds() + (int) config('devlab.execution.orchestrator_overhead_seconds'))
                ->asJson()
                ->post($this->baseUrl.'/run', [
                    'runtime' => $request->runtime,
                    'submission' => $request->submission,
                    'tests' => $request->tests,
                    'limits' => $request->limits,
                    'outputMaxBytes' => (int) config('devlab.execution.output.max_bytes'),
                ]);
        } catch (ConnectionException $e) {
            throw SandboxUnavailable::poolExhausted();
        }

        if ($response->failed()) {
            throw SandboxUnavailable::poolExhausted();
        }

        return $this->toOutcome($response->json());
    }

    public function available(): bool
    {
        try {
            return Http::connectTimeout($this->connectTimeout)
                ->timeout($this->connectTimeout)
                ->get($this->baseUrl.'/health')
                ->successful();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Which isolation runtime the orchestrator reports.
     *
     * Exposed so a deployment can be asked rather than assumed. `default` means
     * runc, which the threat model says is materially weaker than gVisor — the
     * kind of thing nobody should have to read a compose file to discover.
     */
    public function runtime(): ?string
    {
        try {
            $response = Http::connectTimeout($this->connectTimeout)
                ->timeout($this->connectTimeout)
                ->get($this->baseUrl.'/health');

            return $response->successful() ? (string) $response->json('runtime') : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function toOutcome(?array $payload): ExecutionOutcome
    {
        $payload ??= [];

        $stdout = OutputSanitiser::fromConfig();
        $stdout->append(is_string($payload['stdout'] ?? null) ? $payload['stdout'] : '');

        $stderr = OutputSanitiser::fromConfig();
        $stderr->append(is_string($payload['stderr'] ?? null) ? $payload['stderr'] : '');

        return new ExecutionOutcome(
            exitCode: (int) ($payload['exit_code'] ?? -1),
            stdout: $stdout->value(),
            stderr: $stderr->value(),
            durationMs: (int) ($payload['duration_ms'] ?? 0),
            /*
             * Whitelisted rather than passed through. `killed_by` decides how a
             * result is presented, and a value chosen by something adjacent to
             * hostile code should not be able to name a state the application
             * did not define (S5).
             */
            killedBy: $this->killedBy($payload['killed_by'] ?? null),
            truncated: ($payload['truncated'] ?? false) === true || $stdout->truncated() || $stderr->truncated(),
        );
    }

    private function killedBy(mixed $value): ?string
    {
        $known = [
            ExecutionOutcome::KILLED_TIMEOUT,
            ExecutionOutcome::KILLED_MEMORY,
            ExecutionOutcome::KILLED_OUTPUT,
        ];

        return is_string($value) && in_array($value, $known, true) ? $value : null;
    }
}
