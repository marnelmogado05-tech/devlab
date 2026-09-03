<?php

namespace App\Services\Execution;

use RuntimeException;

/**
 * No sandbox could run this submission.
 *
 * A capacity or availability problem, never a verdict on the code. The caller
 * must leave the attempt OPEN and retry — marking somebody's answer failed
 * because the platform ran out of room is the platform lying about their work
 * (S7, ADR 0007).
 */
class SandboxUnavailable extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self(
            'No execution orchestrator is configured. Code execution is disabled.',
        );
    }

    public static function poolExhausted(): self
    {
        return new self('No sandbox is free to run this submission.');
    }

    public static function quotaReached(int $concurrent): self
    {
        return new self("You already have {$concurrent} submissions running.");
    }
}
