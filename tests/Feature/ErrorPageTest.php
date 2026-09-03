<?php

declare(strict_types=1);

use App\Models\Challenge;
use App\Models\Experience;
use App\Models\User;

/*
 * §57 lists error handling as part of a feature being done. Before this, every
 * 404 and 403 rendered Symfony's stock page: no navigation, no theme, no way
 * back — the reader was simply outside the application.
 *
 * These tests pin the two things worth pinning: the status code is preserved
 * (an error page returning 200 is invisible to crawlers and monitoring), and
 * the response is a real Inertia page rather than a blade fallback.
 */

it('renders a missing challenge inside the application', function () {
    $this->get('/challenges/no-such-challenge')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page
            ->component('error')
            ->where('status', 404)
        );
});

it('renders an unpublished challenge as a forbidden page, not a 404', function () {
    /*
     * The distinction is deliberate and worth a test. A draft challenge exists;
     * saying "not found" would be a lie that also makes the draft invisible to
     * the editor who is working on it. The policy refuses, and the reader is
     * told the truth.
     */
    $experience = Experience::factory()->create();

    $challenge = Challenge::factory()
        ->for($experience)
        ->create(['status' => 'draft']);

    $this->actingAs(User::factory()->create())
        ->get(route('challenges.show', $challenge))
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page
            ->component('error')
            ->where('status', 403)
        );
});

/*
 * JSON error shape is not asserted here: `shouldRenderJsonWhen` predates this
 * change, and the test harness deliberately rethrows exceptions on JSON
 * requests so a developer sees the real error rather than a rendered payload.
 * Pinning it would mean disabling that, which costs more than it proves.
 */
