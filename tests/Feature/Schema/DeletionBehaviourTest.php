<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tests\Support\SchemaRows;

/*
 * What survives a delete, and what does not.
 *
 * Two rules pull in opposite directions and both matter:
 *   - A user's own data follows them out (cascade), so account deletion is real.
 *   - Contributed content and moderation history stay (null on delete), so one
 *     departing author cannot erase the library or the audit trail.
 */

it('takes a users own progression with them', function () {
    $user = SchemaRows::user();
    $challenge = SchemaRows::challenge(SchemaRows::experience());

    SchemaRows::profile($user, 'ada');
    SchemaRows::attempt($user, $challenge);
    SchemaRows::xp($user);
    SchemaRows::unlock($user, SchemaRows::achievement());

    DB::table('users')->where('id', $user)->delete();

    expect(DB::table('profiles')->count())->toBe(0)
        ->and(DB::table('challenge_attempts')->count())->toBe(0)
        ->and(DB::table('xp_transactions')->count())->toBe(0)
        ->and(DB::table('achievement_user')->count())->toBe(0);
});

it('keeps a challenge when its author leaves', function () {
    $author = SchemaRows::user();
    $challenge = SchemaRows::challenge(SchemaRows::experience(), ['author_id' => $author]);

    DB::table('users')->where('id', $author)->delete();

    // The library outlives its contributors; only the attribution is dropped.
    expect(DB::table('challenges')->where('id', $challenge)->exists())->toBeTrue()
        ->and(DB::table('challenges')->where('id', $challenge)->value('author_id'))->toBeNull();
});

it('keeps a report when its reporter leaves', function () {
    $challenge = SchemaRows::challenge(SchemaRows::experience());
    $reporter = SchemaRows::user();
    $attempt = SchemaRows::attempt($reporter, $challenge);

    $report = SchemaRows::report($challenge, $reporter, ['attempt_id' => $attempt]);

    DB::table('users')->where('id', $reporter)->delete();

    // A wrong answer key is still wrong after the person who noticed it
    // deletes their account. Losing the report would lose the signal.
    $row = DB::table('challenge_reports')->where('id', $report)->first();

    expect($row)->not->toBeNull()
        ->and($row->user_id)->toBeNull()
        ->and($row->attempt_id)->toBeNull()
        ->and($row->reason)->toBe('wrong_answer');
});

it('removes attempts and reports when a challenge is deleted', function () {
    $challenge = SchemaRows::challenge(SchemaRows::experience());

    SchemaRows::attempt(SchemaRows::user(), $challenge);
    SchemaRows::report($challenge, SchemaRows::user());

    DB::table('challenges')->where('id', $challenge)->delete();

    expect(DB::table('challenge_attempts')->count())->toBe(0)
        ->and(DB::table('challenge_reports')->count())->toBe(0);
});

it('removes challenges when their experience is deleted', function () {
    $experience = SchemaRows::experience();
    $challenge = SchemaRows::challenge($experience);

    SchemaRows::attempt(SchemaRows::user(), $challenge);

    DB::table('experiences')->where('id', $experience)->delete();

    expect(DB::table('challenges')->count())->toBe(0)
        ->and(DB::table('challenge_attempts')->count())->toBe(0);
});

it('keeps a resolved report when the resolving maintainer leaves', function () {
    $challenge = SchemaRows::challenge(SchemaRows::experience());
    $maintainer = SchemaRows::user();

    $report = SchemaRows::report($challenge, SchemaRows::user(), [
        'status' => 'resolved',
        'resolved_by' => $maintainer,
        'resolved_at' => now(),
        'resolution_note' => 'Answer key corrected, version bumped.',
    ]);

    DB::table('users')->where('id', $maintainer)->delete();

    $row = DB::table('challenge_reports')->where('id', $report)->first();

    expect($row)->not->toBeNull()
        ->and($row->resolved_by)->toBeNull()
        ->and($row->resolution_note)->toBe('Answer key corrected, version bumped.');
});
