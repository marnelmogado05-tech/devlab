<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tests\Support\SchemaRows;

/*
 * profiles: one profile per user, and a case-insensitive unique username.
 *
 * "marnel" and "Marnel" must not be two different people — a public identity
 * that differs only by case is an impersonation vector.
 */

it('refuses two profiles for one user', function () {
    $user = SchemaRows::user();

    SchemaRows::profile($user, 'ada');

    SchemaRows::assertViolates(
        SchemaRows::UNIQUE_VIOLATION,
        fn () => SchemaRows::profile($user, 'ada-again'),
        'A user has exactly one public identity.',
    );
});

it('refuses a username that differs only by case', function () {
    SchemaRows::profile(SchemaRows::user(), 'marnel');

    SchemaRows::assertViolates(
        SchemaRows::UNIQUE_VIOLATION,
        fn () => SchemaRows::profile(SchemaRows::user(), 'Marnel'),
        'Usernames are unique case-insensitively, so a lookalike cannot be registered.',
    );

    expect(DB::table('profiles')->count())->toBe(1);
});

it('still allows genuinely different usernames', function () {
    SchemaRows::profile(SchemaRows::user(), 'marnel');
    SchemaRows::profile(SchemaRows::user(), 'marne1');

    expect(DB::table('profiles')->count())->toBe(2);
});
