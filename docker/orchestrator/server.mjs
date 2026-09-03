/**
 * The DevLab execution orchestrator.
 *
 * The only component permitted to create a sandbox (ADR 0007). It holds
 * container-creation privilege and NOTHING else: no database, no cache, no
 * credentials, no knowledge of which user submitted what.
 *
 * Zero dependencies, on purpose. This is the component whose compromise matters
 * most, so it should be readable end to end in one sitting, and an npm tree is
 * the opposite of that. Node's standard library plus the `docker` CLI is all it
 * gets.
 *
 * Controls implemented here map to docs/security/sandbox-threat-model.md:
 *
 *   S1  isolation flags on every run, and the runtime chosen at start-up
 *   S3  CPU, memory, PID, tmpfs and TWO timeouts
 *   S4  --network none, no mounts, no environment
 *   S5  output capped while reading, and the container killed when it overflows
 */

import { spawn } from 'node:child_process';
import { createServer } from 'node:http';

const PORT = Number(process.env.ORCHESTRATOR_PORT ?? 8090);

/**
 * Which container runtime to use.
 *
 * gVisor is what the threat model's S1 control actually depends on. It is not
 * present everywhere — notably not on Docker Desktop — so the runtime is chosen
 * at start-up and REPORTED, rather than assumed. A deployment running without it
 * is running a materially weaker boundary and should be able to see that from
 * the health endpoint rather than by reading a compose file.
 */
const RUNTIME = process.env.SANDBOX_RUNTIME ?? '';

/**
 * How long a container may take to START before its own clock begins.
 *
 * The submission's timeout budgets the code. This budgets everything before it:
 * image resolution, container creation, and the interpreter reaching the first
 * line. On a Linux host that is tens of milliseconds. On Docker Desktop it was
 * measured at ~12 SECONDS, which is longer than most submissions are allowed to
 * run — so a deadline of `timeout + 2` killed every run before the code started,
 * and reported the killing as the submission's fault.
 *
 * Separated from the timeout rather than folded into it, because they answer
 * different questions: one is "is this program stuck", the other is "is this
 * machine slow". Conflating them makes a slow host look like hostile code.
 */
const START_ALLOWANCE_MS = Number(
    process.env.SANDBOX_START_ALLOWANCE_MS ?? 25_000,
);

/** Images this orchestrator will run. Anything else is refused by name. */
const RUNTIMES = {
    'php-8.4': 'devlab-sandbox-php:latest',
};

/**
 * Read a process stream, stopping at a byte cap.
 *
 * The cap is enforced HERE, while reading — not by truncating a finished string.
 * A program printing infinitely fills this process's memory long before anyone
 * gets to slice the result, so the only place a cap works is the read loop.
 *
 * Resolves with `{ text, truncated }` and calls `onOverflow` the first time the
 * limit is passed, so the caller can kill the container rather than politely
 * waiting for a program that will never stop.
 */
function readCapped(stream, maxBytes, onOverflow) {
    return new Promise((resolve) => {
        const chunks = [];
        let size = 0;
        let truncated = false;

        stream.on('data', (chunk) => {
            if (truncated) {
                return;
            }

            const remaining = maxBytes - size;

            if (chunk.length < remaining) {
                chunks.push(chunk);
                size += chunk.length;

                return;
            }

            chunks.push(chunk.subarray(0, Math.max(0, remaining)));
            truncated = true;
            onOverflow();
        });

        stream.on('end', () =>
            resolve({
                text: Buffer.concat(chunks).toString('utf8'),
                truncated,
            }),
        );
        stream.on('error', () =>
            resolve({
                text: Buffer.concat(chunks).toString('utf8'),
                truncated,
            }),
        );
    });
}

/**
 * The flags that make a container a boundary rather than a convenience.
 *
 * Every one of these is a control from the threat model. They are assembled in
 * one place so a review can read the whole isolation posture without chasing it
 * through call sites.
 */
