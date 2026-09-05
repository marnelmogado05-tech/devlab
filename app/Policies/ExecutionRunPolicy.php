<?php

namespace App\Policies;

use App\Models\ChallengeAttempt;
use App\Models\ExecutionRun;
use App\Models\User;
use App\Services\Challenge\ExperienceCapabilities;

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
    public function __construct(private readonly ExperienceCapabilities $capabilities) {}

    public function view(User $user, ExecutionRun $run): bool
    {
        return $run->user_id === $user->id;
    }

    /**
     * Whether this user may run code against this attempt.
     *
     * Three questions, and only the first is about the person. The open check
     * stops the owner of a finished attempt queueing containers against it
     * forever — the attempt would refuse to be re-scored, and the compute would
     * be spent anyway.
     *
     * The capability check stops the same waste arriving from the other
     * direction. Nothing about this route is Code Arena's except the intent:
     * the attempt is any attempt the caller owns, so before this check the
     * owner of an open CURSED CODE attempt could post source to it and spend
     * runs from a budget belonging to a feature their challenge has nothing to
     * do with. Nothing escaped — the code would still be sandboxed, and no
     * evaluator would accept the run id — but a container was started and a run
     * was charged against a challenge that has no cases to run it against.
     *
     * Asked of the EXPERIENCE rather than of the challenge's shape, because
     * inferring "this looks runnable" from a configuration is a guess, and a
     * guess is not a gate (ADR 0009).
     */
    public function create(User $user, ChallengeAttempt $attempt): bool
    {
        if ($attempt->user_id !== $user->id || ! $attempt->isOpen()) {
            return false;
        }

        $attempt->loadMissing('challenge.experience');

        return $this->capabilities->has(
            $attempt->challenge->experience->slug,
            ExperienceCapabilities::EXECUTION,
        );
    }
}
