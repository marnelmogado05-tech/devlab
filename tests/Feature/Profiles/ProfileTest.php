<?php

declare(strict_types=1);

use App\Actions\Profiles\CreateProfile;
use App\Models\Achievement;
use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\Experience;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserStatistic;

describe('creating a profile', function () {
    it('gives every new registration a public identity', function () {
        // /profile/{username} must resolve for every user, and the leaderboard
        // needs a real handle rather than the account name.
        $user = User::factory()->create(['name' => 'Ada Lovelace']);

        $profile = app(CreateProfile::class)->handle($user);

        expect($profile->username)->toBe('ada-lovelace')
            ->and($profile->is_public)->toBeTrue();
    });

    it('is idempotent', function () {
        $user = User::factory()->create();

        $first = app(CreateProfile::class)->handle($user);
        $second = app(CreateProfile::class)->handle($user);

        expect($second->id)->toBe($first->id)
            ->and(Profile::query()->count())->toBe(1);
    });

    it('finds a free handle when the obvious one is taken', function () {
        app(CreateProfile::class)->handle(
            User::factory()->create(['name' => 'Ada Lovelace'])
        );

        $second = User::factory()->create(['name' => 'Ada Lovelace']);

        expect(app(CreateProfile::class)->handle($second)->username)->toBe('ada-lovelace-1');
    });

    it('treats a handle differing only by case as taken', function () {
        // "marnel" and "Marnel" must not be two people.
        app(CreateProfile::class)->handle(User::factory()->create(['name' => 'Marnel']));

        $second = app(CreateProfile::class)->handle(
            User::factory()->create(['name' => 'MARNEL'])
        );

        expect($second->username)->not->toBe('marnel')
            ->and(Profile::query()->count())->toBe(2);
    });

    it('falls back to the email when a name yields nothing usable', function () {
        // A name written entirely in a non-Latin script slugs to an empty string.
        $user = User::factory()->create(['name' => '日本語', 'email' => 'grace@example.com']);

        expect(app(CreateProfile::class)->handle($user)->username)->toBe('grace');
    });

    it('never produces a bare number', function () {
        // A numeric handle reads as an id and invites confusion with one.
        $user = User::factory()->create(['name' => '12345', 'email' => '999@example.com']);

        expect(ctype_digit(app(CreateProfile::class)->handle($user)->username))->toBeFalse();
    });

    it('backfills users who predate profiles', function () {
        User::factory()->count(3)->create();

        $this->artisan('devlab:backfill-profiles')
            ->expectsOutput('Created 3 profile(s).')
            ->assertSuccessful();

        // Idempotent: a second run creates nothing.
        $this->artisan('devlab:backfill-profiles')
            ->expectsOutput('Created 0 profile(s).')
            ->assertSuccessful();

        expect(Profile::query()->count())->toBe(3);
    });
});

describe('the public profile page', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->profile = app(CreateProfile::class)->handle($this->user);

        UserStatistic::query()->create([
            'user_id' => $this->user->id,
            'total_xp' => 600,
            'level' => 2,
            'challenges_started' => 10,
            'challenges_completed' => 6,
            'challenges_failed' => 2,
            'challenges_abandoned' => 2,
            'total_time_seconds' => 800,
            'longest_streak_days' => 4,
            'experiences_played' => 2,
        ]);
    });

    it('is public', function () {
        $this->get(route('profiles.show', $this->profile))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('profiles/show')
                ->where('profile.username', $this->profile->username)
                ->where('detailed', true)
            );
    });

    it('resolves by username, not id', function () {
        $this->get('/profile/'.$this->profile->username)->assertOk();
    });

    it('404s an unknown username', function () {
        $this->get('/profile/nobody-by-that-name')->assertNotFound();
    });

    it('derives level and progress from the ledger total', function () {
        $this->get(route('profiles.show', $this->profile))
            ->assertInertia(fn ($page) => $page
                ->where('progression.total_xp', 600)
                ->where('progression.level.level', 2)
                ->where('progression.next_level.level', 3)
            );
    });

    it('computes success rate over finished attempts, not started ones', function () {
        /*
         * An abandoned attempt is someone closing a tab, not someone getting it
         * wrong. Counting it as failure would make the figure a measure of
         * browsing habits. 6 completed of 8 finished = 0.75.
         */
        $this->get(route('profiles.show', $this->profile))
            ->assertInertia(fn ($page) => $page->where('statistics.success_rate', 0.75));
    });

    it('reports no success rate rather than zero when nothing is finished', function () {
        // "No data" and "never right" are different claims.
        $fresh = User::factory()->create();
        $profile = app(CreateProfile::class)->handle($fresh);

        $this->get(route('profiles.show', $profile))
            ->assertInertia(fn ($page) => $page->where('statistics.success_rate', null));
    });

    it('lists unlocked achievements', function () {
        $achievement = Achievement::factory()->create(['name' => 'First Blood']);
        $this->user->achievements()->attach($achievement, ['unlocked_at' => now()]);

        $this->get(route('profiles.show', $this->profile))
            ->assertInertia(fn ($page) => $page
                ->has('achievements', 1)
                ->where('achievements.0.name', 'First Blood')
            );
    });

    it('shows recent finished attempts without what was submitted', function () {
        /*
         * A profile records what someone did. Showing what they ANSWERED would
         * leak a challenge's content to anyone reading their profile.
         */
        $experience = Experience::factory()->published()->create();
        $challenge = Challenge::factory()->published()->for($experience)->create([
            'solution' => ['answer' => 'THE-ANSWER-KEY'],
        ]);

        ChallengeAttempt::factory()->completed()->for($challenge)->for($this->user)
            ->create(['submission' => ['answer' => 'THE-SUBMITTED-ANSWER']]);

        $response = $this->get(route('profiles.show', $this->profile))->assertOk();

        expect($response->getContent())
            ->not->toContain('THE-ANSWER-KEY')
            ->not->toContain('THE-SUBMITTED-ANSWER');

        $response->assertInertia(fn ($page) => $page->has('recent', 1));
    });
});

