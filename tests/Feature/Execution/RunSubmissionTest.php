<?php

declare(strict_types=1);

use App\Actions\Execution\RunSubmission;
use App\Models\User;
use App\Services\Execution\ExecutionOutcome;
use App\Services\Execution\ExecutionQuota;
use App\Services\Execution\ExecutionRecorder;
use App\Services\Execution\ExecutionRequest;
use App\Services\Execution\SandboxOrchestrator;
use App\Services\Execution\SandboxUnavailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/*
 * The application-side execution pipeline: quota, run, record, release.
 *
 * The orchestrator is a fake here on purpose. What is under test is what the
 * APPLICATION does around a run — whether a slot leaks, whether a capacity
 * failure becomes a verdict, what reaches the log. Whether the sandbox actually
 * contains anything is the abuse suite's job, and it needs a real container.
 */

/** An orchestrator that returns whatever it is told to. */
function fakeOrchestrator(?ExecutionOutcome $outcome = null, ?Throwable $throws = null): SandboxOrchestrator
{
    return new class($outcome, $throws) implements SandboxOrchestrator
    {
        public int $calls = 0;

        public function __construct(
            private readonly ?ExecutionOutcome $outcome,
            private readonly ?Throwable $throws,
        ) {}

        public function run(ExecutionRequest $request): ExecutionOutcome
        {
            $this->calls++;

            if ($this->throws !== null) {
                throw $this->throws;
            }

            return $this->outcome ?? new ExecutionOutcome(0, 'ok', '', 12);
        }

        public function available(): bool
        {
            return $this->throws === null;
        }
    };
}

function runSubmission(SandboxOrchestrator $orchestrator, ?ExecutionQuota $quota = null): RunSubmission
{
    return new RunSubmission(
        $orchestrator,
        $quota ?? new ExecutionQuota(concurrent: 2, ttlSeconds: 60, prefix: 'devlab:test-run'),
        app(ExecutionRecorder::class),
    );
}

function aRequest(): ExecutionRequest
{
    return ExecutionRequest::for('php-8.4', '<?php // submitted', '<?php // tests');
}

beforeEach(function () {
    try {
        Redis::connection()->ping();
    } catch (Throwable $e) {
        $this->markTestSkipped('Redis is unreachable: '.$e->getMessage());
    }

    $this->user = User::factory()->create();
    $this->quota = new ExecutionQuota(concurrent: 2, ttlSeconds: 60, prefix: 'devlab:test-run');
});

afterEach(function () {
    if (isset($this->quota, $this->user)) {
        Redis::del($this->quota->key($this->user));
    }
});

describe('pool exhaustion', function () {
    it('refuses rather than returning a result when no sandbox is free', function () {
        /*
         * S7. There is deliberately no ExecutionOutcome that could describe "we
         * did not try" — the action throws instead, so a caller cannot mistake a
         * capacity failure for a verdict on somebody's code.
         */
        $orchestrator = fakeOrchestrator(throws: SandboxUnavailable::poolExhausted());

        expect(fn () => runSubmission($orchestrator, $this->quota)->handle($this->user, aRequest()))
            ->toThrow(SandboxUnavailable::class);
    });

    it('gives the slot back when the sandbox was unavailable', function () {
        // Otherwise a run of outages costs a user their whole quota, and the
        // platform denies them service for its own failure.
        $orchestrator = fakeOrchestrator(throws: SandboxUnavailable::poolExhausted());

        try {
            runSubmission($orchestrator, $this->quota)->handle($this->user, aRequest());
        } catch (SandboxUnavailable) {
            // expected
        }

        expect($this->quota->held($this->user))->toBe(0);
    });

    it('gives the slot back when the orchestrator throws something unexpected', function () {
        /*
         * The `finally` covers more than the documented failure. A bug in the
         * client is not a reason for a player to permanently lose a slot.
         */
        $orchestrator = fakeOrchestrator(throws: new RuntimeException('connection reset'));

        try {
            runSubmission($orchestrator, $this->quota)->handle($this->user, aRequest());
        } catch (RuntimeException) {
            // expected
        }

        expect($this->quota->held($this->user))->toBe(0);
    });

    it('does not call the sandbox at all once a user is at their limit', function () {
        // The quota is checked BEFORE the run, so a user over their limit costs
        // the pool nothing.
        $this->quota->acquire($this->user);
        $this->quota->acquire($this->user);

        $orchestrator = fakeOrchestrator();

        try {
            runSubmission($orchestrator, $this->quota)->handle($this->user, aRequest());
        } catch (SandboxUnavailable) {
            // expected
        }

        expect($orchestrator->calls)->toBe(0);
    });

    it('leaves the slot count untouched by a refused acquire', function () {
        $this->quota->acquire($this->user);
        $this->quota->acquire($this->user);

        try {
            runSubmission(fakeOrchestrator(), $this->quota)->handle($this->user, aRequest());
        } catch (SandboxUnavailable) {
            // expected
        }

        // Still exactly the two that are genuinely running, not one fewer.
        expect($this->quota->held($this->user))->toBe(2);
    });

    it('releases the slot after a successful run', function () {
        runSubmission(fakeOrchestrator(), $this->quota)->handle($this->user, aRequest());

        expect($this->quota->held($this->user))->toBe(0);
    });
});

