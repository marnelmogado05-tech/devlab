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
    /**
     * The user was already using their share of the pool.
     *
     * Kept distinct from the platform having no room, because they are different
     * events with different answers: one is the player's own doing and clears
     * when their other run finishes, the other is ours and does not. A log — or
     * a run row — that cannot tell them apart cannot tell an abusive account
     * from a bad afternoon.
     */
    public const REASON_QUOTA = 'quota';

    /** The platform could not run it: no orchestrator, no room, or it broke. */
    public const REASON_UNAVAILABLE = 'unavailable';

    private function __construct(string $message, private readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function notConfigured(): self
    {
        return new self(
            'No execution orchestrator is configured. Code execution is disabled.',
            self::REASON_UNAVAILABLE,
        );
    }

    public static function poolExhausted(): self
    {
        return new self('No sandbox is free to run this submission.', self::REASON_UNAVAILABLE);
    }

    public static function quotaReached(int $concurrent): self
    {
        return new self(
            "You already have {$concurrent} submissions running.",
            self::REASON_QUOTA,
        );
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
