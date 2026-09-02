<?php

declare(strict_types=1);

use App\Models\Achievement;
use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\Experience;
use App\Models\User;
use App\Models\XpTransaction;
use App\Services\Challenge\EvaluatorRegistry;
use App\Services\Progression\AchievementUnlocker;
use App\Services\Progression\RefreshUserStatistics;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeEvaluator;

beforeEach(function () {
    FakeEvaluator::reset();

    $this->experience = Experience::factory()->published()->create(['slug' => 'test-experience']);
    app(EvaluatorRegistry::class)->register('test-experience', FakeEvaluator::class);

    $this->challenge = Challenge::factory()->published()->for($this->experience)->create([
        'solution' => ['answer' => 'right'],
        'difficulty' => 'medium',
    ]);

    $this->user = User::factory()->create();
});

function completeOnce(User $user, Challenge $challenge, string $answer = 'right'): void
{
    test()->actingAs($user)->post(route('attempts.store', $challenge));

    $attempt = ChallengeAttempt::query()
        ->where('user_id', $user->id)->where('challenge_id', $challenge->id)
        ->open()->sole();

    test()->actingAs($user)->post(route('attempts.submit', $attempt), [
        'submission' => ['answer' => $answer],
    ]);
}

it('unlocks an achievement when its rule is met', function () {
    Achievement::factory()->afterCompleting(1)->create(['key' => 'first_one']);

    completeOnce($this->user, $this->challenge);

    expect($this->user->achievements()->pluck('key')->all())->toBe(['first_one']);
});

it('does not unlock an achievement whose rule is not met', function () {
    Achievement::factory()->afterCompleting(5)->create();

    completeOnce($this->user, $this->challenge);

    expect($this->user->achievements()->count())->toBe(0);
});

it('grants the xp bonus once, with the unlock', function () {
    Achievement::factory()->afterCompleting(1)->worth(50)->create(['key' => 'bonus_one']);

    completeOnce($this->user, $this->challenge);

    $bonus = XpTransaction::query()
        ->where('source_type', XpTransaction::SOURCE_ACHIEVEMENT)
        ->where('source_id', 'bonus_one')
        ->get();

    expect($bonus)->toHaveCount(1)
        ->and($bonus->first()->amount)->toBe(50);
});

it('never unlocks the same achievement twice', function () {
    /*
     * The rule stays satisfied after the first unlock, so every later completion
     * re-evaluates it. The unique index on (user_id, achievement_id) is what
     * makes the second insert impossible.
     */
    Achievement::factory()->afterCompleting(1)->worth(50)->create();

    $second = Challenge::factory()->published()->for($this->experience)
        ->create(['solution' => ['answer' => 'right']]);
    $third = Challenge::factory()->published()->for($this->experience)
        ->create(['solution' => ['answer' => 'right']]);

    completeOnce($this->user, $this->challenge);
    completeOnce($this->user, $second);
    completeOnce($this->user, $third);

    expect(DB::table('achievement_user')->where('user_id', $this->user->id)->count())->toBe(1)
        ->and(XpTransaction::query()->where('source_type', XpTransaction::SOURCE_ACHIEVEMENT)->count())->toBe(1);
});

it('ignores an inactive achievement', function () {
    // Retiring an achievement must stop new unlocks without deleting anyone's
    // history.
    Achievement::factory()->afterCompleting(1)->inactive()->create();

    completeOnce($this->user, $this->challenge);

    expect($this->user->achievements()->count())->toBe(0);
});

it('unlocks on a per-experience rule', function () {
    Achievement::factory()->requiringAll([
        ['experience' => 'test-experience', 'stat' => 'completed', 'gte' => 1],
    ])->create(['key' => 'experience_rule']);

    completeOnce($this->user, $this->challenge);

    expect($this->user->achievements()->pluck('key')->all())->toBe(['experience_rule']);
});

it('does not credit one experience for another experience work', function () {
    Achievement::factory()->requiringAll([
        ['experience' => 'some-other-experience', 'stat' => 'completed', 'gte' => 1],
    ])->create();

    completeOnce($this->user, $this->challenge);

    expect($this->user->achievements()->count())->toBe(0);
});

it('awards nothing for a failed attempt that meets no rule', function () {
    Achievement::factory()->afterCompleting(1)->create();

    completeOnce($this->user, $this->challenge, 'wrong');

    expect($this->user->achievements()->count())->toBe(0);
});

it('rewards persistence through failure', function () {
    // A failed attempt is data, not a verdict.
    Achievement::factory()->requiringAll([
        ['stat' => 'challenges_failed', 'gte' => 1],
    ])->create(['key' => 'undeterred']);

    completeOnce($this->user, $this->challenge, 'wrong');

    expect($this->user->achievements()->pluck('key')->all())->toBe(['undeterred']);
});

it('counts the unlock in the statistics read model', function () {
    Achievement::factory()->afterCompleting(1)->create();

    completeOnce($this->user, $this->challenge);

    expect($this->user->refresh()->statistic->achievements_unlocked)->toBe(1);
});

it('is safe to evaluate repeatedly outside a completion', function () {
    Achievement::factory()->afterCompleting(1)->worth(50)->create();

    completeOnce($this->user, $this->challenge);

    $unlocker = app(AchievementUnlocker::class);

    // A retry, or a future backfill, must not pay again.
    $unlocker->evaluateFor($this->user->refresh());
    $unlocker->evaluateFor($this->user->refresh());

    expect(DB::table('achievement_user')->count())->toBe(1)
        ->and(XpTransaction::query()->where('source_type', XpTransaction::SOURCE_ACHIEVEMENT)->count())->toBe(1);
});

it('does nothing for a user with no statistics yet', function () {
    Achievement::factory()->afterCompleting(0)->create();

    $stranger = User::factory()->create();

    expect(app(AchievementUnlocker::class)->evaluateFor($stranger))->toBe([]);
});

it('rebuilds achievement counts from source', function () {
    Achievement::factory()->afterCompleting(1)->create();

    completeOnce($this->user, $this->challenge);

    app(RefreshUserStatistics::class)->forUser($this->user);

    expect($this->user->refresh()->statistic->achievements_unlocked)->toBe(1);
});