describe('privacy', function () {
    beforeEach(function () {
        $this->owner = User::factory()->create();
        $this->profile = app(CreateProfile::class)->handle($this->owner);
        $this->profile->update(['is_public' => false, 'bio' => 'THE-PRIVATE-BIO']);
    });

    it('still resolves and still shows level and rank', function () {
        /*
         * Hiding a private profile entirely would leave a gap in the leaderboard
         * numbering and quietly reward making yourself invisible. Level and rank
         * are already public there, so withholding them here would be a privacy
         * setting that hides nothing.
         */
        $this->get(route('profiles.show', $this->profile))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('detailed', false)
                ->has('progression')
            );
    });

    it('withholds activity detail from everyone else', function () {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('profiles.show', $this->profile))
            ->assertOk();

        expect($response->getContent())->not->toContain('THE-PRIVATE-BIO');

        $response->assertInertia(fn ($page) => $page
            ->where('statistics', null)
            ->where('achievements', null)
            ->where('recent', null)
            ->where('profile.bio', null)
        );
    });

    it('shows the owner their own profile in full', function () {
        $this->actingAs($this->owner)
            ->get(route('profiles.show', $this->profile))
            ->assertInertia(fn ($page) => $page
                ->where('detailed', true)
                ->where('isOwner', true)
                ->where('profile.bio', 'THE-PRIVATE-BIO')
            );
    });
});

describe('editing your profile', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        app(CreateProfile::class)->handle($this->user);
    });

    function profilePayload(array $overrides = []): array
    {
        return [
            'username' => 'new-handle',
            'display_name' => 'New Handle',
            'is_public' => true,
            ...$overrides,
        ];
    }

    it('updates the public identity', function () {
        $this->actingAs($this->user)
            ->put(route('public-profile.update'), profilePayload(['bio' => 'Hello.']))
            ->assertRedirect(route('profile.edit'));

        expect($this->user->refresh()->profile->username)->toBe('new-handle')
            ->and($this->user->profile->bio)->toBe('Hello.');
    });

    it('records the preferences the recommender reads', function () {
        // BoredomRecommendationService has read these since it was written and
        // nothing ever wrote them.
        $this->actingAs($this->user)->put(route('public-profile.update'), profilePayload([
            'preferred_difficulty' => 'hard',
            'technologies' => 'PHP, javascript ,, php, docker',
        ]));

        $preferences = $this->user->refresh()->profile->preferences;

        expect($preferences['difficulty'])->toBe('hard')
            // Lowercased, trimmed, de-duplicated, blanks dropped.
            ->and($preferences['technologies'])->toBe(['php', 'javascript', 'docker']);
    });

    it('refuses a username that is taken', function () {
        app(CreateProfile::class)->handle(User::factory()->create())
            ->update(['username' => 'taken']);

        $this->actingAs($this->user)
            ->put(route('public-profile.update'), profilePayload(['username' => 'taken']))
            ->assertSessionHasErrors('username');
    });

    it('refuses a username that differs only by case', function () {
        app(CreateProfile::class)->handle(User::factory()->create())
            ->update(['username' => 'taken']);

        $this->actingAs($this->user)
            ->put(route('public-profile.update'), profilePayload(['username' => 'TAKEN']))
            ->assertSessionHasErrors('username');
    });

    it('lets a user keep their own username', function () {
        $current = $this->user->profile->username;

        $this->actingAs($this->user)
            ->put(route('public-profile.update'), profilePayload(['username' => $current]))
            ->assertSessionHasNoErrors();
    });

    it('restricts the character set', function () {
        /*
         * A username appears in a URL and beside other people's names, so the
         * character set is restricted rather than merely escaped — that closes
         * off homoglyph impersonation instead of relying on every future view to
         * render it safely.
         */
        foreach (['has space', 'has/slash', 'has..dots', '-leading', 'trailing-', 'ünïcode'] as $bad) {
            $this->actingAs($this->user)
                ->put(route('public-profile.update'), profilePayload(['username' => $bad]))
                ->assertSessionHasErrors('username');
        }
    });

    it('refuses a website that is not an http url', function () {
        // `javascript:` in an href is the reason this is a url rule.
        $this->actingAs($this->user)
            ->put(route('public-profile.update'), profilePayload([
                'website' => 'javascript:alert(1)',
            ]))
            ->assertSessionHasErrors('website');
    });

    it('lets a profile be made private', function () {
        $this->actingAs($this->user)
            ->put(route('public-profile.update'), profilePayload(['is_public' => false]));

        expect($this->user->refresh()->profile->is_public)->toBeFalse();
    });

    it('requires an account', function () {
        $this->put(route('public-profile.update'), profilePayload())
            ->assertRedirect(route('login'));
    });

    it('cannot edit anybody else', function () {
        $other = User::factory()->create();
        $otherProfile = app(CreateProfile::class)->handle($other);

        $this->actingAs($this->user)
            ->put(route('public-profile.update'), profilePayload(['username' => 'mine-now']));

        // The route only ever touches the authenticated user's own profile.
        expect($otherProfile->refresh()->username)->not->toBe('mine-now');
    });
});
