<?php

declare(strict_types=1);

use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\Experience;
use App\Models\User;

it('sends a guest straight to a challenge', function () {
    $experience = Experience::factory()->published()->create();
    $challenge = Challenge::factory()->published()->for($experience)->create();

    $this->get(route('bored'))
        ->assertRedirect(route('challenges.show', $challenge));
});

it('sends a signed-in user to a challenge', function () {
    $experience = Experience::factory()->published()->create();
    Challenge::factory()->published()->for($experience)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('bored'))
        ->assertRedirect();
});

it('falls back to the catalogue when there is nothing to recommend', function () {
    // A dead end is not an error page. Send them somewhere they can browse.
    $this->get(route('bored'))
        ->assertRedirect(route('experiences.index'));
});

it('never starts an attempt', function () {
    /*
     * Pressing the button must not create state. A GET that opened an attempt
     * would leave a trail of half-started challenges behind every refresh,
     * prefetch and crawler.
     */
    $experience = Experience::factory()->published()->create();
    Challenge::factory()->published()->for($experience)->create();

    $this->actingAs(User::factory()->create())->get(route('bored'));

    expect(ChallengeAttempt::query()->count())->toBe(0);
});

it('ignores anything the client tries to ask for', function () {
    /*
     * "I'm Bored" is not a search box. A client-supplied filter would turn the
     * one feature the product is named for into a worse version of the
     * catalogue.
     */
    $experience = Experience::factory()->published()->create();
    $only = Challenge::factory()->published()->for($experience)->create(['difficulty' => 'easy']);

    $this->get(route('bored', ['difficulty' => 'expert', 'experience' => 'nope']))
        ->assertRedirect(route('challenges.show', $only));
});
