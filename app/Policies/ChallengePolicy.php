<?php

namespace App\Policies;

use App\Models\Challenge;
use App\Models\User;

/**
 * A challenge is only visible when it AND its experience are published.
 *
 * The parent check matters: unpublishing an experience must actually withdraw
 * its challenges, or a direct link to /challenges/{slug} would keep serving
 * content the maintainer believes they pulled.
 */
class ChallengePolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Challenge $challenge): bool
    {
        return $challenge->isPublished()
            && $challenge->experience->isPublished();
    }
}
