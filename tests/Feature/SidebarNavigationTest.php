<?php

declare(strict_types=1);

use App\Models\Experience;

/*
 * The sidebar's experience list is shared on every request rather than kept in
 * the frontend, so that publishing an experience puts it in the navigation and
 * un-publishing takes it out. These tests pin that, because the failure mode is
 * silent: a hardcoded list stays plausible long after it stops being true.
 */

it('shares the published experiences with every page', function () {
    Experience::factory()->published()->create(['name' => 'Cursed Code', 'slug' => 'cursed-code']);

    $this->get(route('experiences.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('navExperiences', 1)
            ->where('navExperiences.0.slug', 'cursed-code')
            ->where('navExperiences.0.name', 'Cursed Code')
        );
});

it('shares them with a guest, because the catalogue is public', function () {
    Experience::factory()->published()->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('navExperiences', 1));
});

it('never lists an unpublished experience', function () {
    // The sidebar is navigation, not a preview. A draft experience linked from
    // every page is a draft nobody can open.
    Experience::factory()->create(['status' => Experience::STATUS_DRAFT]);

    $this->get(route('experiences.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('navExperiences', 0));
});

it('shares only what the sidebar needs', function () {
    /*
     * Shared props ride on every response, so this one stays three columns.
     * `description` and `config` in particular have no business being sent to
     * every page to render a link.
     */
    Experience::factory()->published()->create();

    $this->get(route('experiences.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('navExperiences.0', fn ($experience) => $experience
                ->hasAll(['slug', 'name', 'icon'])
                ->etc()
            )
        );
});

it('lists them in catalogue order', function () {
    Experience::factory()->published()->create(['name' => 'Second', 'sort_order' => 2]);
    Experience::factory()->published()->create(['name' => 'First', 'sort_order' => 1]);

    $this->get(route('experiences.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('navExperiences.0.name', 'First')
            ->where('navExperiences.1.name', 'Second')
        );
});
