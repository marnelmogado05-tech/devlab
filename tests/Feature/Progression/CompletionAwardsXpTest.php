<?php

declare(strict_types=1);

use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\Experience;
use App\Models\User;
use App\Models\XpTransaction;
use App\Services\Challenge\EvaluatorRegistry;
use Tests\Support\FakeEvaluator;

/*
 * The reward path, end to end. §67 question 5: "is there a test that runs the
 * operation twice and asserts one award?" — several, below.
 */

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

function startAndSubmit(User $user, Challenge $challenge, string $answer = 'right'): void
{
    test()->actingAs($user)->post(route('attempts.store', $challenge));

    $attempt = ChallengeAttempt::query()
        ->where('user_id', $user->id)->where('challenge_id', $challenge->id)
        ->open()->sole();

    test()->actingAs($user)->post(route('attempts.submit', $attempt), [
        'submission' => ['answer' => $answer],
    ]);
}

it('awards xp for the difficulty on a correct completion', function () {
    startAndSubmit($this->user, $this->challenge);

    expect(XpTransaction::query()->where('user_id', $this->user->id)->sum('amount'))
        ->toBe((int) config('devlab.xp.medium'));
});

it('awards nothing for a wrong answer', function () {
    startAndSubmit($this->user, $this->challenge, 'wrong');

    expect(XpTransaction::query()->count())->toBe(0);
});

it('pays for a challenge once, however many times it is replayed', function () {
    /*
     * The farming case. A user may replay a challenge as often as they like —
     * only the first completion pays, enforced by the ledger's unique index on
     * (user_id, source_type, source_id) with the CHALLENGE as the source id.
     * Keying it by attempt would pay again on every replay.
     */
    startAndSubmit($this->user, $this->challenge);
    startAndSubmit($this->user, $this->challenge);
    startAndSubmit($this->user, $this->challenge);

    expect(ChallengeAttempt::query()->where('status', 'completed')->count())->toBe(3)
        ->and(XpTransaction::query()->count())->toBe(1)
        ->and($this->user->statistic->total_xp)->toBe((int) config('devlab.xp.medium'));
});

it('pays each user separately for the same challenge', function () {
    $other = User::factory()->create();

    startAndSubmit($this->user, $this->challenge);
    startAndSubmit($other, $this->challenge);

    expect(XpTransaction::query()->count())->toBe(2);
});

it('writes xp inside the completion transaction, not from a job', function () {
    // ADR 0005: a dropped job must never mean lost XP. No queue is processed
    // here, and the award still exists the moment the request finishes.
    startAndSubmit($this->user, $this->challenge);

    expect(XpTransaction::query()->count())->toBe(1);
});

it('refreshes the statistics read model on completion', function () {
    startAndSubmit($this->user, $this->challenge);

    $statistic = $this->user->refresh()->statistic;

    expect($statistic)->not->toBeNull()
        ->and($statistic->total_xp)->toBe((int) config('devlab.xp.medium'))
        ->and($statistic->challenges_completed)->toBe(1)
        ->and($statistic->challenges_started)->toBe(1)
        ->and($statistic->experiences_played)->toBe(1)
        ->and($statistic->recalculated_at)->not->toBeNull();
});

it('counts a failure separately from a completion', function () {
    startAndSubmit($this->user, $this->challenge, 'wrong');

    $statistic = $this->user->refresh()->statistic;

    expect($statistic->challenges_failed)->toBe(1)
        ->and($statistic->challenges_completed)->toBe(0)
        ->and($statistic->total_xp)->toBe(0);
});

it('raises the level once enough xp is banked', function () {
    // Level 2 needs 500 XP; an expert challenge is worth 500.
    $expert = Challenge::factory()->published()->for($this->experience)->create([
        'solution' => ['answer' => 'right'],
        'difficulty' => 'expert',
    ]);

    startAndSubmit($this->user, $expert);

    expect($this->user->refresh()->statistic->level)->toBe(2);
});
