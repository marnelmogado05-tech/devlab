<?php

declare(strict_types=1);

use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\Experience;
use App\Models\User;

/*
 * Attempt ids are sequential, so /attempts/{id} is guessable by construction.
 * This is DevLab's first IDOR surface and the tests treat it as one.
 */

beforeEach(function () {
    $experience = Experience::factory()->published()->create();
    $this->challenge = Challenge::factory()->published()->for($experience)->create([
        'solution' => ['answer' => 'THE-ANSWER-KEY'],
        'explanation' => 'THE-EXPLANATION',
        'configuration' => ['snippet' => 'THE-SNIPPET'],
    ]);

    $this->owner = User::factory()->create();
    $this->attempt = ChallengeAttempt::factory()
        ->for($this->challenge)->for($this->owner)->create();
});

it('shows an attempt to its owner', function () {
    $this->actingAs($this->owner)
        ->get(route('attempts.show', $this->attempt))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('attempts/show')
            ->where('attempt.id', $this->attempt->id)
        );
});

it('refuses to show an attempt to anyone else', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('attempts.show', $this->attempt))
        ->assertForbidden();
});

it('requires an account to view an attempt', function () {
    $this->get(route('attempts.show', $this->attempt))
        ->assertRedirect(route('login'));
});

it('sends the playable payload but never the answer key', function () {
    // The in-progress request is where a leak is worth the most: the answer is
    // worth exactly the score it would buy.
    $response = $this->actingAs($this->owner)
        ->get(route('attempts.show', $this->attempt))
        ->assertOk();

    expect($response->getContent())
        ->toContain('THE-SNIPPET')
        ->not->toContain('THE-ANSWER-KEY')
        ->not->toContain('THE-EXPLANATION');
});

it('computes elapsed time on the server, not from the client', function () {
    $this->attempt->update(['started_at' => now()->subMinutes(3)]);

    $this->actingAs($this->owner)
        ->get(route('attempts.show', $this->attempt))
        ->assertInertia(fn ($page) => $page->where('attempt.elapsed_seconds', 180));
});
