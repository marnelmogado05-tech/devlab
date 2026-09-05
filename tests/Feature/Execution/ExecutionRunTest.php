<?php

declare(strict_types=1);

use App\Actions\Execution\QueueSubmissionRun;
use App\Actions\Execution\RunSubmission;
use App\Jobs\ExecuteSubmission;
use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\ExecutionRun;
use App\Models\Experience;
use App\Models\User;
use App\Services\Challenge\CodeArena\CodeArenaConfiguration;
use App\Services\Execution\ExecutionOutcome;
use App\Services\Execution\ExecutionRequest;
use App\Services\Execution\SandboxOrchestrator;
use App\Services\Execution\SandboxUnavailable;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

/*
 * The run pipeline: create a row, queue it, carry it through the boundary,
 * record what came back.
 *
 * The orchestrator is faked throughout. What is under test is the APPLICATION's
 * half of ADR 0008 — that a run never touches the attempt, that a platform
 * failure is not a verdict, that a retried job does not run twice. Whether the
 * sandbox actually contains anything is tests/Sandbox's job and needs a real
 * container.
 */

beforeEach(function () {
    $experience = Experience::factory()->published()->create(['slug' => 'code-arena']);

    $this->user = User::factory()->create();

    $this->challenge = Challenge::factory()->published()->for($experience)->create([
        'configuration' => [
            'runtime' => 'php-8.4',
            'entry' => 'solve',
            'signature' => 'function solve(array $n): int',
            'brief' => 'Sum the list.',
            'starter' => "<?php\n\nfunction solve(array \$n): int {}\n",
            'cases' => [
                ['args' => [[1, 2, 3]], 'sample' => true, 'expected' => 6],
                ['args' => [[]], 'sample' => false],
                ['args' => [[7]], 'sample' => false],
            ],
        ],
        'solution' => [
            'expected' => [6, 0, 7],
            'reference' => "<?php\nfunction solve(array \$n): int { return array_sum(\$n); }\n",
        ],
    ]);

    $this->attempt = ChallengeAttempt::factory()
        ->for($this->challenge)
        ->for($this->user)
        ->create(['status' => ChallengeAttempt::STATUS_STARTED]);
});

/** An orchestrator that returns what it is told, and counts its calls. */
function arenaOrchestrator(?ExecutionOutcome $outcome = null, ?Throwable $throws = null): SandboxOrchestrator
{
    return new class($outcome, $throws) implements SandboxOrchestrator
    {
        public int $calls = 0;

        /** @var array<int, ExecutionRequest> */
        public array $requests = [];

        public function __construct(
            private readonly ?ExecutionOutcome $outcome,
            private readonly ?Throwable $throws,
        ) {}

        public function run(ExecutionRequest $request): ExecutionOutcome
        {
            $this->calls++;
            $this->requests[] = $request;

            if ($this->throws !== null) {
                throw $this->throws;
            }

            return $this->outcome ?? new ExecutionOutcome(0, '', '', 5);
        }

        public function available(): bool
        {
            return $this->throws === null;
        }
    };
}

/** Sandbox stdout for a run that returned these values. */
function arenaStdout(array $values): string
{
    $lines = [];

    foreach ($values as $index => $value) {
        $lines[] = (string) json_encode([
            'case' => $index,
            'status' => 'ok',
            'has_value' => true,
            'value' => $value,
            'output' => '',
            'ms' => 2,
        ]);
    }

    return implode("\n", $lines)."\n";
}

describe('starting a run', function () {
    it('records the run and queues it', function () {
        Queue::fake();

        $run = app(QueueSubmissionRun::class)->handle($this->attempt, '<?php // mine');

        expect($run->status)->toBe(ExecutionRun::STATUS_QUEUED)
            ->and($run->user_id)->toBe($this->user->id)
            ->and($run->runtime)->toBe('php-8.4');

        Queue::assertPushed(ExecuteSubmission::class);
    });

    it('does not touch the attempt', function () {
        /*
         * A run is evidence, not an answer. Creating one must not close, score
         * or otherwise age the attempt — the player may run twenty times and
         * submit none of them.
         */
        Queue::fake();

        app(QueueSubmissionRun::class)->handle($this->attempt, '<?php');

        expect($this->attempt->refresh()->status)->toBe(ChallengeAttempt::STATUS_STARTED)
            ->and($this->attempt->score)->toBeNull()
            ->and($this->attempt->completed_at)->toBeNull();
    });

    it('refuses once the attempt has spent its budget', function () {
        /*
         * Without this an attempt is an unbounded compute budget held open for
         * as long as it lives, which is the cheapest way to steal free execution
         * from a platform that gives it away on purpose.
         */
        Queue::fake();
        config()->set('devlab.execution.runs_per_attempt', 2);

        app(QueueSubmissionRun::class)->handle($this->attempt, '<?php');
        app(QueueSubmissionRun::class)->handle($this->attempt, '<?php');

        expect(fn () => app(QueueSubmissionRun::class)->handle($this->attempt, '<?php'))
            ->toThrow(ValidationException::class);

        expect(ExecutionRun::query()->count())->toBe(2);
    });
});

