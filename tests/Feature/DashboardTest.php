<?php

declare(strict_types=1);

use App\Models\Achievement;
use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\Experience;
use App\Models\User;
use App\Models\UserStatistic;

/*
 * The dashboard is a read model of a read model: everything on it already
 * exists in the ledger, the statistics table or the attempts table. So what is
 * worth testing is not that the numbers are right — that is tested where they
 * are computed — but that this page does not invent, leak or contradict them.
 */

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->experience = Experience::factory()->published()->create(['name' => 'Cursed Code']);

    $this->challenge = Challenge::factory()
        ->published()
        ->for($this->experience)
        ->create(['title' => 'Floating point', 'difficulty' => 'medium']);
});

it('is closed to guests', function () {
    $this->get(route('dashboard'))->assertRedirect();
});

it('greets a brand new player without pretending they have a history', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('progression.total_xp', 0)
            ->where('statistics.challenges_completed', 0)
            // Null, not zero: they have never been wrong either.
            ->where('statistics.success_rate', null)
            ->has('openAttempts', 0)
            ->has('recent', 0)
            ->has('achievements', 0)
        );
});

it('surfaces an attempt still open, with the deadline it will be expired at', function () {
    /*
     * The reason this page exists. An attempt left open is expired by the
     * scheduler without telling anyone, so a dashboard that does not mention it
     * is how someone loses work they thought they had saved.
     */
    $this->freezeTime();

    $attempt = ChallengeAttempt::factory()
        ->for($this->challenge)->for($this->user)
        ->create(['started_at' => now()]);

    $expected = now()
        ->addMinutes((int) config('devlab.attempts.expire_after_minutes'))
        ->toIso8601String();

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('openAttempts', 1)
            ->where('openAttempts.0.id', $attempt->id)
            ->where('openAttempts.0.challenge.title', 'Floating point')
            ->where('openAttempts.0.experience', 'Cursed Code')
            ->where('openAttempts.0.expires_at', $expected)
        );
});

it('does not offer a finished attempt as something to resume', function () {
    ChallengeAttempt::factory()
        ->for($this->challenge)->for($this->user)
        ->create(['status' => ChallengeAttempt::STATUS_COMPLETED, 'completed_at' => now()]);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('openAttempts', 0)
            ->has('recent', 1)
        );
});

it('shows nobody else their work', function () {
    // Everything here is keyed by user. A missing where clause would surface as
    // one player's open attempt appearing on another's dashboard.
    $stranger = User::factory()->create();

    ChallengeAttempt::factory()
        ->for($this->challenge)->for($stranger)
        ->create(['started_at' => now()]);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('openAttempts', 0));
});

it('never sends an answer to a page that only lists titles', function () {
    /*
     * The recent list names challenges the player has finished. Sending the
     * submission or the score alongside would leak a challenge's content onto a
     * page nobody thinks of as showing it — the same reason the profile omits
     * them.
     */
    ChallengeAttempt::factory()
        ->for($this->challenge)->for($this->user)
        ->create([
            'status' => ChallengeAttempt::STATUS_COMPLETED,
            'completed_at' => now(),
            'submission' => ['answer' => 'THE-ANSWER'],
            'score' => 90,
        ]);

    $response = $this->actingAs($this->user)->get(route('dashboard'))->assertOk();

    $props = $response->viewData('page')['props'];

    expect(json_encode($props['recent']))
        ->not->toContain('THE-ANSWER')
        ->not->toContain('score');
});

it('reports the success rate the profile reports', function () {
    /*
     * Two pages computing the same figure two ways is how a player learns to
     * believe neither. Abandoned attempts are excluded from both: closing a tab
     * is not getting it wrong.
     */
    UserStatistic::query()->updateOrCreate(['user_id' => $this->user->id], [
        'challenges_completed' => 3,
        'challenges_failed' => 1,
        'challenges_abandoned' => 6,
    ]);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('statistics.success_rate', 0.75));
});

it('lists the most recent unlocks, newest first', function () {
    $older = Achievement::factory()->create(['name' => 'Older']);
    $newer = Achievement::factory()->create(['name' => 'Newer']);

    $this->user->achievements()->attach($older, ['unlocked_at' => now()->subDay()]);
    $this->user->achievements()->attach($newer, ['unlocked_at' => now()]);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('achievements', 2)
            ->where('achievements.0.name', 'Newer')
            ->where('achievements.1.name', 'Older')
        );
});
