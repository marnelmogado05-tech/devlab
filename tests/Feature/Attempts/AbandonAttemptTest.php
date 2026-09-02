<?php

declare(strict_types=1);

use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\Experience;
use App\Models\User;

beforeEach(function () {
    // Elapsed time is asserted exactly, so the clock must not move mid-test.
    $this->freezeTime();

    $experience = Experience::factory()->published()->create();
    $this->challenge = Challenge::factory()->published()->for($experience)->create();
    $this->owner = User::factory()->create();
    $this->attempt = ChallengeAttempt::factory()
        ->for($this->challenge)->for($this->owner)->create(['started_at' => now()->subMinutes(2)]);
});

it('closes an open attempt and records how long it ran', function () {
    $this->actingAs($this->owner)
        ->delete(route('attempts.destroy', $this->attempt))
        ->assertRedirect(route('challenges.show', $this->challenge));

    $this->attempt->refresh();

    expect($this->attempt->status)->toBe(ChallengeAttempt::STATUS_ABANDONED)
        ->and($this->attempt->time_taken_seconds)->toBe(120);
});

it('frees the challenge to be started again', function () {
    $this->actingAs($this->owner)->delete(route('attempts.destroy', $this->attempt));
    $this->actingAs($this->owner)->post(route('attempts.store', $this->challenge));

    expect(ChallengeAttempt::query()->open()->count())->toBe(1)
        ->and(ChallengeAttempt::query()->count())->toBe(2);
});

it('refuses to let anyone else abandon it', function () {
    $this->actingAs(User::factory()->create())
        ->delete(route('attempts.destroy', $this->attempt))
        ->assertForbidden();

    expect($this->attempt->refresh()->isOpen())->toBeTrue();
});

it('never overwrites a finished attempt', function () {
    // A stray abandon must not erase a completion — once scoring exists, that
    // is the record a score was derived from.
    $this->attempt->update([
        'status' => ChallengeAttempt::STATUS_COMPLETED,
        'completed_at' => now(),
        'time_taken_seconds' => 42,
    ]);

    $this->actingAs($this->owner)->delete(route('attempts.destroy', $this->attempt));

    $this->attempt->refresh();

    expect($this->attempt->status)->toBe(ChallengeAttempt::STATUS_COMPLETED)
        ->and($this->attempt->time_taken_seconds)->toBe(42);
});

it('is safe to abandon twice', function () {
    $this->actingAs($this->owner)->delete(route('attempts.destroy', $this->attempt));
    $this->actingAs($this->owner)->delete(route('attempts.destroy', $this->attempt))
        ->assertRedirect();

    expect($this->attempt->refresh()->status)->toBe(ChallengeAttempt::STATUS_ABANDONED);
});
