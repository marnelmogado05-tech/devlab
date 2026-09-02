<?php

declare(strict_types=1);

use App\Models\Profile;
use App\Models\User;
use App\Models\UserStatistic;
use App\Services\Leaderboard\LeaderboardService;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    // Redis is not rolled back between tests; clear what this service owns.
    try {
        $service = app(LeaderboardService::class);

        foreach ($service->periods() as $period) {
            Redis::del($service->key($period));
        }
    } catch (Throwable) {
        // No Redis here — the page is served from PostgreSQL, which is fine.
    }
});

function playerWith(int $xp, ?string $username = null, bool $public = true): User
{
    $user = User::factory()->create();

    UserStatistic::query()->create([
        'user_id' => $user->id,
        'total_xp' => $xp,
        'level' => 1,
    ]);

    if ($username !== null) {
        Profile::factory()->for($user)->create([
            'username' => $username,
            'display_name' => $username,
            'is_public' => $public,
        ]);
    }

    return $user;
}

it('shows the leaderboard to a guest', function () {
    playerWith(500, 'ada');

    $this->get(route('leaderboards.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('leaderboards/index')
            ->has('entries', 1)
            ->where('entries.0.rank', 1)
            ->where('entries.0.display_name', 'ada')
        );
});

it('tells a signed-in user their own rank', function () {
    playerWith(900, 'first');
    $me = playerWith(400, 'second');

    $this->actingAs($me)
        ->get(route('leaderboards.index'))
        ->assertInertia(fn ($page) => $page->where('you.rank', 2));
});

it('says so when a signed-in user is not ranked', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('leaderboards.index'))
        ->assertInertia(fn ($page) => $page->where('you.rank', null));
});

it('still ranks a private profile', function () {
    // Hiding it would leave a gap in the numbering and quietly reward making
    // yourself invisible.
    playerWith(700, 'hidden', public: false);

    $this->get(route('leaderboards.index'))
        ->assertInertia(fn ($page) => $page
            ->has('entries', 1)
            ->where('entries.0.is_public', false)
        );
});

it('offers each configured period', function () {
    $this->get(route('leaderboards.index'))
        ->assertInertia(fn ($page) => $page
            ->where('periods', config('devlab.leaderboards.periods'))
        );
});

it('ignores a period it does not recognise', function () {
    playerWith(500, 'ada');

    $this->get(route('leaderboards.index', ['period' => 'nonsense']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('entries', 1));
});

it('shows an empty board without failing', function () {
    $this->get(route('leaderboards.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('entries', 0));
});

it('rebuilds from the command', function () {
    playerWith(300, 'ada');
    playerWith(100, 'grace');

    $this->artisan('devlab:rebuild-leaderboards')->assertSuccessful();
});
