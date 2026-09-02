<?php

declare(strict_types=1);

use App\Models\Challenge;
use App\Models\Experience;

it('shows a published challenge', function () {
    $experience = Experience::factory()->published()->create();
    $challenge = Challenge::factory()->published()->for($experience)->create([
        'slug' => 'off-by-one',
        'title' => 'Off by one',
    ]);

    $this->get(route('challenges.show', $challenge))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('challenges/show')
            ->where('challenge.slug', 'off-by-one')
            ->where('experience.slug', $experience->slug)
        );
});

it('refuses a draft challenge', function () {
    $experience = Experience::factory()->published()->create();
    $challenge = Challenge::factory()->for($experience)->create();

    $this->get(route('challenges.show', $challenge))->assertForbidden();
});

it('withdraws a published challenge when its experience is unpublished', function () {
    // Otherwise a direct link keeps serving content the maintainer believes they
    // pulled — unpublishing an experience has to mean something.
    $experience = Experience::factory()->create();
    $challenge = Challenge::factory()->published()->for($experience)->create();

    $this->get(route('challenges.show', $challenge))->assertForbidden();
});

it('404s an unknown challenge', function () {
    $this->get('/challenges/no-such-challenge')->assertNotFound();
});
