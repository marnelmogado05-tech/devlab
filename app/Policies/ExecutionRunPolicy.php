<?php

namespace App\Policies;

use App\Models\ChallengeAttempt;
use App\Models\ExecutionRun;
use App\Models\User;

/**
 * A run is as private as the attempt it belongs to.
 *
 * Run ids are sequential, like attempt ids, so this is the same IDOR surface
 * `ChallengeAttemptPolicy` guards — and a leaked run is worse than a leaked
 * attempt, because it contains somebody's source code and everything their code
 * printed while running.
 *
 * Ownership is read from the RUN's `user_id`, which is written from the attempt
 * when the run is created and never from a request.
 */
class ExecutionRunPolicy
{
    public function view(User $user, ExecutionRun $run): bool
    {
        return $run->user_id === $user->id;
    }

    /**
     * Whether this user may run code against this attempt.
     *
     * The open check is the important half. Without it the owner of a finished
     * attempt could keep queueing containers against it forever — the attempt
     * would refuse to be re-scored, and the compute would be spent anyway.
     */
    public function create(User $user, ChallengeAttempt $attempt): bool
    {
        return $attempt->user_id === $user->id && $attempt->isOpen();
    }
}
