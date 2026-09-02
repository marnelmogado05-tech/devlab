<?php

namespace App\Http\Controllers;

use App\Models\ChallengeAttempt;
use App\Models\Profile;
use App\Models\UserStatistic;
use App\Services\Leaderboard\LeaderboardService;
use App\Services\Progression\LevelCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * GET /profile/{username} — the public profile (§17, §74).
 *
 * A PRIVATE profile still exists and still ranks: hiding it entirely would leave
 * a gap in the leaderboard numbering and quietly reward making yourself
 * invisible. What it withholds is activity DETAIL — statistics, achievements and
 * history — which is what "private" reasonably means to the person who set it.
 *
 * The owner always sees their own profile in full.
 */
class ProfileShowController extends Controller
{
    public function __construct(
        private readonly LevelCalculator $levels,
        private readonly LeaderboardService $leaderboards,
    ) {}

    public function __invoke(Request $request, Profile $profile): Response
    {
        $profile->load('user');

        $isOwner = $request->user()?->id === $profile->user_id;
        $detailed = $profile->is_public || $isOwner;

        $statistic = $profile->user->statistic;
        $totalXp = $statistic->total_xp ?? 0;

        return Inertia::render('profiles/show', [
            'profile' => [
                'username' => $profile->username,
                'display_name' => $profile->display_name ?? $profile->username,
                'bio' => $detailed ? $profile->bio : null,
                'location' => $detailed ? $profile->location : null,
                'website' => $detailed ? $profile->website : null,
                'github_handle' => $detailed ? $profile->github_handle : null,
                'is_public' => $profile->is_public,
                'joined_at' => $profile->user->created_at?->toDateString(),
            ],
            'isOwner' => $isOwner,
            'detailed' => $detailed,

            /*
             * Level and XP are shown even on a private profile, because they are
             * already public on the leaderboard. Withholding here would be a
             * privacy setting that does not actually hide anything.
             */
            'progression' => [
                'total_xp' => $totalXp,
                'level' => $this->levels->forXp($totalXp),
                'next_level' => $this->levels->nextAfter($totalXp),
                'progress' => $this->levels->progressToNext($totalXp),
                'rank' => $this->leaderboards->rankFor($profile->user),
            ],

            'statistics' => $detailed ? $this->statistics($statistic) : null,
            'achievements' => $detailed ? $this->achievements($profile) : null,
            'recent' => $detailed ? $this->recentActivity($profile) : null,
        ]);
    }

    /**
     * The §17 developer-oriented figures, from the read model.
     *
     * @return array<string, mixed>
     */
    private function statistics(?UserStatistic $statistic): array
    {
        if ($statistic === null) {
            return [
                'challenges_completed' => 0,
                'challenges_started' => 0,
                'success_rate' => null,
                'average_solve_seconds' => null,
                'current_streak_days' => 0,
                'longest_streak_days' => 0,
                'experiences_played' => 0,
                'per_experience' => [],
            ];
        }

        $completed = $statistic->challenges_completed;
        $finished = $completed + $statistic->challenges_failed;

        return [
            'challenges_completed' => $completed,
            'challenges_started' => $statistic->challenges_started,
            /*
             * Out of FINISHED attempts, not started ones: an abandoned attempt is
             * someone closing a tab, not someone getting it wrong, and counting
             * it as failure would make the figure a measure of browsing habits.
             * Null rather than 0% when nothing has been finished — "no data" and
             * "never right" are different claims.
             */
            'success_rate' => $finished > 0 ? round($completed / $finished, 3) : null,
            'average_solve_seconds' => $completed > 0
                ? (int) round($statistic->total_time_seconds / max(1, $finished))
                : null,
            'current_streak_days' => $statistic->current_streak_days,
            'longest_streak_days' => $statistic->longest_streak_days,
            'experiences_played' => $statistic->experiences_played,
            'per_experience' => $statistic->per_experience,
        ];
    }

    /**
     * Unlocked achievements only.
     *
     * A profile shows what someone earned, not what they have not — the
     * catalogue at /achievements is where the full list belongs. Secret ones are
     * safe to name here precisely because they have been unlocked.
     *
     * @return array<int, array<string, mixed>>
     */
    private function achievements(Profile $profile): array
    {
        /*
          * A projection joined against the pivot rather than hydrated models:
          * what is wanted is the unlock TIME alongside the achievement, and
          * reaching through `->pivot` for it hides that this is a join.
          */
        return DB::table('achievement_user')
            ->join('achievements', 'achievements.id', '=', 'achievement_user.achievement_id')
            ->where('achievement_user.user_id', $profile->user_id)
            ->orderByDesc('achievement_user.unlocked_at')
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

    /**
     * Recent finished attempts.
     *
     * Deliberately no score and no submission: a profile is a record of what
     * someone did, and showing what they answered would leak a challenge's
     * content to anyone reading their profile.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentActivity(Profile $profile): array
    {
        return ChallengeAttempt::query()
            ->where('user_id', $profile->user_id)
            ->whereIn('status', [ChallengeAttempt::STATUS_COMPLETED, ChallengeAttempt::STATUS_FAILED])
            ->with('challenge:id,slug,title,difficulty,experience_id', 'challenge.experience:id,slug,name')
            ->latest('completed_at')
            ->limit(10)
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
}
