<?php

declare(strict_types=1);

use App\Actions\Attempts\StartAttempt;
use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\Experience;
use App\Models\User;

beforeEach(function () {
    $this->experience = Experience::factory()->published()->create();
    $this->challenge = Challenge::factory()->published()->for($this->experience)->create();
    $this->user = User::factory()->create();
});

it('opens an attempt and sends the player to it', function () {
    $this->actingAs($this->user)
        ->post(route('attempts.store', $this->challenge))
        ->assertRedirect();

    $attempt = ChallengeAttempt::query()->sole();

    expect($attempt->user_id)->toBe($this->user->id)
        ->and($attempt->challenge_id)->toBe($this->challenge->id)
        ->and($attempt->status)->toBe(ChallengeAttempt::STATUS_STARTED);
});

it('snapshots the challenge version being played', function () {
    // Without this, correcting a wrong answer key leaves no way to identify
    // which attempts were scored against the broken version (§71).
    $this->challenge->update(['version' => 7]);

    $this->actingAs($this->user)->post(route('attempts.store', $this->challenge));

    expect(ChallengeAttempt::query()->sole()->challenge_version)->toBe(7);
});

it('returns the same attempt when start is pressed twice', function () {
    // The double-click case. A second attempt would be a second started_at, and
    // therefore a shorter elapsed time to submit against once scoring exists.
    $this->actingAs($this->user)->post(route('attempts.store', $this->challenge));
    $this->actingAs($this->user)->post(route('attempts.store', $this->challenge));

    expect(ChallengeAttempt::query()->count())->toBe(1);
});

it('resolves a concurrent start without failing the request', function () {
    /*
     * Simulates the race the partial unique index exists for: another request
     * inserts an open attempt between this action's read and its own insert.
     * The action must return the winner's row, not a 500.
     */
    $action = new class extends StartAttempt
    {
        public bool $raced = false;

        public function handle(User $user, Challenge $challenge): ChallengeAttempt
        {
            if (! $this->raced) {
                $this->raced = true;

                ChallengeAttempt::query()->create([
                    'user_id' => $user->id,
                    'challenge_id' => $challenge->id,
                    'challenge_version' => $challenge->version,
                    'status' => ChallengeAttempt::STATUS_STARTED,
                    'started_at' => now(),
                    'metadata' => [],
                ]);
            }

            return parent::handle($user, $challenge);
        }
    };

    $attempt = $action->handle($this->user, $this->challenge);

    expect($attempt->exists)->toBeTrue()
        ->and(ChallengeAttempt::query()->count())->toBe(1);
});

it('lets a user start again after finishing', function () {
    // Replaying a challenge is allowed; only the live attempt is exclusive.
    ChallengeAttempt::factory()->completed()
        ->for($this->challenge)->for($this->user)->create();

    $this->actingAs($this->user)->post(route('attempts.store', $this->challenge));

    expect(ChallengeAttempt::query()->count())->toBe(2)
        ->and(ChallengeAttempt::query()->open()->count())->toBe(1);
});

it('gives two users their own attempts at the same challenge', function () {
    $other = User::factory()->create();

    $this->actingAs($this->user)->post(route('attempts.store', $this->challenge));
    $this->actingAs($other)->post(route('attempts.store', $this->challenge));

    expect(ChallengeAttempt::query()->open()->count())->toBe(2);
});

it('refuses to open an attempt at an unpublished challenge', function () {
    $draft = Challenge::factory()->for($this->experience)->create();

    $this->actingAs($this->user)
        ->post(route('attempts.store', $draft))
        ->assertForbidden();

    expect(ChallengeAttempt::query()->count())->toBe(0);
});

it('refuses to open an attempt when the experience is unpublished', function () {
    $experience = Experience::factory()->create();
    $challenge = Challenge::factory()->published()->for($experience)->create();

    $this->actingAs($this->user)
        ->post(route('attempts.store', $challenge))
        ->assertForbidden();
});

it('requires an account to start an attempt', function () {
    // Browsing is public; attempts belong to somebody.
    $this->post(route('attempts.store', $this->challenge))
        ->assertRedirect(route('login'));

    expect(ChallengeAttempt::query()->count())->toBe(0);
});
