<?php

declare(strict_types=1);

use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\Experience;
use App\Models\Profile;
use App\Models\User;
use App\Services\Recommendation\BoredomRecommendationService;
use Random\Engine\Mt19937;
use Random\Randomizer;

/*
 * Randomness is the feature (§10), which makes it the hard thing to test: an
 * assertion that "it returns something" would pass on a recommender that always
 * returned the same challenge.
 *
 * So the Randomizer is injected. A seeded engine makes a draw exactly
 * reproducible, and the distribution tests run many draws and assert on the
 * shape of the result rather than on any single one.
 */

function seededService(int $seed = 1): BoredomRecommendationService
{
    return new BoredomRecommendationService(new Randomizer(new Mt19937($seed)));
}

function publishedChallenge(?Experience $experience = null, array $attributes = []): Challenge
{
    $experience ??= Experience::factory()->published()->create();

    return Challenge::factory()->published()->for($experience)->create($attributes);
}

it('recommends a published challenge', function () {
    $challenge = publishedChallenge();

    expect(seededService()->recommend()?->id)->toBe($challenge->id);
});

it('recommends nothing when there is nothing published', function () {
    // Draft content must not be handed to anyone.
    Challenge::factory()->create();

    expect(seededService()->recommend())->toBeNull();
});

it('ignores an experience pulled from the pool', function () {
    // available_in_bored is the escape hatch for an experience that is broken
    // but should stay browsable.
    $pulled = Experience::factory()->published()->hiddenFromBored()->create();
    publishedChallenge($pulled);

    expect(seededService()->recommend())->toBeNull();
});

it('ignores challenges from an unpublished experience', function () {
    publishedChallenge(Experience::factory()->create());

    expect(seededService()->recommend())->toBeNull();
});

it('recommends to a guest', function () {
    // Being handed something before signing up is the entire pitch.
    publishedChallenge();

    expect(seededService()->recommend(null))->not->toBeNull();
});

it('does not recommend something just completed', function () {
    $user = User::factory()->create();
    $recent = publishedChallenge();

    ChallengeAttempt::factory()->completed()->for($recent)->for($user)->create([
        'completed_at' => now()->subDay(),
    ]);

    expect(seededService()->recommend($user))->toBeNull();
});

it('lets an old completion back into the pool', function () {
    /*
     * Otherwise the pool only ever shrinks, and an active user eventually gets
     * nothing. Replaying something from months ago is a fine recommendation.
     */
    $user = User::factory()->create();
    $old = publishedChallenge();

    ChallengeAttempt::factory()->completed()->for($old)->for($user)->create([
        'completed_at' => now()->subDays((int) config('devlab.bored.exclude_completed_days') + 1),
    ]);

    expect(seededService()->recommend($user)?->id)->toBe($old->id);
});

it('still recommends a challenge that was only attempted, not finished', function () {
    $user = User::factory()->create();
    $abandoned = publishedChallenge();

    ChallengeAttempt::factory()->abandoned()->for($abandoned)->for($user)->create();

    expect(seededService()->recommend($user)?->id)->toBe($abandoned->id);
});

it('favours an experience the user has never played', function () {
    $familiar = Experience::factory()->published()->create();
    $strange = Experience::factory()->published()->create();

    $known = publishedChallenge($familiar);
    $unknown = publishedChallenge($strange);

    $user = User::factory()->create();
    ChallengeAttempt::factory()->abandoned()->for($known)->for($user)->create();

    $picks = ['known' => 0, 'unknown' => 0];

    for ($seed = 1; $seed <= 200; $seed++) {
        $pick = seededService($seed)->recommend($user);
        $picks[$pick->id === $unknown->id ? 'unknown' : 'known']++;
    }

    // Weighted, not filtered: the familiar experience must stay reachable.
    expect($picks['unknown'])->toBeGreaterThan($picks['known'])
        ->and($picks['known'])->toBeGreaterThan(0);
});

it('favours the difficulty a user prefers', function () {
    $experience = Experience::factory()->published()->create();
    $easy = publishedChallenge($experience, ['difficulty' => 'easy']);
    $expert = publishedChallenge($experience, ['difficulty' => 'expert']);

    $user = User::factory()->create();
    Profile::factory()->for($user)->create(['preferences' => ['difficulty' => 'expert']]);

    $picks = ['easy' => 0, 'expert' => 0];

    for ($seed = 1; $seed <= 200; $seed++) {
        $pick = seededService($seed)->recommend($user);
        $picks[$pick->id === $expert->id ? 'expert' : 'easy']++;
    }

    expect($picks['expert'])->toBeGreaterThan($picks['easy'])
        ->and($picks['easy'])->toBeGreaterThan(0);
});

it('favours a technology the user prefers', function () {
    $experience = Experience::factory()->published()->create();
    $rust = publishedChallenge($experience, ['tags' => ['rust']]);
    $php = publishedChallenge($experience, ['tags' => ['php']]);

    $user = User::factory()->create();
    Profile::factory()->for($user)->create(['preferences' => ['technologies' => ['rust']]]);

    $picks = ['rust' => 0, 'php' => 0];

    for ($seed = 1; $seed <= 200; $seed++) {
        $pick = seededService($seed)->recommend($user);
        $picks[$pick->id === $rust->id ? 'rust' : 'php']++;
    }

    expect($picks['rust'])->toBeGreaterThan($picks['php']);
});

it('sometimes ignores every preference on purpose', function () {
    /*
     * The wildcard. With the weights alone the unplayed experience would win
     * overwhelmingly; the point of this test is that the "wrong" answer still
     * comes up, because that is the mechanic the product is named for.
     */
    $familiar = Experience::factory()->published()->create();
    $strange = Experience::factory()->published()->create();

    $known = publishedChallenge($familiar);
    publishedChallenge($strange);

    $user = User::factory()->create();
    ChallengeAttempt::factory()->abandoned()->for($known)->for($user)->create();

    $sawKnown = false;

    for ($seed = 1; $seed <= 300 && ! $sawKnown; $seed++) {
        $sawKnown = seededService($seed)->recommend($user)->id === $known->id;
    }

    expect($sawKnown)->toBeTrue();
});

it('is reproducible from a seed', function () {
    // Not a property of the product, but the property that makes every test
    // above meaningful rather than flaky.
    foreach (range(1, 5) as $i) {
        publishedChallenge();
    }

    $user = User::factory()->create();

    expect(seededService(42)->recommend($user)?->id)
        ->toBe(seededService(42)->recommend($user)?->id);
});

it('recommends something even when every weight cancels out', function () {
    // A zero-weight pool must still answer: the user asked for something to do.
    config()->set('devlab.bored.weights', [
        'unplayed_experience' => -100.0,
        'preferred_difficulty' => 0.0,
        'preferred_technology' => 0.0,
        'popularity' => 0.0,
        'recency_penalty' => -100.0,
    ]);
    config()->set('devlab.bored.wildcard_chance', 0.0);

    publishedChallenge();

    expect(seededService()->recommend(User::factory()->create()))->not->toBeNull();
});