describe('the job', function () {
    it('records what the sandbox returned, and no verdict', function () {
        $orchestrator = arenaOrchestrator(new ExecutionOutcome(
            exitCode: 0,
            stdout: arenaStdout([6, 0, 7]),
            stderr: '',
            durationMs: 91,
        ));

        $this->app->instance(SandboxOrchestrator::class, $orchestrator);

        $run = queuedRun($this);

        (new ExecuteSubmission($run->id))->handle(
            app(RunSubmission::class),
            app(CodeArenaConfiguration::class),
        );

        $run->refresh();

        expect($run->status)->toBe(ExecutionRun::STATUS_FINISHED)
            ->and($run->duration_ms)->toBe(91)
            ->and($run->observed)->toHaveCount(3)
            // Values, never verdicts. Nothing on this row says "passed".
            ->and($run->observed[0]['value'])->toBe(6)
            ->and($run->observed[0])->not->toHaveKey('passed');
    });

    it('sends the harness and never the answer key into the sandbox', function () {
        /*
         * The property ADR 0008 turns on, asserted at the boundary itself rather
         * than at the generator: this is the last point before the payload
         * leaves the application.
         */
        $orchestrator = arenaOrchestrator(new ExecutionOutcome(0, arenaStdout([6, 0, 7]), '', 5));
        $this->app->instance(SandboxOrchestrator::class, $orchestrator);

        $this->challenge->update(['solution' => [
            'expected' => [6, 0, 4242],
            'reference' => "<?php\nfunction solve(array \$n): int { return array_sum(\$n); }\n",
        ]]);

        $run = queuedRun($this);

        (new ExecuteSubmission($run->id))->handle(
            app(RunSubmission::class),
            app(CodeArenaConfiguration::class),
        );

        $request = $orchestrator->requests[0];

        expect($request->submission)->toBe('<?php // mine')
            ->and($request->tests)->not->toContain('4242')
            ->and(base64_decode($request->tests, true))->toBeFalse();
    });

    it('marks a run the platform declined as unavailable, and leaves the attempt open', function () {
        /*
         * S7. A capacity failure is never a verdict: the attempt must survive
         * untouched, and the run must not be submittable.
         */
        $this->app->instance(
            SandboxOrchestrator::class,
            arenaOrchestrator(throws: SandboxUnavailable::poolExhausted()),
        );

        $run = queuedRun($this);

        (new ExecuteSubmission($run->id))->handle(
            app(RunSubmission::class),
            app(CodeArenaConfiguration::class),
        );

        $run->refresh();

        expect($run->status)->toBe(ExecutionRun::STATUS_UNAVAILABLE)
            ->and($run->failure_reason)->toBe(ExecutionRun::REASON_UNAVAILABLE)
            ->and($run->isSubmittable())->toBeFalse()
            ->and($this->attempt->refresh()->status)->toBe(ChallengeAttempt::STATUS_STARTED);
    });

    it('tells a quota refusal apart from the platform having no room', function () {
        // Different events with different answers: one clears when the player's
        // other run finishes, the other does not.
        $this->app->instance(
            SandboxOrchestrator::class,
            arenaOrchestrator(throws: SandboxUnavailable::quotaReached(2)),
        );

        $run = queuedRun($this);

        (new ExecuteSubmission($run->id))->handle(
            app(RunSubmission::class),
            app(CodeArenaConfiguration::class),
        );

        expect($run->refresh()->failure_reason)->toBe(ExecutionRun::REASON_QUOTA);
    });

    it('does not run a second container when the job is delivered twice', function () {
        /*
         * The status guard, which is the reason `tries` above 1 is safe. Two
         * workers handed the same job would otherwise both start a container —
         * pool exhaustion caused by us rather than by an attacker.
         */
        $orchestrator = arenaOrchestrator(new ExecutionOutcome(0, arenaStdout([6, 0, 7]), '', 5));
        $this->app->instance(SandboxOrchestrator::class, $orchestrator);

        $run = queuedRun($this);

        $job = new ExecuteSubmission($run->id);

        $job->handle(
            app(RunSubmission::class),
            app(CodeArenaConfiguration::class),
        );
        $job->handle(
            app(RunSubmission::class),
            app(CodeArenaConfiguration::class),
        );

        expect($orchestrator->calls)->toBe(1);
    });

    it('drops output lines it cannot read rather than repairing them', function () {
        /*
         * Every byte of this came from a process containing hostile code. A line
         * the application cannot read is a case with no result, which grades as
         * a failure — the correct outcome for code that broke its own harness.
         */
        $stdout = "not json\n"
            .'{"case":0,"status":"ok","has_value":true,"value":6}'."\n"
            ."{\"case\":\"one\"}\n"
            .'{"case":2,"status":"ok","has_value":true,"value":7}'."\n";

        $this->app->instance(
            SandboxOrchestrator::class,
            arenaOrchestrator(new ExecutionOutcome(0, $stdout, '', 5)),
        );

        $run = queuedRun($this);

        (new ExecuteSubmission($run->id))->handle(
            app(RunSubmission::class),
            app(CodeArenaConfiguration::class),
        );

        expect($run->refresh()->observed)->toHaveCount(2)
            ->and(array_column($run->observed, 'case'))->toBe([0, 2]);
    });

    it('marks a run unavailable when the job gives up entirely', function () {
        // Otherwise the row sits in `running` forever, telling the player to
        // keep waiting for something nobody is doing.
        $run = queuedRun($this);

        (new ExecuteSubmission($run->id))->failed(new RuntimeException('worker died'));

        expect($run->refresh()->status)->toBe(ExecutionRun::STATUS_UNAVAILABLE);
    });
});

