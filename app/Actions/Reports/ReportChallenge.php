<?php

namespace App\Actions\Reports;

use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\ChallengeReport;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * File a report against a challenge.
 *
 * Idempotent, and the guarantee is the database's: `challenge_reports` carries a
 * partial unique index on (challenge_id, user_id, reason) WHERE status = 'open'.
 * A double-clicked submit or a retried request therefore cannot produce two open
 * reports, and the same index is the anti-spam guard — one open report per person
 * per reason per challenge.
 *
 * Filing a report NEVER touches the reporter's attempt, score or XP. It must not
 * become a way to escape a failed attempt.
 */
class ReportChallenge
{
    public function handle(
        User $reporter,
        Challenge $challenge,
        string $reason,
        ?string $details = null,
        ?ChallengeAttempt $attempt = null,
    ): ChallengeReport {
        try {
            /*
             * A SAVEPOINT, for the same reason the XP grant needs one: PostgreSQL
             * aborts the whole transaction after a failed statement, and a caller
             * may already have one open.
             */
            return DB::transaction(fn () => ChallengeReport::query()->create([
                'challenge_id' => $challenge->id,
                /*
                 * The version played, not the current one. Fixing a wrong key
                 * means bumping the version, and the attempts that need
                 * identifying are the ones scored against the old one (§71).
                 */
                'challenge_version' => $attempt->challenge_version ?? $challenge->version,
                'user_id' => $reporter->id,
                'attempt_id' => $attempt?->id,
                'reason' => $reason,
                'details' => $details,
                'status' => ChallengeReport::STATUS_OPEN,
            ]));
        } catch (QueryException $e) {
            if (! $this->isDuplicate($e)) {
                throw $e;
            }

            /*
             * They already have this exact report open. Hand it back rather than
             * failing: the reporter asked for a state, and the state holds. It
             * also means the response is identical either way, so a reporter
             * cannot learn anything from a rejection.
             */
            return ChallengeReport::query()
                ->open()
                ->where('challenge_id', $challenge->id)
                ->where('user_id', $reporter->id)
                ->where('reason', $reason)
                ->sole();
        }
    }

    private function isDuplicate(QueryException $e): bool
    {
        return ($e->errorInfo[0] ?? null) === '23505';
    }
}
