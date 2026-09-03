<?php

/**
 * The sandbox entrypoint.
 *
 * Reads a base64 JSON payload from its first argument, writes the two files to
 * the tmpfs, and runs the tests. It never touches the network and never writes
 * outside /tmp, which is the only writable path in the container.
 *
 * An argument rather than stdin: `docker run -i` spawned from Node never sees
 * the pipe close, so every run held its attach open until the deadline. Removing
 * the channel is a better answer than timing around it.
 *
 * This file is part of the sandbox image, not the application. It runs INSIDE
 * the boundary, alongside hostile code, and is therefore not trusted by anything
 * outside it — the orchestrator treats everything this prints as untrusted bytes
 * (S5).
 */
$raw = base64_decode((string) ($argv[1] ?? ''), true);

$payload = $raw === false ? null : json_decode($raw, true);

if (! is_array($payload)) {
    fwrite(STDERR, "The sandbox received no readable payload.\n");
    exit(2);
}

$submission = (string) ($payload['submission'] ?? '');
$tests = (string) ($payload['tests'] ?? '');

/*
 * /tmp is a tmpfs mounted noexec. The files are written there because it is the
 * only writable path, and they are read by the interpreter rather than executed
 * as programs, so noexec costs nothing.
 */
file_put_contents('/tmp/submission.php', $submission);
file_put_contents('/tmp/tests.php', $tests);

require '/tmp/tests.php';
