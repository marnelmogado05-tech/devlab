<?php

namespace App\Actions\Attempts;

use App\Models\ChallengeAttempt;
use Illuminate\Support\Facades\DB;

/**
 * Close attempts left open longer than `devlab.attempts.expire_after_minutes`.
 *
 * Expiry exists to protect scoring: without it, a tab left open overnight
 * produces an attempt whose elapsed time is meaningless, and — once a speed
 * bonus exists — an attempt whose elapsed time is a liability. It also frees the
 * partial unique index so the user can start the challenge again.
 *
 * A single UPDATE, deliberately: this runs on a schedule over a table that grows
 * without bound, and loading models to save them one at a time would not survive
 * a real backlog. Nothing here needs model events.
 */
class ExpireStaleAttempts
{
    /**
     * @return int the number of attempts expired
     */
    public function handle(): int
    {
        $cutoff = now()->subMinutes((int) config('devlab.attempts.expire_after_minutes'));

        return ChallengeAttempt::query()
            ->open()
            ->where('started_at', '<', $cutoff)
            ->update([
                'status' => ChallengeAttempt::STATUS_EXPIRED,
                // Recorded from the row's own timestamps, so an expired attempt
                // still says how long it was actually open.
                'time_taken_seconds' => DB::raw('EXTRACT(EPOCH FROM (NOW() - started_at))::int'),
                'updated_at' => now(),
            ]);
    }
}
