<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tests\Support\SchemaRows;

/*
 * achievement_user (user_id, achievement_id) unique.
 *
 * Unlocking is idempotent by construction: the award path attempts the insert
 * and treats a unique violation as "they already had it". No read-then-write,
 * so a retried listener cannot award the XP bonus twice.
 */

it('refuses to unlock the same achievement twice for one user', function () {
    $user = SchemaRows::user();
    $achievement = SchemaRows::achievement();

    SchemaRows::unlock($user, $achievement);

    SchemaRows::assertViolates(
        SchemaRows::UNIQUE_VIOLATION,
        fn () => SchemaRows::unlock($user, $achievement),
        'A retried unlock must not create a second row to award a second bonus from.',
    );

    expect(DB::table('achievement_user')->where('user_id', $user)->count())->toBe(1);
});

it('lets different users unlock the same achievement', function () {
    $achievement = SchemaRows::achievement();

    SchemaRows::unlock(SchemaRows::user(), $achievement);
    SchemaRows::unlock(SchemaRows::user(), $achievement);

    expect(DB::table('achievement_user')->where('achievement_id', $achievement)->count())->toBe(2);
});

it('keeps achievement keys unique', function () {
    // The key is the stable identifier used in code and in XP transaction
    // source ids. Two achievements sharing one key would collide in the ledger.
    SchemaRows::achievement(['key' => 'bug_whisperer']);

    SchemaRows::assertViolates(
        SchemaRows::UNIQUE_VIOLATION,
        fn () => SchemaRows::achievement(['key' => 'bug_whisperer']),
        'Achievement keys address rows in the XP ledger and must stay unique.',
    );
});
