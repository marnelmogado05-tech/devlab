<?php

declare(strict_types=1);

use App\Models\Challenge;
use App\Models\Experience;
use App\Models\User;

/*
 * The front door. DevLab's pitch is "open it, press the button, get handed
 * something", so the thing this page must not do is fail to offer the button.
 */

it('is public', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('welcome'));
});

it('offers the button that the product is named for', function () {
    // It was absent from this page until now: the button existed on the
    // dashboard and the catalogue, but not on the page a stranger arrives at.
    $this->get(route('home'))->assertOk()->assertSee('/bored', escape: false);
});

it('shows the real challenge count', function () {
    /*
     * Read at request time rather than written into the copy. A landing page
     * claiming more content than exists is the kind of lie that costs a
     * contributor an evening.
     */
    $experience = Experience::factory()->published()->create();
    Challenge::factory()->published()->count(3)->for($experience)->create();
    Challenge::factory()->count(2)->for($experience)->create();

    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page->where('challengeCount', 3));
});

it('says so honestly when there is nothing published', function () {
    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page
            ->where('challengeCount', 0)
            ->has('experiences', 0)
        );
});

it('lists published experiences with their counts', function () {
    $experience = Experience::factory()->published()->create(['name' => 'Cursed Code']);
    Challenge::factory()->published()->count(2)->for($experience)->create();

    Experience::factory()->create(['name' => 'Still a draft']);

    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page
            ->has('experiences', 1)
            ->where('experiences.0.name', 'Cursed Code')
            ->where('experiences.0.challenges_count', 2)
        );
});

it('greets a signed-in user without changing what the page is for', function () {
    Experience::factory()->published()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('welcome'));
});

it('leaks nothing about a challenge', function () {
    // The landing page names experiences, never individual challenges — a
    // visitor should not be able to read a snippet before pressing anything.
    $experience = Experience::factory()->published()->create();
    Challenge::factory()->published()->for($experience)->create([
        'title' => 'THE-CHALLENGE-TITLE',
        'configuration' => ['snippet' => 'THE-SNIPPET'],
        'solution' => ['answer' => 'THE-ANSWER-KEY'],
    ]);

    $response = $this->get(route('home'))->assertOk();

    expect($response->getContent())
        ->not->toContain('THE-SNIPPET')
        ->not->toContain('THE-ANSWER-KEY')
        ->not->toContain('THE-CHALLENGE-TITLE');
});
