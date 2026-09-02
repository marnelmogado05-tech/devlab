<?php

namespace App\Policies;

use App\Models\ChallengeReport;
use App\Models\User;

/**
 * Who may do what with a report.
 *
 * FAILS CLOSED, deliberately. The contract says only a maintainer may list,
 * resolve or dismiss — and DevLab has no maintainer role yet. Rather than invent
 * a role system to satisfy a method (§77), those abilities return false for
 * everyone, and the MVP read path is a console command run by whoever has server
 * access.
 *
 * When a role arrives, the branch belongs here and nowhere else.
 */
class ChallengeReportPolicy
{
    /**
     * Anyone signed in may report a challenge.
     *
     * Authenticated only: anonymous reporting is an abuse surface with no upside,
     * and the anti-spam guard is a unique index keyed on the reporter.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * A reporter may see their own report and nothing else.
     *
     * Reports are never publicly visible: a visible report count is a spoiler —
     * "this one is broken" changes how you play it — and a harassment vector
     * against the author.
     */
    public function view(User $user, ChallengeReport $report): bool
    {
        return $report->user_id === $user->id;
    }

    /**
     * Listing every report is a maintainer action. No such role exists yet.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function resolve(User $user, ChallengeReport $report): bool
    {
        return false;
    }
}
