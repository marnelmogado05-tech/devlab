<?php

declare(strict_types=1);

use App\Models\Challenge;
use App\Models\Experience;
use App\Models\User;
use Database\Seeders\ExperienceSeeder;

/*
 * The catalogue is public and read-only. What it may show is decided per row by
 * ExperiencePolicy, not by the listing query — the scope is a performance
 * measure, and a test that only proved the scope works would miss a direct hit
 * on /experiences/{slug}.
 */

it('lists published experiences to a guest', function () {
    Experience::factory()->published()->create(['name' => 'Cursed Code']);

    $this->get(route('experiences.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('experiences/index')
            ->has('experiences', 1)
            ->where('experiences.0.name', 'Cursed Code')
        );
});

it('hides draft and archived experiences from the catalogue', function () {
    Experience::factory()->published()->create(['name' => 'Visible']);
    Experience::factory()->create(['name' => 'Still a draft']);
    Experience::factory()->archived()->create(['name' => 'Retired']);

    $this->get(route('experiences.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('experiences', 1)
            ->where('experiences.0.name', 'Visible')
        );
});

it('counts only published challenges on a catalogue card', function () {
    $experience = Experience::factory()->published()->create();

    Challenge::factory()->published()->count(2)->for($experience)->create();
    Challenge::factory()->count(3)->for($experience)->create();

    $this->get(route('experiences.index'))
        ->assertInertia(fn ($page) => $page->where('experiences.0.challenges_count', 2));
});

it('shows a published experience', function () {
    $experience = Experience::factory()->published()->create(['slug' => 'cursed-code']);

    $this->get(route('experiences.show', $experience))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('experiences/show')
            ->where('experience.slug', 'cursed-code')
        );
});

it('refuses a draft experience even by direct link', function () {
    $experience = Experience::factory()->create();

    // 403 rather than 404: the policy denies it. Whether an unpublished slug
    // should be indistinguishable from a missing one is a real question, and the
    // answer is recorded where the behaviour is, not left implicit.
    $this->get(route('experiences.show', $experience))->assertForbidden();
});

it('refuses an archived experience', function () {
    $experience = Experience::factory()->archived()->create();

    $this->get(route('experiences.show', $experience))->assertForbidden();
});

it('lists only published challenges on an experience page', function () {
    $experience = Experience::factory()->published()->create();

    Challenge::factory()->published()->for($experience)->create(['title' => 'Shipped']);
    Challenge::factory()->for($experience)->create(['title' => 'Unfinished']);

    $this->get(route('experiences.show', $experience))
        ->assertInertia(fn ($page) => $page
            ->has('challenges.data', 1)
            ->where('challenges.data.0.title', 'Shipped')
        );
});

it('does not leak another experience\'s challenges', function () {
    $experience = Experience::factory()->published()->create();
    $other = Experience::factory()->published()->create();

    Challenge::factory()->published()->for($experience)->create(['title' => 'Mine']);
    Challenge::factory()->published()->for($other)->create(['title' => 'Theirs']);

    $this->get(route('experiences.show', $experience))
        ->assertInertia(fn ($page) => $page
            ->has('challenges.data', 1)
            ->where('challenges.data.0.title', 'Mine')
        );
});

it('paginates a long challenge list', function () {
    $experience = Experience::factory()->published()->create();
    $perPage = (int) config('devlab.catalogue.page_size');

    Challenge::factory()->published()->count($perPage + 3)->for($experience)->create();

    $this->get(route('experiences.show', $experience))
        ->assertInertia(fn ($page) => $page
            ->has('challenges.data', $perPage)
            ->where('challenges.total', $perPage + 3)
            ->where('challenges.last_page', 2)
        );
});

it('serves the catalogue to a signed-in user too', function () {
    Experience::factory()->published()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('experiences.index'))
        ->assertOk();
});

it('seeds the experiences idempotently', function () {
    // The seeder is safe to re-run: a maintainer editing a tagline should not
    // have to wonder whether they are about to create a second Cursed Code.
    //
    // Counted against itself rather than against a literal. The subject here is
    // idempotency, and a hardcoded total makes this test fail every time an
    // experience is added — which says nothing about whether re-running the
    // seeder duplicated anything.
    $this->seed(ExperienceSeeder::class);

    $afterOneRun = Experience::query()->count();

    $this->seed(ExperienceSeeder::class);

    expect($afterOneRun)->toBeGreaterThan(0)
        ->and(Experience::query()->count())->toBe($afterOneRun)
        ->and(Experience::query()->published()->count())->toBe($afterOneRun - 1);

    // Dev Roulette is the "I'm Bored" dispatcher, so it must not sit in its own
    // recommendation pool.
    $roulette = Experience::query()->where('slug', 'dev-roulette')->sole();

    expect($roulette->available_in_bored)->toBeFalse();
});
