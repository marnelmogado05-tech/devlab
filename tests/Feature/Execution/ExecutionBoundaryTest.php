<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Execution\ExecutionOutcome;
use App\Services\Execution\ExecutionQuota;
use App\Services\Execution\ExecutionRequest;
use App\Services\Execution\OutputSanitiser;
use App\Services\Execution\SandboxOrchestrator;
use App\Services\Execution\SandboxUnavailable;
use App\Services\Execution\UnavailableOrchestrator;
use Illuminate\Support\Facades\Redis;

/*
 * Phase 3's boundary, before there is anything behind it.
 *
 * Nothing here executes anything, and the first test is the one that says so:
 * the container's default orchestrator refuses. These are the controls for S5
 * and S7 from docs/security/sandbox-threat-model.md, written before the runner
 * so that the first sandbox to exist is built against controls that already
 * pass their own tests.
 */

describe('the default binding', function () {
    it('refuses to run anything', function () {
        /*
         * Law 2, as a test rather than as a convention. The obvious alternative
         * — a stub returning a plausible outcome — would let a misconfigured
         * deployment grade submissions against nothing, which looks like it
         * works and is therefore worse than an outage.
         */
        $orchestrator = app(SandboxOrchestrator::class);

        expect($orchestrator)->toBeInstanceOf(UnavailableOrchestrator::class)
            ->and($orchestrator->available())->toBeFalse();

        expect(fn () => $orchestrator->run(ExecutionRequest::for('php-8.4', '<?php', 'tests')))
            ->toThrow(SandboxUnavailable::class);
    });

    it('is disabled in configuration', function () {
        expect(config('devlab.execution.enabled'))->toBeFalse();
    });

    it('takes its limits from configuration rather than from a caller', function () {
        // A challenge must not be able to ask for more CPU than the platform
        // allows by being written to.
        $request = ExecutionRequest::for('php-8.4', '<?php', 'tests');

        expect($request->limits)->toBe(config('devlab.execution.limits'))
            ->and($request->timeoutSeconds())->toBe(config('devlab.execution.limits.timeout_seconds'));
    });

    it('carries nothing that identifies a user', function () {
        /*
         * S4. The orchestrator does not need to know whose code it is running,
         * and a component that cannot identify a user cannot leak one.
         */
        $properties = array_map(
            fn (ReflectionProperty $property) => $property->getName(),
            (new ReflectionClass(ExecutionRequest::class))->getProperties(),
        );

        expect($properties)->toBe(['runtime', 'submission', 'tests', 'limits']);
    });
});

describe('output capping', function () {
    it('stops accepting output at the cap', function () {
        /*
         * The S5 control, and the reason it is a streaming API. A program
         * printing infinitely fills the reader's memory long before anyone gets
         * to call substr on a complete string — truncating at the end is not a
         * control, it is a comment.
         */
        $sanitiser = new OutputSanitiser(10);

        expect($sanitiser->append('12345'))->toBeTrue()
            ->and($sanitiser->append('67890 and more'))->toBeFalse()
            ->and($sanitiser->truncated())->toBeTrue()
            ->and($sanitiser->value())->toBe('1234567890'.OutputSanitiser::TRUNCATION_NOTICE);
    });

    it('refuses everything after the cap, however much more arrives', function () {
        $sanitiser = new OutputSanitiser(4);

        $sanitiser->append('aaaaaaaa');

        expect($sanitiser->append('bbbb'))->toBeFalse()
            ->and($sanitiser->value())->toBe('aaaa'.OutputSanitiser::TRUNCATION_NOTICE);
    });

    it('says when output was cut, so a reader knows there was more', function () {
        $short = new OutputSanitiser(1024);
        $short->append('all of it');

        expect($short->truncated())->toBeFalse()
            ->and($short->value())->toBe('all of it');
    });

    it('strips terminal escapes, because a maintainer reading logs is a reader', function () {
        // ANSI escapes can rewrite a terminal's display, move the cursor and
        // hide text. `cat` of a stored result must not be able to lie.
        $sanitiser = new OutputSanitiser(1024);
        $sanitiser->append("\e[31mred\e[0m\e[2J");

        expect($sanitiser->value())->toBe('[31mred[0m[2J');
    });

    it('keeps the whitespace that makes output readable', function () {
        // Removing tabs and newlines would make a stack trace unreadable to
        // defend against nothing.
        $sanitiser = new OutputSanitiser(1024);
        $sanitiser->append("line one\n\tindented\r\nline two");

        expect($sanitiser->value())->toBe("line one\n\tindented\r\nline two");
    });

    it('removes a null byte', function () {
        $sanitiser = new OutputSanitiser(1024);
        $sanitiser->append("before\0after");

        expect($sanitiser->value())->toBe('beforeafter');
    });

    it('repairs invalid encoding rather than letting it reach the driver', function () {
        /*
         * Output is bytes; the column is text. Storing an invalid sequence
         * throws at the database driver, which turns hostile output into an
         * application error — the exact outcome the sandbox exists to prevent.
         */
        $sanitiser = new OutputSanitiser(1024);
        $sanitiser->append("valid \xC3\x28 invalid");

        expect(mb_check_encoding($sanitiser->value(), 'UTF-8'))->toBeTrue();
    });

    it('does not corrupt what follows a multi-byte character cut at the cap', function () {
        // The cap counts bytes, so it can land inside a character. Replacing the
        // broken sequence keeps the rest of the string valid.
        $sanitiser = new OutputSanitiser(4);
        $sanitiser->append('aaa€');

        expect(mb_check_encoding($sanitiser->value(), 'UTF-8'))->toBeTrue();
    });

    it('leaves HTML alone, because escaping belongs where it is rendered', function () {
        /*
         * Escaping here would double-encode in a JSON payload and leave the
         * value wrong everywhere else. React escapes on render; this keeps the
         * stored value true.
         */
        $sanitiser = new OutputSanitiser(1024);
        $sanitiser->append('<script>alert(1)</script>');

        expect($sanitiser->value())->toBe('<script>alert(1)</script>');
    });
});

