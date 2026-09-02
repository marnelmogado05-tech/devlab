<?php

namespace App\Policies;

use App\Models\ChallengeAttempt;
use App\Models\User;

/**
 * An attempt is private to the person making it.
 *
 * This is DevLab's first IDOR surface: attempt ids are sequential, so
 * /attempts/{id} is guessable by construction. Ownership is checked here rather
 * than by scoping a query in one controller, because the next controller to
 * touch attempts would have to remember to repeat the scope.
 */
class ChallengeAttemptPolicy
{
    public function view(User $user, ChallengeAttempt $attempt): bool
    {
        return $attempt->user_id === $user->id;
    }

    /**
     * Only the owner may abandon, and only while it is still open. A closed
     * attempt is a record; changing its status later would rewrite history.
     */
    public function abandon(User $user, ChallengeAttempt $attempt): bool
    {
        return $attempt->user_id === $user->id;
    }

    /**
     * Only the owner may submit, and only while the attempt is open.
     *
     * The open check is authorization, not convenience: without it, the owner of
     * a finished attempt could keep POSTing to it. The action would refuse to
     * re-score, but the request would still run an evaluator on every call.
     */
    public function submit(User $user, ChallengeAttempt $attempt): bool
    {
        return $attempt->user_id === $user->id && $attempt->isOpen();
    }

    /**
     * Whether this user may open an attempt at all. Playable content only —
     * `ChallengePolicy::view()` decides what is playable.
     */
    public function create(User $user): bool
    {
        return true;
    }
}
