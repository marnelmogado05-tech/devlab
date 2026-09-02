<?php

declare(strict_types=1);

use App\Models\Challenge;
use App\Models\Experience;

/*
 * Threat T3 — answer-key leakage.
 *
 * A solution is trivially convertible into rank, which is what makes it worth
 * stealing, and a leak is silent: the page looks correct. These tests search the
 * WHOLE response body rather than asserting on named props, because the failure
 * being guarded against is a prop nobody meant to send — `Inertia::render($challenge)`
 * would pass a prop-shape assertion while shipping the answer key.
 *
 * The factory plants recognisable markers for exactly this.
 */

beforeEach(function () {
    $this->experience = Experience::factory()->published()->create();

    $this->challenge = Challenge::factory()->published()->for($this->experience)->create([
        'solution' => ['answer' => 'THE-ANSWER-KEY', 'tests' => ['THE-TEST-CASE']],
        'explanation' => 'THE-EXPLANATION',
    ]);
});

it('never sends the answer key to the challenge page', function () {
    $response = $this->get(route('challenges.show', $this->challenge))->assertOk();

    expect($response->getContent())
        ->not->toContain('THE-ANSWER-KEY')
        ->not->toContain('THE-TEST-CASE')
        ->not->toContain('THE-EXPLANATION');
});

it('never sends the answer key in a challenge listing', function () {
    $response = $this->get(route('experiences.show', $this->experience))->assertOk();

    expect($response->getContent())
        ->not->toContain('THE-ANSWER-KEY')
        ->not->toContain('THE-TEST-CASE')
        ->not->toContain('THE-EXPLANATION');
});

it('does not expose solution or explanation as a prop under any name', function () {
    $this->get(route('challenges.show', $this->challenge))
        ->assertInertia(fn ($page) => $page
            ->missing('challenge.solution')
            ->missing('challenge.explanation')
        );
});

it('keeps the solution hidden when a challenge is serialised', function () {
    // Defence in depth: the controller whitelists, and the model also refuses to
    // serialise the key if some future code path returns the model directly.
    expect($this->challenge->toArray())->not->toHaveKey('solution');
});
