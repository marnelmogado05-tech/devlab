<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserStatistic;
use App\Services\Leaderboard\LeaderboardService;
use Illuminate\Support\Facades\Redis;

/*
 * The Redis path, against a REAL Redis.
 *
 * ADR 0005 makes these mandatory rather than optional: "sorted-set behaviour is
 * the reason for choosing Redis and is exactly what the array driver cannot
 * model". phpunit.xml runs CACHE_STORE=array for speed, so nothing else in the
 * suite touches Redis at all — without this file the ranking code would ship
 * entirely unexercised.
 *
 * They are skipped when Redis is unreachable, which is the normal case on a host
 * without the phpredis extension (see getting-started.md). CI installs the
 * extension and runs a Redis service, so they execute there — and so does
 * `docker compose exec app php artisan test`.
 */

beforeEach(function () {
    try {
        Redis::connection()->ping();
    } catch (Throwable $e) {
        $this->markTestSkipped('Redis is unreachable: '.$e->getMessage());
    }

    $this->leaderboards = app(LeaderboardService::class);

    // The suite shares a Redis with whatever else is running, so clear only the
    // keys this service owns rather than flushing the database.
    foreach ($this->leaderboards->periods() as $period) {
        Redis::del($this->leaderboards->key($period));
    }
});

function scoringUser(int $totalXp): User
{
    $user = User::factory()->create();

    UserStatistic::query()->create([
        'user_id' => $user->id,
        'total_xp' => $totalXp,
        'level' => 1,
    ]);

    return $user;
}

it('populates a sorted set from postgresql', function () {
    scoringUser(300);
    scoringUser(100);

    $this->leaderboards->rebuild();

    expect((int) Redis::zcard($this->leaderboards->key(LeaderboardService::PERIOD_ALL_TIME)))
        ->toBe(2);
});

it('ranks from the sorted set', function () {
    $top = scoringUser(900);
    $next = scoringUser(400);

    $this->leaderboards->rebuild();

    $rows = $this->leaderboards->top(LeaderboardService::PERIOD_ALL_TIME);

    expect(array_column($rows, 'user_id'))->toBe([$top->id, $next->id])
        ->and($rows[0]['score'])->toBe(900)
        ->and($rows[0]['rank'])->toBe(1);
});

it('reads a user rank from the sorted set', function () {
    scoringUser(900);
    $second = scoringUser(400);

    $this->leaderboards->rebuild();

    // Redis ranks from zero; a human-facing rank starts at one.
    expect($this->leaderboards->rankFor($second))->toBe(2);
});

it('updates a score in place rather than adding a second entry', function () {
    $user = scoringUser(100);

    $this->leaderboards->sync($user);
    $user->statistic->update(['total_xp' => 750]);
    $this->leaderboards->sync($user);

    $key = $this->leaderboards->key(LeaderboardService::PERIOD_ALL_TIME);

    expect((int) Redis::zcard($key))->toBe(1)
        ->and((int) Redis::zscore($key, (string) $user->id))->toBe(750);
});

it('is idempotent to rebuild', function () {
    scoringUser(300);
    scoringUser(100);

    $first = $this->leaderboards->rebuild();
    $second = $this->leaderboards->rebuild();

    expect($second)->toBe($first)
        ->and((int) Redis::zcard($this->leaderboards->key(LeaderboardService::PERIOD_ALL_TIME)))->toBe(2);
});

it('drops a user who no longer scores', function () {
    $user = scoringUser(100);
    $this->leaderboards->sync($user);

    $user->statistic->update(['total_xp' => 0]);
    $this->leaderboards->sync($user);

    expect((int) Redis::zcard($this->leaderboards->key(LeaderboardService::PERIOD_ALL_TIME)))
        ->toBe(0);
});

it('rebuilds without ever exposing a half-populated board', function () {
    /*
     * The rebuild stages into a temporary key and RENAMEs it into place, so a
     * reader either sees the old board or the new one. Deleting and refilling in
     * place would show an empty leaderboard for the duration.
     */
    scoringUser(500);
    $this->leaderboards->rebuild();

    scoringUser(700);
    $this->leaderboards->rebuild();

    $key = $this->leaderboards->key(LeaderboardService::PERIOD_ALL_TIME);

    expect((int) Redis::zcard($key))->toBe(2)
        ->and((int) Redis::exists($key.':rebuilding'))->toBe(0);
});

it('serves the same order from redis as from postgresql', function () {
    /*
     * The two paths must not disagree; a rank that changes when the cache warms
     * is worse than a slow one. The repeated 640 is the interesting case: Redis
     * breaks that tie reverse-lexicographically and PostgreSQL numerically.
     */
    foreach ([820, 640, 640, 210, 95] as $xp) {
        scoringUser($xp);
    }

    $fromDatabase = array_column($this->leaderboards->top(LeaderboardService::PERIOD_ALL_TIME), 'user_id');

    $this->leaderboards->rebuild();

    $fromRedis = array_column($this->leaderboards->top(LeaderboardService::PERIOD_ALL_TIME), 'user_id');

    expect($fromRedis)->toBe($fromDatabase);
});