describe('the concurrency quota', function () {
    beforeEach(function () {
        // Against a REAL Redis: the atomicity of INCR is the whole control, and
        // an array driver would pass this suite while proving nothing. Skipped
        // where Redis is unreachable, which is the normal case on a host without
        // phpredis; CI and the container both have it.
        try {
            Redis::connection()->ping();
        } catch (Throwable $e) {
            $this->markTestSkipped('Redis is unreachable: '.$e->getMessage());
        }

        $this->quota = new ExecutionQuota(concurrent: 2, ttlSeconds: 120, prefix: 'devlab:test-execution');
        $this->user = User::factory()->create();
    });

    afterEach(function () {
        if (isset($this->quota)) {
            Redis::del($this->quota->key($this->user));
        }
    });

    it('allows a user up to their limit', function () {
        $this->quota->acquire($this->user);
        $this->quota->acquire($this->user);

        expect($this->quota->held($this->user))->toBe(2);
    });

    it('refuses the one past the limit', function () {
        /*
         * S7. Rate limiting bounds how FAST someone can submit; this bounds how
         * much of the pool they hold at any instant. Thirty a minute is fine if
         * each takes a second and starves everyone if each takes ten.
         */
        $this->quota->acquire($this->user);
        $this->quota->acquire($this->user);

        expect(fn () => $this->quota->acquire($this->user))
            ->toThrow(SandboxUnavailable::class);
    });

    it('does not leave a slot held by a refused acquire', function () {
        // The atomic increment has to be undone, or a user who hits their limit
        // once is permanently one slot poorer.
        $this->quota->acquire($this->user);
        $this->quota->acquire($this->user);

        try {
            $this->quota->acquire($this->user);
        } catch (SandboxUnavailable) {
            // expected
        }

        expect($this->quota->held($this->user))->toBe(2);
    });

    it('gives a slot back', function () {
        $this->quota->acquire($this->user);
        $this->quota->release($this->user);

        expect($this->quota->held($this->user))->toBe(0);

        // And the freed slot is usable rather than merely counted.
        $this->quota->acquire($this->user);
        expect($this->quota->held($this->user))->toBe(1);
    });

    it('counts each user separately', function () {
        $other = User::factory()->create();

        $this->quota->acquire($this->user);
        $this->quota->acquire($this->user);

        // At their own limit, and it says nothing about anybody else's.
        $this->quota->acquire($other);

        expect($this->quota->held($other))->toBe(1);

        Redis::del($this->quota->key($other));
    });

    it('expires a slot, so a dead worker cannot leak one forever', function () {
        /*
         * Without the TTL a crash mid-run leaks a slot per occurrence, and a
         * user who hit that twice could never run anything again — a denial of
         * service the platform inflicts on itself.
         */
        $this->quota->acquire($this->user);

        expect(Redis::ttl($this->quota->key($this->user)))
            ->toBeGreaterThan(0)
            ->toBeLessThanOrEqual(120);
    });

    it('never lets releasing a slot mask the failure that caused it', function () {
        // release() runs in a finally. An exception there would replace whatever
        // real error was already propagating.
        $quota = new ExecutionQuota(concurrent: 1, ttlSeconds: 5, prefix: 'devlab:test-execution');

        expect(fn () => $quota->release(User::factory()->create()))->not->toThrow(Throwable::class);
    });
});

describe('the outcome', function () {
    it('does not call a zero exit code a pass', function () {
        /*
         * Whether a submission is CORRECT is the evaluator's decision from the
         * test results. A program can exit 0 having done nothing at all.
         */
        $outcome = new ExecutionOutcome(exitCode: 0, stdout: '', stderr: '', durationMs: 12);

        expect(method_exists($outcome, 'passed'))->toBeFalse()
            ->and($outcome->completed())->toBeTrue();
    });

    it('reports a killed run as not completed', function () {
        $outcome = new ExecutionOutcome(
            exitCode: 137,
            stdout: '',
            stderr: '',
            durationMs: 10_000,
            killedBy: ExecutionOutcome::KILLED_TIMEOUT,
        );

        expect($outcome->completed())->toBeFalse()
            ->and($outcome->toArray()['killed_by'])->toBe('timeout');
    });
});
