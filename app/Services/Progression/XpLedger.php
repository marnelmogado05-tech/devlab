<?php

namespace App\Services\Progression;

use App\Models\User;
use App\Models\XpTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The only way XP is granted.
 *
 * Granting is idempotent, and the guarantee is a DATABASE CONSTRAINT rather than
 * a check in PHP: `xp_transactions` is unique on
 * (user_id, source_type, source_id), so a replayed request, a retried job or two
 * concurrent completions cannot insert the same award twice. An existence check
 * would race — both callers would find nothing and both would insert.
 *
 * Nothing else in the application may write this table.
 */
class XpLedger
{
    /**
     * Grant XP, or do nothing if this exact award already exists.
     *
     * @param  array<string, mixed>  $metadata
     * @return XpTransaction|null the new row, or null when it was already granted
     */
    public function grant(
        User $user,
        int $amount,
        string $sourceType,
        string $sourceId,
        string $description,
        array $metadata = [],
    ): ?XpTransaction {
        try {
            /*
             * The insert runs in a NESTED transaction, which PostgreSQL
             * implements as a SAVEPOINT. This is not a nicety: Postgres aborts
             * the entire transaction after any failed statement, so without the
             * savepoint a duplicate award would roll back whatever surrounds
             * this call — and the caller is the completion transaction. A user
             * replaying a challenge they had already been paid for would have
             * their attempt fail to close at all.
             *
             * With the savepoint, only the duplicate insert is undone and the
             * completion carries on.
             */
            return DB::transaction(fn () => XpTransaction::query()->create([
                'user_id' => $user->id,
                'amount' => $amount,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'description' => $description,
                'metadata' => $metadata,
            ]));
        } catch (QueryException $e) {
            if ($this->isDuplicateAward($e)) {
                // Already granted. The caller asked for a state, and the state
                // already holds — that is success, not an error.
                return null;
            }

            throw $e;
        }
    }

    /**
     * A user's XP, from the ledger itself rather than the cached read model.
     */
    public function totalFor(User $user): int
    {
        return (int) XpTransaction::query()->where('user_id', $user->id)->sum('amount');
    }

    private function isDuplicateAward(QueryException $e): bool
    {
        return ($e->errorInfo[0] ?? null) === '23505';
    }
}