function dockerArguments(image, limits, payload) {
    const args = ['run', '--rm'];

    if (RUNTIME) {
        args.push(`--runtime=${RUNTIME}`);
    }

    args.push(
        // S4: no network at all. Not a firewall rule — no interface exists.
        '--network',
        'none',

        // S3: memory equal to memory-swap, so the limit cannot be evaded by
        // swapping. The container is OOM-killed rather than slowed to a crawl.
        '--memory',
        `${limits.memory_mb}m`,
        '--memory-swap',
        `${limits.memory_mb}m`,
        '--cpus',
        String(limits.cpu_cores),
        '--pids-limit',
        String(limits.processes),

        // S3/S4: nothing writable except a small tmpfs, and nothing executable
        // in it. No host path is mounted anywhere.
        '--read-only',
        '--tmpfs',
        `/tmp:rw,noexec,nosuid,size=${limits.tmpfs_mb}m`,

        // S1: no capabilities, no way to gain any, not root.
        '--cap-drop',
        'ALL',
        '--security-opt',
        'no-new-privileges',
        '--user',
        '65534:65534',

        image,

        /*
         * The payload travels as a base64 argument rather than on stdin.
         *
         * stdin was the obvious choice and does not work: `docker run -i`
         * spawned from Node never sees the pipe close, so the CLI holds the
         * attach open and every run reaches its deadline having already
         * finished. An argument removes the channel rather than working around
         * it, and base64 removes every quoting question with it.
         */
        payload,
    );

    return args;
}

/**
 * Run one submission.
 *
 * The payload reaches the sandbox as an argument rather than through a mounted
 * file, so there is no host path involved at any point.
 */
async function run({ runtime, submission, tests, limits, outputMaxBytes }) {
    const image = RUNTIMES[runtime];

    if (!image) {
        return { error: `Unknown runtime '${runtime}'.` };
    }

    const payload = Buffer.from(JSON.stringify({ submission, tests })).toString(
        'base64',
    );

    const started = Date.now();
    const child = spawn('docker', dockerArguments(image, limits, payload));

    let killedBy = null;

    const kill = (reason) => {
        if (killedBy === null) {
            killedBy = reason;
        }

        child.kill('SIGKILL');
    };

    /*
     * S3, the second timeout. The sandbox has its own limit; this one is held
     * out here and destroys the container when it passes. A process that ignores
     * its own timeout is exactly what we are defending against, so the enforcing
     * timer must not live inside it.
     */
    const deadline = setTimeout(
        () => kill('timeout'),
        START_ALLOWANCE_MS + limits.timeout_seconds * 1000,
    );

    const stdout = readCapped(child.stdout, outputMaxBytes, () =>
        kill('output'),
    );
    const stderr = readCapped(child.stderr, outputMaxBytes, () =>
        kill('output'),
    );

    const exitCode = await new Promise((resolve) => {
        child.on('error', () => resolve(-1));
        child.on('close', (code) => resolve(code ?? -1));
    });

    clearTimeout(deadline);

    const out = await stdout;
    const err = await stderr;

    /*
     * 137 is SIGKILL, which for a container under a memory limit is almost
     * always the OOM killer. Reported rather than inferred as success: a run
     * that was killed did not complete, whatever it printed on the way.
     */
    if (killedBy === null && exitCode === 137) {
        killedBy = 'memory';
    }

    return {
        exit_code: exitCode,
        stdout: out.text,
        stderr: err.text,
        duration_ms: Date.now() - started,
        killed_by: killedBy,
        truncated: out.truncated || err.truncated,
    };
}

function readBody(request) {
    return new Promise((resolve, reject) => {
        const chunks = [];
        let size = 0;

        request.on('data', (chunk) => {
            size += chunk.length;

            // A submission is code, not a file upload. Anything this large is
            // an attack on the orchestrator rather than an answer.
            if (size > 1_000_000) {
                reject(new Error('Payload too large.'));

                return;
            }

            chunks.push(chunk);
        });
        request.on('end', () =>
            resolve(Buffer.concat(chunks).toString('utf8')),
        );
        request.on('error', reject);
    });
}

const server = createServer(async (request, response) => {
    const json = (status, body) => {
        response.writeHead(status, { 'content-type': 'application/json' });
        response.end(JSON.stringify(body));
    };

    if (request.method === 'GET' && request.url === '/health') {
        return json(200, {
            ok: true,
            start_allowance_ms: START_ALLOWANCE_MS,
            // Named so a deployment can see which boundary it is actually
            // running without reading a compose file.
            runtime: RUNTIME || 'default',
            runtimes: Object.keys(RUNTIMES),
        });
    }

    if (request.method !== 'POST' || request.url !== '/run') {
        return json(404, { error: 'Not found.' });
    }

    try {
        const payload = JSON.parse(await readBody(request));
        const outcome = await run(payload);

        return json(outcome.error ? 422 : 200, outcome);
    } catch (error) {
        return json(400, { error: String(error?.message ?? error) });
    }
});

server.listen(PORT, () => {
    process.stdout.write(
        `orchestrator listening on ${PORT}, runtime=${RUNTIME || 'default'}\n`,
    );
});
