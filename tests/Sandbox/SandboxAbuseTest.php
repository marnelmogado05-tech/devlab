<?php

declare(strict_types=1);

use App\Services\Execution\ExecutionOutcome;
use App\Services\Execution\ExecutionRequest;
use App\Services\Execution\HttpOrchestrator;

/*
 * The abuse suite the sandbox threat model requires.
 *
 * Every test here is an attack. They run against a REAL orchestrator and a REAL
 * container — a mocked sandbox would pass all of this and prove nothing, because
 * what is under test is not the code, it is the boundary.
 *
 * Skipped when the orchestrator is unreachable, which is the normal case: it
 * lives behind a compose profile so the default development stack does not gain
 * a container-creating service. To run them:
 *
 *     docker compose --profile execution up -d --build orchestrator
 *     docker build -t devlab-sandbox-php:latest -f docker/sandbox/php/Dockerfile .
 *     DEVLAB_SANDBOX_TESTS=1 php artisan test --testsuite=Sandbox
 *
 * Container start dominates the runtime. On a loaded machine it was measured
 * between 12 and 60 seconds, so `SANDBOX_START_ALLOWANCE_MS` on the orchestrator
 * may need raising before the suite will finish — a run killed while the
 * container was still starting reports an empty result, not a failed control.
 *
 * WHAT THESE TESTS DO NOT PROVE
 * -----------------------------
 * S1 — container escape — is NOT covered here. These assert that the isolation
 * flags produce the behaviour they should, which is a different claim from "the
 * boundary holds against a kernel exploit". S1 depends on gVisor, and gVisor
 * does not run on Docker Desktop, so on a typical development machine this suite
 * exercises the runc fallback that the threat model already calls materially
 * weaker. A deployment claiming S1 needs this suite green on a Linux host with
 * runsc, and `runtime()` below is how you check which one you actually ran.
 */

beforeEach(function () {
    /*
     * Opt-in, not "run whenever something answers on 8090". Every test here
     * starts a real container, which costs seconds on a Linux host and can cost
     * a minute on a loaded developer machine. A suite that expensive must be
     * asked for — reachability is not consent.
     */
    if (! env('DEVLAB_SANDBOX_TESTS')) {
        test()->markTestSkipped('Set DEVLAB_SANDBOX_TESTS=1 to run the sandbox abuse suite.');
    }

    config(['devlab.execution.enabled' => true]);

    $this->orchestrator = new HttpOrchestrator(
        (string) env('DEVLAB_ORCHESTRATOR_URL', 'http://localhost:8090'),
    );

    if (! $this->orchestrator->available()) {
        test()->markTestSkipped('The execution orchestrator is not running.');
    }
});

/** Run a PHP submission through the sandbox. */
function attack(string $code): ExecutionOutcome
{
    return test()->orchestrator->run(
        ExecutionRequest::for('php-8.4', '', $code),
    );
}

it('reports which isolation runtime it is actually using', function () {
    /*
     * Not an assertion about which one is right — it is a record. A suite that
     * passed under runc and was reported as proving gVisor's isolation would be
     * the most dangerous kind of green.
     */
    $runtime = $this->orchestrator->runtime();

    expect($runtime)->toBeString();

    if ($runtime === 'default') {
        // Deliberately not a failure: the fallback is a supported development
        // configuration. It is a statement in the output so nobody reads this
        // suite as evidence for S1.
        fwrite(STDERR, "\n  [sandbox] runtime=runc — S1 (container escape) is NOT covered by this run.\n");
    }
});

it('runs an ordinary submission', function () {
    // The control case. Without it, every test below could be passing because
    // nothing runs at all.
    $outcome = attack('<?php echo "hello";');

    expect($outcome->stdout)->toContain('hello')
        ->and($outcome->completed())->toBeTrue()
        ->and($outcome->exitCode)->toBe(0);
});

it('survives a fork bomb', function () {
    // The first thing anyone tries. The PID limit is what stops it.
    $outcome = attack(<<<'PHP'
        <?php
        while (true) {
            @shell_exec('sh -c "while true; do sh -c : & done" &');
        }
        PHP);

    expect($outcome->completed())->toBeFalse();
})->group('abuse');

it('survives a memory bomb', function () {
    // Memory equals memory-swap, so this cannot be evaded by swapping: the
    // container is OOM-killed rather than dragging the host into swap.
    $outcome = attack(<<<'PHP'
        <?php
        ini_set('memory_limit', '-1');
        $held = [];
        while (true) {
            $held[] = str_repeat('x', 1024 * 1024);
        }
        PHP);

    expect($outcome->completed())->toBeFalse()
        ->and($outcome->killedBy)->toBeIn([ExecutionOutcome::KILLED_MEMORY, ExecutionOutcome::KILLED_TIMEOUT]);
})->group('abuse');

