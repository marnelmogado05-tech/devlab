<?php

declare(strict_types=1);

use App\Actions\Attempts\ExpireStaleAttempts;
use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\Experience;
use App\Models\User;

beforeEach(function () {
    $experience = Experience::factory()->published()->create();
    $this->challenge = Challenge::factory()->published()->for($experience)->create();
    $this->user = User::factory()->create();
});

it('expires an attempt left open past the window', function () {
    $attempt = ChallengeAttempt::factory()->stale()
        ->for($this->challenge)->for($this->user)->create();

    expect(app(ExpireStaleAttempts::class)->handle())->toBe(1);

    expect($attempt->refresh()->status)->toBe(ChallengeAttempt::STATUS_EXPIRED);
});

it('records how long an expired attempt was actually open', function () {
    $attempt = ChallengeAttempt::factory()
        ->for($this->challenge)->for($this->user)
        ->create(['started_at' => now()->subMinutes(200)]);

    app(ExpireStaleAttempts::class)->handle();

    // 200 minutes, allowing for the seconds the test itself takes.
    expect($attempt->refresh()->time_taken_seconds)->toBeGreaterThanOrEqual(12_000);
});

it('leaves a fresh attempt alone', function () {
    $attempt = ChallengeAttempt::factory()
        ->for($this->challenge)->for($this->user)->create();

    expect(app(ExpireStaleAttempts::class)->handle())->toBe(0)
        ->and($attempt->refresh()->isOpen())->toBeTrue();
});

it('does not touch attempts that already finished', function () {
    $completed = ChallengeAttempt::factory()->completed()
        ->for($this->challenge)->for($this->user)
        ->create(['started_at' => now()->subDays(3)]);

    app(ExpireStaleAttempts::class)->handle();

    expect($completed->refresh()->status)->toBe(ChallengeAttempt::STATUS_COMPLETED);
});

it('frees the challenge to be started again', function () {
    // Expiry has to release the one-open-attempt slot, or a user who left a tab
    // open is locked out of that challenge forever.
    ChallengeAttempt::factory()->stale()
        ->for($this->challenge)->for($this->user)->create();

    app(ExpireStaleAttempts::class)->handle();

    $this->actingAs($this->user)->post(route('attempts.store', $this->challenge));

    expect(ChallengeAttempt::query()->open()->count())->toBe(1)
        ->and(ChallengeAttempt::query()->count())->toBe(2);
});

it('is exposed as a command', function () {
    ChallengeAttempt::factory()->stale()
        ->for($this->challenge)->for($this->user)->create();

    $this->artisan('devlab:expire-attempts')
        ->expectsOutputToContain('Expired 1 attempt(s).')
        ->assertSuccessful();
});
