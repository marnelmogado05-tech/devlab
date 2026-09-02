<?php

declare(strict_types=1);

use App\Models\Achievement;
use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserStatistic;
use App\Models\XpTransaction;
use Database\Seeders\AchievementSeeder;
use Database\Seeders\CursedCodeSeeder;
use Database\Seeders\ExperienceSeeder;
use Illuminate\Support\Facades\DB;

/*
 * §38's end-to-end flow, as one chain:
 *
 *   register → browse → I'm Bored → start → complete → score → XP → achievement
 *
 * Every link is covered somewhere in Feature/, and that is exactly why this file
 * exists: a per-slice test proves each part works in isolation, and says nothing
 * about whether they agree with each other. The bugs this catches live in the
 * seams — a statistic refreshed before the ledger row it should count, an
 * achievement evaluated against yesterday's totals, a rank that never updates.
 *
 * It runs against the REAL seeded content rather than factories, so a change to
 * a seeder that breaks the loop fails here rather than in production.
 */

beforeEach(function () {
    $this->seed(ExperienceSeeder::class);
    $this->seed(AchievementSeeder::class);
    $this->seed(CursedCodeSeeder::class);
});

/** Register through the real endpoint, as a new player actually would. */
function registerPlayer(string $email = 'newcomer@example.com'): User
{
    /*
     * Registration signs the new user in, and Fortify redirects an authenticated
     * visitor away from /register rather than creating a second account — so a
     * test that registers twice has to sign out in between.
     */
    test()->post('/logout');

    test()->post('/register', [
        'name' => 'Ada Lovelace',
        'email' => $email,
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    test()->post('/logout');

    return User::query()->where('email', $email)->sole();
}

it('carries a new player from registration to a ranked, decorated profile', function () {
    // ── register ────────────────────────────────────────────────────────────
    $user = registerPlayer();

    // Registration gives a public identity, so the leaderboard has a handle and
    // /profile/{username} resolves immediately.
    expect($user->profile)->not->toBeNull();

    // ── browse ──────────────────────────────────────────────────────────────
    $this->get(route('experiences.index'))->assertOk();

    // ── "I'm Bored" assigns something ───────────────────────────────────────
    $assignment = $this->actingAs($user)->get(route('bored'))->assertOk();

    $slug = $assignment->viewData('page')['props']['assignment']['slug'];
    $challenge = Challenge::query()->where('slug', $slug)->sole();

    // Pressing the button creates nothing.
    expect(ChallengeAttempt::query()->count())->toBe(0);

    // ── start ───────────────────────────────────────────────────────────────
    $this->actingAs($user)->post(route('attempts.store', $challenge))->assertRedirect();

    $attempt = ChallengeAttempt::query()->open()->sole();

    // ── complete, with the answer the seeder recorded ────────────────────────
    $answer = $challenge->solution['answer'];

    $this->actingAs($user)
        ->post(route('attempts.submit', $attempt), ['submission' => ['answer' => $answer]])
        ->assertRedirect(route('attempts.show', $attempt));

    $attempt->refresh();
    $user->refresh();

    // ── score ───────────────────────────────────────────────────────────────
    expect($attempt->status)->toBe(ChallengeAttempt::STATUS_COMPLETED)
        ->and($attempt->score)->toBeGreaterThan(0)
        ->and($attempt->score)->toBeLessThanOrEqual($attempt->max_score);

    // ── XP: the completion award, plus the achievement bonus it triggered ────
    $challengeXp = (int) config("devlab.xp.{$challenge->difficulty}");
    $firstBlood = Achievement::query()->where('key', 'first_blood')->sole();

    expect(XpTransaction::query()->where('user_id', $user->id)->sum('amount'))
        ->toBe($challengeXp + $firstBlood->xp_bonus);

    // ── achievement ─────────────────────────────────────────────────────────
    expect($user->achievements()->pluck('key')->all())->toContain('first_blood');

    // ── statistics, recomputed AFTER the achievement bonus landed ───────────
    /*
     * The order matters and is the point of this assertion. Statistics are
     * refreshed once before achievements are evaluated (the rules read them) and
     * again after an unlock, because the bonus changes the total. Getting that
     * wrong leaves the profile showing an XP figure the ledger does not support.
     */
    $statistic = $user->statistic;

    expect($statistic->total_xp)->toBe($challengeXp + $firstBlood->xp_bonus)
        ->and($statistic->challenges_completed)->toBe(1)
        ->and($statistic->achievements_unlocked)->toBe(1);

    // ── the profile shows all of it ─────────────────────────────────────────
    $this->actingAs($user)
        ->get(route('profiles.show', $user->profile))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('progression.total_xp', $statistic->total_xp)
            ->has('achievements', 1)
            ->has('recent', 1)
        );

    // ── and the leaderboard ranks them ──────────────────────────────────────
    $this->actingAs($user)
        ->get(route('leaderboards.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('you.rank', 1));
});

it('keeps the ledger and the read model in agreement', function () {
    /*
     * `user_statistics.total_xp` is a cache of SUM(xp_transactions.amount).
     * ADR 0004's whole claim is that it is rebuildable from source, so the two
     * must agree after a real completion — not only after an explicit rebuild.
     */
    $user = registerPlayer();
    $challenge = Challenge::query()->published()->firstOrFail();

    $this->actingAs($user)->post(route('attempts.store', $challenge));
    $attempt = ChallengeAttempt::query()->open()->sole();

    $this->actingAs($user)->post(route('attempts.submit', $attempt), [
        'submission' => ['answer' => $challenge->solution['answer']],
    ]);

    $ledger = (int) XpTransaction::query()->where('user_id', $user->id)->sum('amount');

    expect($user->refresh()->statistic->total_xp)->toBe($ledger);

    // And a rebuild from source changes nothing.
    $this->artisan('devlab:rebuild-statistics')->assertSuccessful();

    expect($user->refresh()->statistic->total_xp)->toBe($ledger);
});

it('pays a player once for a challenge, however many times they replay it', function () {
    /*
     * The farming case, across the whole chain rather than at the ledger alone:
     * three completions must produce three attempts, one award, and a total that
     * does not move after the first.
     */
    $user = registerPlayer();
    $challenge = Challenge::query()->published()->firstOrFail();
    $answer = $challenge->solution['answer'];

    $totals = [];

    for ($i = 0; $i < 3; $i++) {
        $this->actingAs($user)->post(route('attempts.store', $challenge));
        $attempt = ChallengeAttempt::query()->open()->sole();

        $this->actingAs($user)->post(route('attempts.submit', $attempt), [
            'submission' => ['answer' => $answer],
        ]);

        $totals[] = (int) XpTransaction::query()->where('user_id', $user->id)->sum('amount');
    }

    expect(ChallengeAttempt::query()->where('status', 'completed')->count())->toBe(3)
        ->and(XpTransaction::query()->where('source_type', XpTransaction::SOURCE_CHALLENGE_COMPLETION)->count())->toBe(1)
        // The total is identical after every replay.
        ->and($totals[1])->toBe($totals[0])
        ->and($totals[2])->toBe($totals[0]);
});

it('rolls the whole completion back together if any part of it fails', function () {
    /*
     * The completion is one transaction: attempt, ledger and statistics land
     * together or not at all. A partial completion is the worst outcome — an
     * attempt closed with no XP is unrecoverable without an audit, because the
     * one-award-per-challenge constraint would refuse the retry.
     */
    $user = registerPlayer();
    $challenge = Challenge::query()->published()->firstOrFail();

    $this->actingAs($user)->post(route('attempts.store', $challenge));
    $attempt = ChallengeAttempt::query()->open()->sole();

    // Force the transaction to fail after the attempt is written, by making the
    // ledger insert impossible.
    DB::statement('ALTER TABLE xp_transactions ADD CONSTRAINT tmp_fail CHECK (amount < 0)');

    try {
        $this->actingAs($user)->post(route('attempts.submit', $attempt), [
            'submission' => ['answer' => $challenge->solution['answer']],
        ]);
    } catch (Throwable) {
        // The request failing is the point.
    } finally {
        DB::statement('ALTER TABLE xp_transactions DROP CONSTRAINT tmp_fail');
    }

    // The attempt is still open: nothing was half-committed.
    expect($attempt->refresh()->isOpen())->toBeTrue()
        ->and(XpTransaction::query()->count())->toBe(0);

    // And the retry succeeds normally.
    $this->actingAs($user)->post(route('attempts.submit', $attempt), [
        'submission' => ['answer' => $challenge->solution['answer']],
    ]);

    expect($attempt->refresh()->status)->toBe(ChallengeAttempt::STATUS_COMPLETED)
        ->and(XpTransaction::query()->count())->toBeGreaterThan(0);
});

it('never lets a failed attempt pay, but still counts it', function () {
    // Success rate is built on the difference between failed and abandoned, so
    // the whole chain has to keep them apart.
    $user = registerPlayer();
    $challenge = Challenge::query()->published()->firstOrFail();

    $this->actingAs($user)->post(route('attempts.store', $challenge));
    $attempt = ChallengeAttempt::query()->open()->sole();

    // A REAL option that happens to be wrong. An unknown key is refused by
    // validation and the attempt stays open — correct, but not what is under
    // test here.
    $wrong = collect($challenge->configuration['options'])
        ->pluck('key')
        ->first(fn (string $key) => $key !== $challenge->solution['answer']);

    $this->actingAs($user)->post(route('attempts.submit', $attempt), [
        'submission' => ['answer' => $wrong],
    ]);

    $user->refresh();

    expect($attempt->refresh()->status)->toBe(ChallengeAttempt::STATUS_FAILED)
        ->and(XpTransaction::query()->count())->toBe(0)
        ->and($user->statistic->challenges_failed)->toBe(1)
        ->and($user->statistic->challenges_completed)->toBe(0)
        ->and($user->achievements()->count())->toBe(0);
});

it('gives two players independent progressions', function () {
    /*
     * Everything in the chain is keyed by user. One shared key anywhere — a
     * ledger source id, an achievement unlock, a statistics row — would show up
     * here as one player's work appearing on another's profile.
     */
    $first = registerPlayer('first@example.com');
    $second = registerPlayer('second@example.com');

    $challenge = Challenge::query()->published()->firstOrFail();
    $answer = $challenge->solution['answer'];

    foreach ([$first, $second] as $user) {
        $this->actingAs($user)->post(route('attempts.store', $challenge));

        $attempt = ChallengeAttempt::query()->open()
            ->where('user_id', $user->id)->sole();

        $this->actingAs($user)->post(route('attempts.submit', $attempt), [
            'submission' => ['answer' => $answer],
        ]);
    }

    expect(XpTransaction::query()->where('user_id', $first->id)->sum('amount'))
        ->toBe((int) XpTransaction::query()->where('user_id', $second->id)->sum('amount'))
        ->and(UserStatistic::query()->count())->toBe(2)
        ->and(DB::table('achievement_user')->count())->toBe(2)
        ->and(Profile::query()->count())->toBe(2);
});