it('stops an infinite loop at the deadline', function () {
    $outcome = attack('<?php while (true) { $x = 1; }');

    /*
     * That it was STOPPED is the claim. There was a wall-clock bound here too,
     * and it was wrong: the number encoded an assumption about how long a
     * container takes to start, so a slow machine failed the test for the one
     * reason that is not a security property. The orchestrator's deadline is
     * what bounds this, and it is configurable per deployment.
     */
    expect($outcome->completed())->toBeFalse()
        ->and($outcome->killedBy)->toBe(ExecutionOutcome::KILLED_TIMEOUT);
})->group('abuse');

it('caps an output flood instead of reading it forever', function () {
    /*
     * The S5 control, end to end. A program printing infinitely would fill the
     * orchestrator's memory if the cap were applied to a finished string, so
     * what is being tested is that the READ stops, not that the result is short.
     */
    $outcome = attack('<?php while (true) { echo str_repeat("x", 4096); }');

    expect($outcome->truncated)->toBeTrue()
        ->and(strlen($outcome->stdout))
        ->toBeLessThan((int) config('devlab.execution.output.max_bytes') + 1024);
})->group('abuse');

it('cannot reach the network', function () {
    // S4. Not a firewall rule — `--network none` means no interface exists.
    $outcome = attack(<<<'PHP'
        <?php
        $result = @file_get_contents('http://example.com', false, stream_context_create([
            'http' => ['timeout' => 3],
        ]));
        echo $result === false ? 'NO-NETWORK' : 'REACHED';
        PHP);

    expect($outcome->stdout)->toContain('NO-NETWORK');
})->group('abuse');

it('cannot write outside its tmpfs', function () {
    $outcome = attack(<<<'PHP'
        <?php
        echo @file_put_contents('/etc/passwd', 'x') === false ? 'READ-ONLY' : 'WROTE';
        echo ' ';
        echo @file_put_contents('/srv/run.php', 'x') === false ? 'ENTRYPOINT-SAFE' : 'OVERWROTE-ENTRYPOINT';
        PHP);

    expect($outcome->stdout)->toContain('READ-ONLY')
        ->and($outcome->stdout)->toContain('ENTRYPOINT-SAFE');
})->group('abuse');

it('runs as an unprivileged user with no capabilities', function () {
    $outcome = attack(<<<'PHP'
        <?php
        echo 'uid=', trim((string) @shell_exec('id -u')), ' ';
        echo 'caps=', trim((string) @file_get_contents('/proc/self/status'));
        PHP);

    expect($outcome->stdout)->toContain('uid=65534')
        // CapEff is the effective capability set. All zeroes is --cap-drop ALL
        // having actually applied rather than having been asked for.
        ->and($outcome->stdout)->toMatch('/CapEff:\s+0000000000000000/');
})->group('abuse');

it('carries no credentials into the sandbox', function () {
    // S4. There is nothing in there worth exfiltrating, and this is the
    // assertion that keeps it that way as the image changes.
    $outcome = attack(<<<'PHP'
        <?php
        $leaks = array_filter(
            array_keys($_ENV + $_SERVER),
            fn (string $key) => (bool) preg_match('/DB_|REDIS|PASSWORD|SECRET|APP_KEY|TOKEN/i', $key),
        );

        echo $leaks === [] ? 'NO-SECRETS' : 'LEAKED: '.implode(',', $leaks);
        PHP);

    expect($outcome->stdout)->toContain('NO-SECRETS');
})->group('abuse');

it('stores hostile output inert rather than interpreting it', function () {
    // S5, on the way back. Terminal escapes and markup are stripped or stored
    // as text; neither reaches a reader able to act on them.
    $outcome = attack(<<<'PHP'
        <?php
        echo "\e[2J\e[1;1H";
        echo "<script>alert(1)</script>";
        echo "\0";
        PHP);

    expect($outcome->stdout)->not->toContain("\e")
        ->and($outcome->stdout)->not->toContain("\0")
        // HTML survives as text: escaping belongs where it is rendered, and
        // React escapes it there.
        ->and($outcome->stdout)->toContain('<script>');
})->group('abuse');

it('does not let one submission see another', function () {
    /*
     * One container per execution, destroyed after. A reused sandbox would be a
     * channel between two strangers' submissions.
     */
    attack('<?php file_put_contents("/tmp/left-behind", "secret");');

    $second = attack('<?php echo @file_get_contents("/tmp/left-behind") === false ? "CLEAN" : "FOUND";');

    expect($second->stdout)->toContain('CLEAN');
})->group('abuse');
