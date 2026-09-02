<?php

namespace App\Actions\Attempts;

use App\Models\ChallengeAttempt;
use Illuminate\Support\Facades\DB;

/**
 * Close an open attempt because the user walked away from it.
 *
 * Abandoning is idempotent and never fails on an already-closed attempt: the
 * user's intent is "this is over", and it already is. Re-closing a completed
 * attempt must NOT overwrite its status, or a stray abandon request would erase
 * a completion — and, once scoring exists, the record a score was derived from.
 */
class AbandonAttempt
{
    public function handle(ChallengeAttempt $attempt): ChallengeAttempt
    {
        return DB::transaction(function () use ($attempt): ChallengeAttempt {
            $fresh = ChallengeAttempt::query()
                ->whereKey($attempt->getKey())
                ->lockForUpdate()
                ->first();

            if ($fresh === null || ! $fresh->isOpen()) {
                return $fresh ?? $attempt;
            }

            $fresh->update([
                'status' => ChallengeAttempt::STATUS_ABANDONED,
                'time_taken_seconds' => $fresh->elapsedSeconds(),
            ]);

            return $fresh;
        });
    }
}
