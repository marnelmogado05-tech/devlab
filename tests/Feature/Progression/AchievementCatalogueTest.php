<?php

declare(strict_types=1);

use App\Models\Achievement;
use App\Models\User;
use Database\Seeders\AchievementSeeder;

it('lists active achievements to a guest', function () {
    Achievement::factory()->create(['name' => 'First Blood']);
    Achievement::factory()->inactive()->create(['name' => 'Retired']);

    $this->get(route('achievements.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('achievements/index')
            ->has('achievements', 1)
            ->where('achievements.0.name', 'First Blood')
            ->where('achievements.0.unlocked', false)
        );
});

it('marks the ones the signed-in user holds', function () {
    $held = Achievement::factory()->create(['name' => 'Held']);
    Achievement::factory()->create(['name' => 'Not held']);

    $user = User::factory()->create();
    $user->achievements()->attach($held, ['unlocked_at' => now()]);

    $this->actingAs($user)
        ->get(route('achievements.index'))
        ->assertInertia(fn ($page) => $page
            ->where('unlocked_count', 1)
            ->where('achievements.0.unlocked', true)
            ->where('achievements.1.unlocked', false)
        );
});

it('conceals a secret achievement until it is earned', function () {
    Achievement::factory()->secret()->create([
        'name' => 'THE-SECRET-NAME',
        'description' => 'THE-SECRET-DESCRIPTION',
    ]);

    $response = $this->get(route('achievements.index'))->assertOk();

    // Withheld server-side: a hidden field in a prop is not hidden at all.
    expect($response->getContent())
        ->not->toContain('THE-SECRET-NAME')
        ->not->toContain('THE-SECRET-DESCRIPTION');
});

it('reveals a secret achievement to someone who has earned it', function () {
    $secret = Achievement::factory()->secret()->create(['name' => 'THE-SECRET-NAME']);

    $user = User::factory()->create();
    $user->achievements()->attach($secret, ['unlocked_at' => now()]);

    $response = $this->actingAs($user)->get(route('achievements.index'))->assertOk();

    expect($response->getContent())->toContain('THE-SECRET-NAME');
});

it('never sends the unlock rule to the client', function () {
    // Not secret exactly, but the rule engine's input — and publishing the exact
    // thresholds invites optimising for the badge rather than the challenge.
    Achievement::factory()->afterCompleting(42)->create();

    $this->get(route('achievements.index'))
        ->assertInertia(fn ($page) => $page->missing('achievements.0.criteria'));
});

it('seeds the starting achievements idempotently', function () {
    $this->seed(AchievementSeeder::class);
    $this->seed(AchievementSeeder::class);

    expect(Achievement::query()->count())->toBe(8)
        // The key is the XP ledger's source id, so it must be stable.
        ->and(Achievement::query()->where('key', 'first_blood')->exists())->toBeTrue();
});
