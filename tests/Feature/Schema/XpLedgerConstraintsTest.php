<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tests\Support\SchemaRows;

/*
 * The anti-double-award constraint: xp_transactions (user_id, source_type, source_id).
 *
 * Laws 5 and 6, threat model T2. A retried job, a replayed request or a
 * double-clicked submit must not be able to award the same XP twice, and the
 * guarantee must hold in the database rather than in an application-level
 * existence check, which races under concurrency.
 */

it('refuses a second xp award for the same source', function () {
    $user = SchemaRows::user();

    SchemaRows::xp($user, ['source_id' => '42', 'amount' => 100]);

    SchemaRows::assertViolates(
        SchemaRows::UNIQUE_VIOLATION,
        fn () => SchemaRows::xp($user, ['source_id' => '42', 'amount' => 100]),
        'A retried award for the same source must not create a second ledger row.',
    );

    expect(DB::table('xp_transactions')->where('user_id', $user)->sum('amount'))->toEqual(100);
});

it('scopes the award constraint to one user', function () {
    $first = SchemaRows::user();
    $second = SchemaRows::user();

    // A daily bonus is keyed by date, so the same source_id is legitimately
    // reused across users. user_id is part of the key for exactly this reason.
    SchemaRows::xp($first, ['source_type' => 'daily_bonus', 'source_id' => '2026-09-01']);
    SchemaRows::xp($second, ['source_type' => 'daily_bonus', 'source_id' => '2026-09-01']);

    expect(DB::table('xp_transactions')->count())->toBe(2);
});

it('allows the same source id under a different source type', function () {
    $user = SchemaRows::user();

    SchemaRows::xp($user, ['source_type' => 'challenge_attempt', 'source_id' => '7']);
    SchemaRows::xp($user, ['source_type' => 'achievement', 'source_id' => '7']);

    expect(DB::table('xp_transactions')->where('user_id', $user)->count())->toBe(2);
});

it('records a correction as a compensating negative row', function () {
    $user = SchemaRows::user();

    SchemaRows::xp($user, ['source_id' => '9', 'amount' => 200]);
    SchemaRows::xp($user, [
        'source_type' => 'correction',
        'source_id' => 'reversal-of-9',
        'amount' => -200,
        'description' => 'Reversed: challenge 9 had a wrong answer key',
    ]);

    // Law 6: the ledger is append-only. The total moves because a row was
    // added, never because a row was edited away.
    expect(DB::table('xp_transactions')->where('user_id', $user)->sum('amount'))->toEqual(0)
        ->and(DB::table('xp_transactions')->where('user_id', $user)->count())->toBe(2);
});
