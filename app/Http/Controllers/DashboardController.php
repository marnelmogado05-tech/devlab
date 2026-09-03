<?php

namespace App\Http\Controllers;

use App\Models\ChallengeAttempt;
use App\Models\User;
use App\Models\UserStatistic;
use App\Services\Leaderboard\LeaderboardService;
use App\Services\Progression\LevelCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * GET /dashboard — where a signed-in player lands.
 *
 * The profile is a record of what someone did; this is a prompt to do something
 * next, which is why the two show different things from the same tables. What
 * belongs here is whatever shortens the distance to the next challenge: an
 * attempt still open, the button, and enough progress to make pressing it feel
 * worth doing.
 *
 * Everything is read from the ledger and the read model. There is no figure on
 * this page that is not already true somewhere else.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly LevelCalculator $levels,
        private readonly LeaderboardService $leaderboards,
    ) {}

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $statistic = $user->statistic;
        $totalXp = $statistic->total_xp ?? 0;

        return Inertia::render('dashboard', [
            'progression' => [
                'total_xp' => $totalXp,
                'level' => $this->levels->forXp($totalXp),
                'next_level' => $this->levels->nextAfter($totalXp),
                'progress' => $this->levels->progressToNext($totalXp),
                'rank' => $this->leaderboards->rankFor($user),
            ],
            'statistics' => $this->statistics($statistic),
            'openAttempts' => $this->openAttempts($user),
            'recent' => $this->recentActivity($user),
            'achievements' => $this->latestAchievements($user),
            'username' => $user->profile?->username,
        ]);
    }

    /**
     * Attempts still open, newest first.
     *
     * The most useful thing this page can do. One open attempt per challenge is
     * a database constraint, but nothing stops someone opening several across
     * different challenges, and an attempt left open is expired by the scheduler
     * after `devlab.attempts.expire_after_minutes` — quietly, from the player's
     * point of view. Showing them here, with the deadline, is the difference
     * between abandoning something and forgetting it.
     *
     * @return array<int, array<string, mixed>>
     */
    private function openAttempts(User $user): array
    {
        $expireAfter = (int) config('devlab.attempts.expire_after_minutes');

        return ChallengeAttempt::query()
            ->where('user_id', $user->id)
            ->open()
            ->with([
                'challenge:id,slug,title,difficulty,experience_id',
                'challenge.experience:id,name',
            ])
            ->latest('started_at')
            ->limit(5)
            ->get()
            ->map(fn (ChallengeAttempt $attempt) => [
                'id' => $attempt->id,
                'challenge' => [
                    'slug' => $attempt->challenge->slug,
                    'title' => $attempt->challenge->title,
                    'difficulty' => $attempt->challenge->difficulty,
                ],
                'experience' => $attempt->challenge->experience->name,
                'started_at' => $attempt->started_at->toIso8601String(),
                'expires_at' => $attempt->started_at->copy()->addMinutes($expireAfter)->toIso8601String(),
            ])
            ->all();
    }

    /**
     * The handful of figures worth showing above the fold.
     *
     * Fewer than the profile carries, on purpose: this page is read on the way
     * to somewhere else, and a wall of statistics is something to scroll past.
     *
     * @return array<string, mixed>
     */
    private function statistics(?UserStatistic $statistic): array
    {
        if ($statistic === null) {
            return [
                'challenges_completed' => 0,
                'success_rate' => null,
                'current_streak_days' => 0,
                'achievements_unlocked' => 0,
            ];
        }

        $completed = $statistic->challenges_completed;
        $finished = $completed + $statistic->challenges_failed;

        return [
            'challenges_completed' => $completed,
            /*
             * Out of FINISHED attempts, matching the profile exactly. Two pages
             * computing the same rate two ways is how a player learns not to
             * believe either of them.
             */
            'success_rate' => $finished > 0 ? round($completed / $finished, 3) : null,
            'current_streak_days' => $statistic->current_streak_days,
            'achievements_unlocked' => $statistic->achievements_unlocked,
        ];
    }

    /**
     * The last few finished attempts.
     *
     * No score and no submission, for the same reason the profile omits them:
     * an answer on a summary page is a challenge's content leaking onto a page
     * nobody thought of as showing it.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentActivity(User $user): array
    {
        return ChallengeAttempt::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [ChallengeAttempt::STATUS_COMPLETED, ChallengeAttempt::STATUS_FAILED])
            ->with([
                'challenge:id,slug,title,difficulty,experience_id',
                'challenge.experience:id,name',
            ])
            ->latest('completed_at')
            ->limit(5)
            ->get()
            ->map(fn (ChallengeAttempt $attempt) => [
                'challenge' => [
                    'slug' => $attempt->challenge->slug,
                    'title' => $attempt->challenge->title,
                    'difficulty' => $attempt->challenge->difficulty,
                ],
                'experience' => $attempt->challenge->experience->name,
                'solved' => $attempt->status === ChallengeAttempt::STATUS_COMPLETED,
                'completed_at' => $attempt->completed_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * The three most recent unlocks.
     *
     * Joined against the pivot rather than hydrated through the relation,
     * because what is wanted is the unlock TIME alongside the achievement, and
     * reaching through `->pivot` for it hides that this is a join.
     *
     * @return array<int, array<string, mixed>>
     */
    private function latestAchievements(User $user): array
    {
        return DB::table('achievement_user')
            ->join('achievements', 'achievements.id', '=', 'achievement_user.achievement_id')
            ->where('achievement_user.user_id', $user->id)
            ->orderByDesc('achievement_user.unlocked_at')
            ->limit(3)
            ->get([
                'achievements.key',
                'achievements.name',
                'achievements.description',
                'achievements.tier',
                'achievement_user.unlocked_at',
            ])
            ->map(fn (object $row) => [
                'key' => $row->key,
                'name' => $row->name,
                'description' => $row->description,
                'tier' => $row->tier,
                'unlocked_at' => $row->unlocked_at,
            ])
            ->all();
    }
}