describe('the endpoints', function () {
    it('lets the owner start a run', function () {
        Queue::fake();

        $this->actingAs($this->user)
            ->postJson(route('attempts.runs.store', $this->attempt), ['source' => '<?php'])
            ->assertAccepted()
            ->assertJsonPath('run.status', ExecutionRun::STATUS_QUEUED);
    });

    it('refuses somebody else\'s attempt', function () {
        Queue::fake();

        $this->actingAs(User::factory()->create())
            ->postJson(route('attempts.runs.store', $this->attempt), ['source' => '<?php'])
            ->assertForbidden();

        expect(ExecutionRun::query()->count())->toBe(0);
    });

    it('refuses to run against a closed attempt', function () {
        /*
         * Without the open check, the owner of a finished attempt could keep
         * queueing containers forever: the attempt would refuse to be re-scored
         * and the compute would be spent anyway.
         */
        Queue::fake();

        $this->attempt->update(['status' => ChallengeAttempt::STATUS_COMPLETED]);

        $this->actingAs($this->user)
            ->postJson(route('attempts.runs.store', $this->attempt), ['source' => '<?php'])
            ->assertForbidden();
    });

    it('refuses to run against an experience that does not execute anything', function () {
        /*
         * The route is generic: it takes any attempt the caller owns, and five
         * of the six playable experiences have nothing to run. Before the
         * capability check this was accepted — a container was started and a
         * run was charged against a challenge with no cases, out of a budget
         * belonging to Code Arena (ADR 0009).
         *
         * Nothing escaped even then. This is a spending hole, not a sandbox
         * one, which is why it is asserted on the ROW COUNT and the queue
         * rather than on anything about isolation.
         */
        Queue::fake();

        $elsewhere = Experience::factory()->published()->create(['slug' => 'cursed-code']);

        $attempt = ChallengeAttempt::factory()
            ->for(Challenge::factory()->published()->for($elsewhere))
            ->for($this->user)
            ->create(['status' => ChallengeAttempt::STATUS_STARTED]);

        $this->actingAs($this->user)
            ->postJson(route('attempts.runs.store', $attempt), ['source' => '<?php'])
            ->assertForbidden();

        expect(ExecutionRun::query()->count())->toBe(0);
        Queue::assertNothingPushed();
    });

    it('refuses an experience nobody registered, rather than defaulting to yes', function () {
        // Default-deny. An experience seeded without a capability declaration
        // is a deployment mistake, and the safe reading of a mistake on a gate
        // over compute spend is "no".
        Queue::fake();

        $unknown = Experience::factory()->published()->create(['slug' => 'production-nightmare']);

        $attempt = ChallengeAttempt::factory()
            ->for(Challenge::factory()->published()->for($unknown))
            ->for($this->user)
            ->create(['status' => ChallengeAttempt::STATUS_STARTED]);

        $this->actingAs($this->user)
            ->postJson(route('attempts.runs.store', $attempt), ['source' => '<?php'])
            ->assertForbidden();

        expect(ExecutionRun::query()->count())->toBe(0);
    });

    it('rejects a submission larger than the cap before anything is spent', function () {
        Queue::fake();
        config()->set('devlab.execution.max_source_bytes', 64);

        $this->actingAs($this->user)
            ->postJson(route('attempts.runs.store', $this->attempt), ['source' => str_repeat('a', 65)])
            ->assertUnprocessable();

        expect(ExecutionRun::query()->count())->toBe(0);
        Queue::assertNothingPushed();
    });

    it('reports a finished run graded, and a pending one not at all', function () {
        ExecutionRun::query()->create([
            'challenge_attempt_id' => $this->attempt->id,
            'user_id' => $this->user->id,
            'runtime' => 'php-8.4',
            'source' => '<?php',
            'status' => ExecutionRun::STATUS_QUEUED,
        ]);

        ExecutionRun::query()->create([
            'challenge_attempt_id' => $this->attempt->id,
            'user_id' => $this->user->id,
            'runtime' => 'php-8.4',
            'source' => '<?php',
            'status' => ExecutionRun::STATUS_FINISHED,
            'observed' => [
                ['case' => 0, 'status' => 'ok', 'has_value' => true, 'value' => 6],
                ['case' => 1, 'status' => 'ok', 'has_value' => true, 'value' => 0],
                ['case' => 2, 'status' => 'ok', 'has_value' => true, 'value' => 99],
            ],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('attempts.runs.index', $this->attempt))
            ->assertOk();

        // Newest first: the finished one was created second.
        $response->assertJsonPath('runs.0.results.passed', 2)
            ->assertJsonPath('runs.0.results.total', 3)
            ->assertJsonPath('runs.1.results', null);
    });

    it('shows a hidden case its inputs and never its expectation', function () {
        /*
         * The line the whole experience sits on. Inputs make a failure
         * diagnosable; the expectation would make it guessable, and it is not
         * sent to the client at any point.
         */
        $this->challenge->update(['solution' => [
            'expected' => [6, 0, 4242],
            'reference' => "<?php\nfunction solve(array \$n): int { return array_sum(\$n); }\n",
        ]]);

        ExecutionRun::query()->create([
            'challenge_attempt_id' => $this->attempt->id,
            'user_id' => $this->user->id,
            'runtime' => 'php-8.4',
            'source' => '<?php',
            'status' => ExecutionRun::STATUS_FINISHED,
            'observed' => [
                ['case' => 0, 'status' => 'ok', 'has_value' => true, 'value' => 6],
                ['case' => 1, 'status' => 'ok', 'has_value' => true, 'value' => 0],
                ['case' => 2, 'status' => 'ok', 'has_value' => true, 'value' => 1],
            ],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('attempts.runs.index', $this->attempt))
            ->assertOk();

        expect($response->json('runs.0.results.cases.2.args'))->toBe([[7]])
            ->and($response->json('runs.0.results.cases.2.expected'))->toBeNull()
            ->and($response->getContent())->not->toContain('4242');
    });

    it('refuses to show somebody else\'s runs', function () {
        ExecutionRun::query()->create([
            'challenge_attempt_id' => $this->attempt->id,
            'user_id' => $this->user->id,
            'runtime' => 'php-8.4',
            'source' => '<?php // private',
            'status' => ExecutionRun::STATUS_FINISHED,
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('attempts.runs.index', $this->attempt))
            ->assertForbidden();
    });
});

/** A queued run against the test's attempt, with known source. */
function queuedRun(object $test): ExecutionRun
{
    return ExecutionRun::query()->create([
        'challenge_attempt_id' => $test->attempt->id,
        'user_id' => $test->user->id,
        'runtime' => 'php-8.4',
        'source' => '<?php // mine',
        'status' => ExecutionRun::STATUS_QUEUED,
    ]);
}
