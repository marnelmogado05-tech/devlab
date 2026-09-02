<?php

namespace App\Policies;

use App\Models\Experience;
use App\Models\User;

/**
 * Law 4: authorize every object access with a policy. Filtering a listing query
 * is a performance measure, not authorization — this is what decides.
 *
 * The catalogue is public, so `$user` is nullable throughout: a guest may browse
 * published content and is refused everything else, exactly as a signed-in user
 * without special standing would be.
 *
 * There is no author or moderator role yet. When one arrives, the extra branches
 * belong here and nowhere else.
 */
class ExperiencePolicy
{
    /**
     * Anyone may look at the catalogue. What it contains is decided per row.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Experience $experience): bool
    {
        return $experience->isPublished();
    }
}
