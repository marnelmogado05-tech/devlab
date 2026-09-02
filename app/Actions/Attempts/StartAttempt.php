<?php

namespace App\Actions\Attempts;

use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Open an attempt at a challenge, or hand back the one already open.
 *
 * Starting is IDEMPOTENT by design. A double-clicked button, a retried request
 * or two tabs must all land on one attempt, because a second open attempt would
 * be a second `started_at` — and therefore a second, shorter elapsed time to
 * submit against once scoring exists.
 *
 * The guarantee is the database's, not this class's. `challenge_attempts` carries
 * a partial unique index on (user_id, challenge_id) WHERE status = 'started', so
 * the concurrent case is resolved by the index rejecting the loser. A
 * check-then-insert would race: both requests would find nothing and both would
 * insert.
 */
class StartAttempt
{
    public function handle(User $user, Challenge $challenge): ChallengeAttempt
    {
        return DB::transaction(function () use ($user, $challenge): ChallengeAttempt {
            $open = $this->openAttempt($user, $challenge);

            if ($open !== null) {
                return $open;
            }

            try {
                return ChallengeAttempt::query()->create([
                    'user_id' => $user->id,
                    'challenge_id' => $challenge->id,
                    /*
                     * Snapshot the version played. Without it, correcting a wrong
                     * answer key leaves no way to identify which attempts were
                     * scored against the broken one (plan §71).
                     */
                    'challenge_version' => $challenge->version,
                    'status' => ChallengeAttempt::STATUS_STARTED,
                    'started_at' => now(),
                    'metadata' => [],
                ]);
            } catch (QueryException $e) {
                /*
                 * Someone else won the race between the read above and this
                 * insert. That is the index doing its job, not an error — the
                 * attempt they created is the one this user wanted.
                 */
                if (! $this->isUniqueViolation($e)) {
                    throw $e;
                }

                return $this->openAttempt($user, $challenge) ?? throw $e;
            }
        });
    }

    private function openAttempt(User $user, Challenge $challenge): ?ChallengeAttempt
    {
        return ChallengeAttempt::query()
            ->open()
            ->where('user_id', $user->id)
            ->where('challenge_id', $challenge->id)
            ->first();
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return ($e->errorInfo[0] ?? null) === '23505';
    }
}
