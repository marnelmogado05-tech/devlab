<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\User;
use App\Services\Leaderboard\LeaderboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The leaderboard (§16, §74).
 *
 * Public. Rank is read from the server; nothing here accepts a position, a score
 * or a page of results from the client.
 */
class LeaderboardController extends Controller
{
    public function __construct(private readonly LeaderboardService $leaderboards) {}

    /**
     * GET /leaderboards
     */
    public function index(Request $request): Response
    {
        $period = (string) $request->query('period', LeaderboardService::PERIOD_ALL_TIME);
        $pageSize = (int) config('devlab.leaderboards.page_size');

        $rows = $this->leaderboards->top($period, $pageSize);

        return Inertia::render('leaderboards/index', [
            'period' => $period,
            'periods' => $this->leaderboards->periods(),
            'entries' => $this->withDisplayNames($rows),
            'you' => $request->user()
                ? [
                    'rank' => $this->leaderboards->rankFor($request->user(), $period),
                    'user_id' => $request->user()->id,
                ]
                : null,
        ]);
    }

    /**
     * Attach the public identity for each ranked user, in one query.
     *
     * A private profile still ranks — hiding it would leave a gap in the
     * numbering and quietly reward making yourself invisible — but it is shown
     * without a link to a profile page that would refuse to load.
     *
     * @param  array<int, array{user_id: int, score: int, rank: int}>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function withDisplayNames(array $rows): array
    {
        $userIds = array_column($rows, 'user_id');

        // A plain array rather than a keyed Collection: `get()` on a Collection
        // is typed as always returning a model, which hides the perfectly normal
        // case of a ranked user who has not created a profile.
        $profiles = [];

        foreach (Profile::query()->whereIn('user_id', $userIds)->get() as $profile) {
            $profiles[$profile->user_id] = $profile;
        }

        $names = User::query()
            ->whereIn('id', $userIds)
            ->pluck('name', 'id');

        return array_map(function (array $row) use ($profiles, $names): array {
            $profile = $profiles[$row['user_id']] ?? null;

            return [
                'rank' => $row['rank'],
                'score' => $row['score'],
                'username' => $profile?->username,
                // `??` already suppresses access on null, so `?->` to its left
                // would be redundant. It stays on `username` above, which has no
                // fallback of its own.
                'display_name' => $profile->display_name
                    ?? $profile->username
                    ?? $names[$row['user_id']]
                    ?? 'Unknown',
                'is_public' => (bool) ($profile->is_public ?? false),
            ];
        }, $rows);
    }
}
