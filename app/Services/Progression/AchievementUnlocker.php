<?php

namespace App\Services\Progression;

use App\Models\Achievement;
use App\Models\User;
use App\Models\XpTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Evaluates every active achievement against a user's statistics and unlocks
 * the ones they have earned.
 *
 * Unlocking is idempotent, and the guarantee is `achievement_user`'s unique
 * index on (user_id, achievement_id) — attempt the insert, treat a conflict as
 * "they already had it". No read-then-write, so a retry cannot award the XP
 * bonus twice.
 *
 * Runs INSIDE the completion transaction rather than from a queued listener.
 * The progression skill's diagram enqueues this step, but ADR 0005 is binding
 * and more specific: an achievement grants XP, and no reward may exist only in a
 * job. Evaluation is cheap — a handful of declarative rules read against one
 * already-loaded statistics row, with no query per achievement.
 */
class AchievementUnlocker
{
    public function __construct(
        private readonly AchievementCriteria $criteria,
        private readonly XpLedger $ledger,
    ) {}

    /**
     * @return array<int, Achievement> the achievements unlocked by this call
     */
    public function evaluateFor(User $user): array
    {
        $statistic = $user->statistic;

        if ($statistic === null) {
            return [];
        }

        $alreadyHeld = DB::table('achievement_user')
            ->where('user_id', $user->id)
            ->pluck('achievement_id')
            ->all();

        $candidates = Achievement::query()
            ->active()
            ->whereNotIn('id', $alreadyHeld)
            ->get();

        $unlocked = [];

        foreach ($candidates as $achievement) {
            if (! $this->criteria->isMetBy($achievement->criteria, $statistic)) {
                continue;
            }

            if ($this->unlock($user, $achievement)) {
                $unlocked[] = $achievement;
            }
        }

        return $unlocked;
    }

    /**
     * @return bool whether this call was the one that unlocked it
     */
    private function unlock(User $user, Achievement $achievement): bool
    {
        try {
            /*
             * A SAVEPOINT, for the same reason the XP grant needs one:
             * PostgreSQL aborts the whole transaction after a failed statement,
             * and this runs inside the completion transaction. Without it, a
             * concurrent unlock would roll back the completion itself.
             */
            DB::transaction(fn () => DB::table('achievement_user')->insert([
                'user_id' => $user->id,
                'achievement_id' => $achievement->id,
                'unlocked_at' => now(),
                'progress' => '{}',
            ]));
        } catch (QueryException $e) {
            if ($this->isDuplicateUnlock($e)) {
                // Someone else got there first — a concurrent completion, or a
                // retry. They have it, which is what was wanted.
                return false;
            }

            throw $e;
        }

        if ($achievement->xp_bonus > 0) {
            $this->ledger->grant(
                user: $user,
                amount: $achievement->xp_bonus,
                sourceType: XpTransaction::SOURCE_ACHIEVEMENT,
                // Keyed by the achievement's stable key, not its id: renaming or
                // reseeding must not be able to pay the bonus a second time.
                sourceId: $achievement->key,
                description: "Achievement unlocked: {$achievement->name}",
                metadata: ['achievement_id' => $achievement->id],
            );
        }

        return true;
    }

    private function isDuplicateUnlock(QueryException $e): bool
    {
        return ($e->errorInfo[0] ?? null) === '23505';
    }
}
