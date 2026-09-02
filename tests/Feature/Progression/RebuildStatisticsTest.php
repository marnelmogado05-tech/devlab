<?php

declare(strict_types=1);

use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\Experience;
use App\Models\User;
use App\Models\UserStatistic;
use App\Models\XpTransaction;
use App\Services\Progression\RefreshUserStatistics;

/*
 * ADR 0004's central claim is that user_statistics is rebuildable from source.
 * These tests are what make that claim checkable rather than aspirational.
 */

beforeEach(function () {
    $this->experience = Experience::factory()->published()->create();
    $this->challenge = Challenge::factory()->published()->for($this->experience)->create();
    $this->user = User::factory()->create();
});

it('rebuilds a total from the ledger alone', function () {
    XpTransaction::factory()->for($this->user)->create(['amount' => 300, 'source_id' => 'a']);
    XpTransaction::factory()->for($this->user)->create(['amount' => 250, 'source_id' => 'b']);

    app(RefreshUserStatistics::class)->forUser($this->user);

    expect($this->user->statistic->total_xp)->toBe(550);
});

it('repairs a corrupted total', function () {
    // The scenario the rebuild exists for: something wrote a number that the
    // ledger does not support.
    XpTransaction::factory()->for($this->user)->create(['amount' => 100, 'source_id' => 'a']);

    UserStatistic::query()->updateOrCreate(
        ['user_id' => $this->user->id],
        ['total_xp' => 999_999, 'level' => 6],
    );

    app(RefreshUserStatistics::class)->forUser($this->user);

    expect($this->user->refresh()->statistic->total_xp)->toBe(100)
        ->and($this->user->statistic->level)->toBe(1);
});

it('counts attempts by status', function () {
    ChallengeAttempt::factory()->completed()->for($this->challenge)->for($this->user)->create();
    ChallengeAttempt::factory()->abandoned()->for($this->challenge)->for($this->user)->create();
    ChallengeAttempt::factory()->for($this->challenge)->for($this->user)->create();

    app(RefreshUserStatistics::class)->forUser($this->user);

    $statistic = $this->user->statistic;

    expect($statistic->challenges_started)->toBe(3)
        ->and($statistic->challenges_completed)->toBe(1)
        ->and($statistic->challenges_abandoned)->toBe(1);
});

it('is idempotent', function () {
    XpTransaction::factory()->for($this->user)->create(['amount' => 100, 'source_id' => 'a']);
    ChallengeAttempt::factory()->completed()->for($this->challenge)->for($this->user)->create();

    $refresh = app(RefreshUserStatistics::class);

    $first = $refresh->forUser($this->user)->only(['total_xp', 'challenges_completed', 'level']);
    $second = $refresh->forUser($this->user)->only(['total_xp', 'challenges_completed', 'level']);

    expect($second)->toBe($first)
        ->and(UserStatistic::query()->count())->toBe(1);
});

it('counts a streak of consecutive days', function () {
    foreach ([2, 1, 0] as $daysAgo) {
        ChallengeAttempt::factory()->for($this->challenge)->for($this->user)->create([
            'status' => ChallengeAttempt::STATUS_COMPLETED,
            'completed_at' => now()->subDays($daysAgo),
        ]);
    }

    app(RefreshUserStatistics::class)->forUser($this->user);

    expect($this->user->statistic->current_streak_days)->toBe(3)
        ->and($this->user->statistic->longest_streak_days)->toBe(3);
});

it('does not count a streak that has already been broken', function () {
    // Telling someone they are on a nine-day run they lost last month is worse
    // than telling them it is over.
    foreach ([40, 39, 38] as $daysAgo) {
        ChallengeAttempt::factory()->for($this->challenge)->for($this->user)->create([
            'status' => ChallengeAttempt::STATUS_COMPLETED,
            'completed_at' => now()->subDays($daysAgo),
        ]);
    }

    app(RefreshUserStatistics::class)->forUser($this->user);

    expect($this->user->statistic->current_streak_days)->toBe(0)
        // The record still stands, though.
        ->and($this->user->statistic->longest_streak_days)->toBe(3);
});

it('rebuilds every user from the command', function () {
    $other = User::factory()->create();

    XpTransaction::factory()->for($this->user)->create(['amount' => 100, 'source_id' => 'a']);
    XpTransaction::factory()->for($other)->create(['amount' => 200, 'source_id' => 'b']);

    // A user with no history at all is not given a row.
    User::factory()->create();

    $this->artisan('devlab:rebuild-statistics')
        ->expectsOutputToContain('Rebuilt statistics for 2 user(s).')
        ->assertSuccessful();

    expect(UserStatistic::query()->count())->toBe(2)
        ->and($this->user->statistic->total_xp)->toBe(100)
        ->and($other->statistic->total_xp)->toBe(200);
});
