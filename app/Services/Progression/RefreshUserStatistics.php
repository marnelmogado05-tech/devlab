<?php

namespace App\Services\Progression;

use App\Models\ChallengeAttempt;
use App\Models\User;
use App\Models\UserStatistic;
use App\Models\XpTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Recompute one user's statistics from source.
 *
 * RECOMPUTED, never incremented. Incrementing is faster and is how a derived
 * table silently drifts: one missed decrement, one retried job, and the number
 * is wrong forever with nothing to compare it against.
 *
 * This is also the rebuild path — the live completion transaction and
 * `devlab:rebuild-statistics` call the same method — so "rebuildable from
 * source" is true by construction rather than by a second implementation that
 * has to be kept in step (ADR 0004).
 */
class RefreshUserStatistics
{
    public function __construct(private readonly LevelCalculator $levels) {}

    public function forUser(User $user): UserStatistic
    {
        $totalXp = (int) XpTransaction::query()->where('user_id', $user->id)->sum('amount');

        $counts = $this->attemptCounts($user);
        $streaks = $this->streaks($user);

        return UserStatistic::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'total_xp' => $totalXp,
                'level' => $this->levels->levelForXp($totalXp),
                'challenges_started' => $counts['started'],
                'challenges_completed' => $counts['completed'],
                'challenges_failed' => $counts['failed'],
                'challenges_abandoned' => $counts['abandoned'],
                'total_time_seconds' => $counts['total_time'],
                'experiences_played' => $counts['experiences'],
                'current_streak_days' => $streaks['current'],
                'longest_streak_days' => $streaks['longest'],
                'last_activity_on' => $streaks['last_activity'],
                'recalculated_at' => now(),
            ],
        );
    }

    /**
     * @return array{started: int, completed: int, failed: int, abandoned: int, total_time: int, experiences: int}
     */
    private function attemptCounts(User $user): array
    {
        /*
         * One pass over the user's attempts rather than a query per status.
         * `challenges_started` counts every attempt ever opened, which is what
         * makes completed/started a meaningful success rate.
         */
        $row = ChallengeAttempt::query()
            ->where('user_id', $user->id)
            ->selectRaw('COUNT(*) AS started')
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'completed') AS completed")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'failed') AS failed")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'abandoned') AS abandoned")
            ->selectRaw('COALESCE(SUM(time_taken_seconds), 0) AS total_time')
            ->first();

        $experiences = ChallengeAttempt::query()
            ->where('challenge_attempts.user_id', $user->id)
            ->join('challenges', 'challenges.id', '=', 'challenge_attempts.challenge_id')
            ->distinct()
            ->count('challenges.experience_id');

        return [
            'started' => (int) ($row->started ?? 0),
            'completed' => (int) ($row->completed ?? 0),
            'failed' => (int) ($row->failed ?? 0),
            'abandoned' => (int) ($row->abandoned ?? 0),
            'total_time' => (int) ($row->total_time ?? 0),
            'experiences' => $experiences,
        ];
    }

    /**
     * Consecutive days on which the user finished at least one challenge.
     *
     * Derived from the attempt history rather than maintained incrementally, so
     * a missed day cannot leave a streak permanently overstated.
     *
     * @return array{current: int, longest: int, last_activity: Carbon|null}
     */
    private function streaks(User $user): array
    {
        /** @var array<int, string> $days */
        $days = ChallengeAttempt::query()
            ->where('user_id', $user->id)
            ->where('status', ChallengeAttempt::STATUS_COMPLETED)
            ->selectRaw('DISTINCT DATE(completed_at) AS day')
            ->orderBy('day')
            ->pluck('day')
            ->filter()
            ->map(fn ($day) => (string) $day)
            ->all();

        if ($days === []) {
            return ['current' => 0, 'longest' => 0, 'last_activity' => null];
        }

        $dates = array_map(fn (string $day) => Carbon::parse($day)->startOfDay(), $days);

        $longest = 1;
        $run = 1;

        for ($i = 1; $i < count($dates); $i++) {
            // Carbon 3 returns a float here, so an === comparison against an
            // integer silently never matches.
            $gap = (int) round((float) $dates[$i - 1]->diffInDays($dates[$i]));

            $run = $gap === 1 ? $run + 1 : 1;
            $longest = max($longest, $run);
        }

        $last = end($dates);

        /*
         * A streak is only "current" if it reaches today or yesterday. Anything
         * older has been broken — counting it would tell a user they are on a
         * nine-day run they lost last month.
         */
        $sinceLast = (int) round((float) $last->diffInDays(now()->startOfDay()));

        $current = $sinceLast <= 1 ? $run : 0;

        return [
            'current' => $current,
            'longest' => $longest,
            'last_activity' => $last,
        ];
    }

    /**
     * Rebuild every user with any progression history.
     *
     * @return int the number of users refreshed
     */
    public function rebuildAll(): int
    {
        $userIds = XpTransaction::query()->select('user_id')->distinct()
            ->union(ChallengeAttempt::query()->select('user_id')->distinct())
            ->pluck('user_id');

        $refreshed = 0;

        User::query()->whereIn('id', $userIds)->chunkById(200, function ($users) use (&$refreshed): void {
            foreach ($users as $user) {
                DB::transaction(fn () => $this->forUser($user));
                $refreshed++;
            }
        });

        return $refreshed;
    }
}
