<?php

namespace App\Events;

use App\Models\ChallengeAttempt;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A challenge attempt finished — correctly or not.
 *
 * Dispatched AFTER the completion transaction commits, so a listener never
 * observes an attempt that is about to be rolled back.
 *
 * Nothing listens yet. This is the seam the XP ledger, achievement evaluation
 * and statistics refresh attach to (§56.8–9), and defining it here keeps those
 * slices additive rather than surgery on the completion path.
 */
class ChallengeCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public ChallengeAttempt $attempt) {}
}
