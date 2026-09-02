<?php

use App\Services\Leaderboard\LeaderboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Integration');

/*
 * Unit tests get a booted application but NOT RefreshDatabase. Services read
 * their constants from config/devlab.php (scoring weights, rate limits), so they
 * need the container; migrating a database for a pure function would make the
 * fast suite slow for nothing.
 */
pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Redis is not rolled back
|--------------------------------------------------------------------------
|
| RefreshDatabase wraps each test in a transaction and rolls PostgreSQL back.
| Nothing does that for Redis, so a leaderboard sorted set written by one test is
| still there for the next — and because the service prefers Redis over the
| database, the next test reads rankings for users that no longer exist.
|
| That bit three separate test files before this was centralised. Clearing the
| keys the leaderboard owns, once, removes the whole class of failure rather than
| the next instance of it.
|
| Wrapped, because a host without phpredis has no Redis to clear and that is a
| supported way to run the suite.
*/
pest()->beforeEach(function (): void {
    try {
        $leaderboards = app(LeaderboardService::class);

        foreach ($leaderboards->periods() as $period) {
            Redis::del($leaderboards->key($period));
        }
    } catch (Throwable) {
        // No Redis here. The leaderboard falls back to PostgreSQL, which is
        // itself covered by LeaderboardFallbackTest.
    }
})->in('Feature', 'Integration');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