describe('what gets recorded', function () {
    it('records the cost of a finished run', function () {
        $recorded = [];

        Log::listen(function ($message) use (&$recorded) {
            $recorded[] = ['message' => $message->message, 'context' => $message->context];
        });

        $outcome = new ExecutionOutcome(
            exitCode: 1,
            stdout: 'printed',
            stderr: 'complained',
            durationMs: 4321,
            killedBy: ExecutionOutcome::KILLED_TIMEOUT,
            truncated: true,
        );

        runSubmission(fakeOrchestrator($outcome), $this->quota)->handle($this->user, aRequest());

        expect($recorded)->toHaveCount(1)
            ->and($recorded[0]['message'])->toBe('execution.finished')
            ->and($recorded[0]['context'])->toMatchArray([
                'user_id' => $this->user->id,
                'runtime' => 'php-8.4',
                'exit_code' => 1,
                'duration_ms' => 4321,
                'killed_by' => ExecutionOutcome::KILLED_TIMEOUT,
                'truncated' => true,
                'stdout_bytes' => 7,
                'stderr_bytes' => 10,
            ]);
    });

    it('never writes the submission, the tests or the output into a log', function () {
        /*
         * A log line is read by more people and kept longer than an attempt row.
         * A player's code in one is both an answer-key leak and a copy of
         * untrusted bytes somewhere nobody escapes on render.
         */
        $recorded = [];

        Log::listen(function ($message) use (&$recorded) {
            $recorded[] = json_encode($message->context);
        });

        $outcome = new ExecutionOutcome(0, 'SECRET-STDOUT', 'SECRET-STDERR', 10);

        runSubmission(fakeOrchestrator($outcome), $this->quota)->handle($this->user, aRequest());

        $everything = implode("\n", $recorded);

        expect($everything)->not->toContain('SECRET-STDOUT')
            ->and($everything)->not->toContain('SECRET-STDERR')
            ->and($everything)->not->toContain('// submitted')
            ->and($everything)->not->toContain('// tests')
            // The SIZES are the cost signal, and they are safe to keep.
            ->and($everything)->toContain('stdout_bytes');
    });

    it('records the limits in force, so an old line stays readable', function () {
        // A record of a kill means nothing without knowing what it was killed
        // against, and limits change.
        $recorded = [];

        Log::listen(function ($message) use (&$recorded) {
            $recorded[] = $message->context;
        });

        runSubmission(fakeOrchestrator(), $this->quota)->handle($this->user, aRequest());

        expect($recorded[0]['limits'])->toBe(config('devlab.execution.limits'))
            ->and($recorded[0])->toHaveKeys(['user_id', 'duration_ms', 'exit_code', 'killed_by']);
    });

    it('records a declined run differently from a failed one', function () {
        /*
         * They are different events. A run the platform declined must never be
         * counted against the player, and a log that cannot tell them apart
         * makes that distinction unauditable.
         */
        $levels = [];

        Log::listen(function ($message) use (&$levels) {
            $levels[] = $message->level.':'.$message->message;
        });

        try {
            runSubmission(
                fakeOrchestrator(throws: SandboxUnavailable::poolExhausted()),
                $this->quota,
            )->handle($this->user, aRequest());
        } catch (SandboxUnavailable) {
            // expected
        }

        expect($levels)->toBe(['warning:execution.unavailable']);
    });

    it('records a quota refusal as declined, not as a run', function () {
        $this->quota->acquire($this->user);
        $this->quota->acquire($this->user);

        $recorded = [];

        Log::listen(function ($message) use (&$recorded) {
            $recorded[] = $message->context;
        });

        try {
            runSubmission(fakeOrchestrator(), $this->quota)->handle($this->user, aRequest());
        } catch (SandboxUnavailable) {
            // expected
        }

        expect($recorded[0]['reason'])->toBe('quota');
    });
});
