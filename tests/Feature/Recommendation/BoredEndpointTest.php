<?php

declare(strict_types=1);

use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\Experience;
use App\Models\User;

/*
 * Dev Roulette (§9.1). `/bored` RENDERS the assignment rather than redirecting to
 * it — the reveal is the product's moment, and a silent redirect made pressing
 * the button indistinguishable from clicking a link.
 */

function publishedChallengeFor(array $attributes = []): Challenge
{
    $experience = Experience::factory()->published()->create();

    return Challenge::factory()->published()->for($experience)->create($attributes);
}

it('reveals an assignment to a guest', function () {
    $challenge = publishedChallengeFor(['title' => 'Two tenths and a lie']);

    $this->get(route('bored'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('roulette/index')
            ->where('assignment.slug', $challenge->slug)
            ->where('assignment.title', 'Two tenths and a lie')
            ->where('signedIn', false)
        );
});

it('names the experience the assignment came from', function () {
    // Being told "Bug Hunter" is what makes an unfamiliar experience feel
    // assigned rather than stumbled into.
    $experience = Experience::factory()->published()->create(['name' => 'Bug Hunter']);
    Challenge::factory()->published()->for($experience)->create();

    $this->get(route('bored'))
        ->assertInertia(fn ($page) => $page->where('assignment.experience.name', 'Bug Hunter'));
});

it('shows the cost before you commit', function () {
    publishedChallengeFor(['difficulty' => 'hard', 'estimated_minutes' => 12]);

    $this->get(route('bored'))
        ->assertInertia(fn ($page) => $page
            ->where('assignment.difficulty', 'hard')
            ->where('assignment.estimated_minutes', 12)
        );
});

it('lets a signed-in user start immediately', function () {
    publishedChallengeFor();

    $this->actingAs(User::factory()->create())
        ->get(route('bored'))
        ->assertInertia(fn ($page) => $page->where('signedIn', true));
});

it('falls back to the catalogue when there is nothing to recommend', function () {
    $this->get(route('bored'))->assertRedirect(route('experiences.index'));
});

it('never starts an attempt', function () {
    /*
     * Pressing the button must not create state. A GET that opened an attempt
     * would leave a trail of half-started challenges behind every refresh,
     * prefetch and crawler — and "spin again" is a refresh by design.
     */
    publishedChallengeFor();

    $this->actingAs(User::factory()->create())->get(route('bored'));
    $this->actingAs(User::factory()->create())->get(route('bored'));

    expect(ChallengeAttempt::query()->count())->toBe(0);
});

it('does not spoil the challenge on the reveal', function () {
    // configuration belongs to the play page. Showing it here would spoil
    // anything a player re-spins past.
    publishedChallengeFor([
        'configuration' => ['snippet' => 'THE-SNIPPET'],
        'solution' => ['answer' => 'THE-ANSWER-KEY'],
        'explanation' => 'THE-EXPLANATION',
    ]);

    $response = $this->get(route('bored'))->assertOk();

    expect($response->getContent())
        ->not->toContain('THE-SNIPPET')
        ->not->toContain('THE-ANSWER-KEY')
        ->not->toContain('THE-EXPLANATION');
});

it('ignores anything the client tries to ask for', function () {
    /*
     * "I'm Bored" is not a search box. A client-supplied filter would turn the
     * one feature the product is named for into a worse version of the
     * catalogue.
     */
    $only = publishedChallengeFor(['difficulty' => 'easy']);

    $this->get(route('bored', ['difficulty' => 'expert', 'experience' => 'nope']))
        ->assertInertia(fn ($page) => $page->where('assignment.slug', $only->slug));
});
