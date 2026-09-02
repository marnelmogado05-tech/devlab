<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserStatistic;
use App\Models\XpTransaction;
use App\Services\Leaderboard\LeaderboardService;

/*
 * The PostgreSQL path.
 *
 * These run everywhere, with or without a working Redis, because they assert the
 * behaviour that matters most: losing Redis costs latency, never data. A
 * leaderboard served entirely from PostgreSQL must still be correct.
 */

beforeEach(function () {
    $this->leaderboards = app(LeaderboardService::class);
});

function rankedUser(int $totalXp): User
{
    $user = User::factory()->create();

    UserStatistic::query()->create([
        'user_id' => $user->id,
        'total_xp' => $totalXp,
        'level' => 1,
    ]);

    return $user;
}

it('ranks users by xp, highest first', function () {
    $middle = rankedUser(500);
    $top = rankedUser(900);
    $bottom = rankedUser(100);

    $rows = $this->leaderboards->top(LeaderboardService::PERIOD_ALL_TIME);

    expect(array_column($rows, 'user_id'))
        ->toBe([$top->id, $middle->id, $bottom->id])
        ->and(array_column($rows, 'rank'))->toBe([1, 2, 3]);
});

it('leaves out users with no xp', function () {
    rankedUser(0);
    $scorer = rankedUser(50);

    $rows = $this->leaderboards->top(LeaderboardService::PERIOD_ALL_TIME);

    // A zero is an absence, not a tie for last place.
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['user_id'])->toBe($scorer->id);
});

it('breaks ties deterministically', function () {
    // Two users on the same score must not swap places between requests.
    $first = rankedUser(100);
    $second = rankedUser(100);

    $once = array_column($this->leaderboards->top(LeaderboardService::PERIOD_ALL_TIME), 'user_id');
    $twice = array_column($this->leaderboards->top(LeaderboardService::PERIOD_ALL_TIME), 'user_id');

    expect($once)->toBe($twice)
        ->and($once)->toBe([$first->id, $second->id]);
});

it('reports a user rank', function () {
    rankedUser(900);
    $user = rankedUser(500);

    expect($this->leaderboards->rankFor($user))->toBe(2);
});

it('reports no rank for someone who has never scored', function () {
    expect($this->leaderboards->rankFor(User::factory()->create()))->toBeNull();
});

it('paginates', function () {
    foreach ([300, 200, 100] as $xp) {
        rankedUser($xp);
    }

    $page = $this->leaderboards->top(LeaderboardService::PERIOD_ALL_TIME, limit: 2, offset: 2);

    expect($page)->toHaveCount(1)
        // Rank continues from the offset rather than restarting at one.
        ->and($page[0]['rank'])->toBe(3);
});

it('scores the weekly board from the ledger, not the lifetime total', function () {
    $user = rankedUser(10_000);

    XpTransaction::factory()->for($user)->create([
        'amount' => 120,
        'source_id' => 'recent',
        'created_at' => now(),
    ]);

    XpTransaction::factory()->for($user)->create([
        'amount' => 9_880,
        'source_id' => 'ancient',
        'created_at' => now()->subMonths(2),
    ]);

    $rows = $this->leaderboards->top(LeaderboardService::PERIOD_WEEKLY);

    expect($rows[0]['score'])->toBe(120);
});

it('falls back to all time for an unknown period', function () {
    // A crafted ?period= must not reach a query builder as a raw string.
    $user = rankedUser(400);

    $rows = $this->leaderboards->top('"; DROP TABLE users; --');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['user_id'])->toBe($user->id);
});
