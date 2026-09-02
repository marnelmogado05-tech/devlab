<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tests\Support\SchemaRows;

/*
 * challenge_reports: a partial unique index on (challenge_id, user_id, reason)
 * WHERE status = 'open' AND user_id IS NOT NULL, plus CHECK caps on the two
 * free-text columns.
 *
 * ADR 0003. Reporting is the only channel that catches a wrong answer key,
 * which is silent and corrupts every score derived from it — so the channel
 * has to survive both spam and a double-clicked submit.
 */

beforeEach(function () {
    $this->challenge = SchemaRows::challenge(SchemaRows::experience());
    $this->reporter = SchemaRows::user();
});

it('refuses a duplicate open report from the same reporter', function () {
    SchemaRows::report($this->challenge, $this->reporter);

    SchemaRows::assertViolates(
        SchemaRows::UNIQUE_VIOLATION,
        fn () => SchemaRows::report($this->challenge, $this->reporter),
        'One open report per person, per reason, per challenge.',
    );

    expect(DB::table('challenge_reports')->count())->toBe(1);
});

it('allows the same reporter to raise a different reason', function () {
    SchemaRows::report($this->challenge, $this->reporter, ['reason' => 'wrong_answer']);
    SchemaRows::report($this->challenge, $this->reporter, ['reason' => 'unclear']);

    expect(DB::table('challenge_reports')->count())->toBe(2);
});

it('allows a report to be raised again after the first was resolved', function () {
    $first = SchemaRows::report($this->challenge, $this->reporter);

    DB::table('challenge_reports')->where('id', $first)->update([
        'status' => 'resolved',
        'resolved_at' => now(),
    ]);

    // A regression must be reportable. The constraint covers open reports only.
    SchemaRows::report($this->challenge, $this->reporter);

    expect(DB::table('challenge_reports')->where('status', 'open')->count())->toBe(1)
        ->and(DB::table('challenge_reports')->count())->toBe(2);
});

it('does not constrain anonymous reports', function () {
    // user_id is nullable and dropped when a reporter deletes their account.
    // NULLs must not collide with each other, or one deleted account would
    // block every future anonymous report on that challenge.
    SchemaRows::report($this->challenge, null);
    SchemaRows::report($this->challenge, null);

    expect(DB::table('challenge_reports')->count())->toBe(2);
});

it('caps report details in the database, not only in validation', function () {
    SchemaRows::assertViolates(
        SchemaRows::CHECK_VIOLATION,
        fn () => SchemaRows::report($this->challenge, $this->reporter, [
            'details' => str_repeat('a', 2001),
        ]),
        'details is capped at 2000 characters by a CHECK constraint.',
    );

    SchemaRows::report($this->challenge, $this->reporter, ['details' => str_repeat('a', 2000)]);

    expect(DB::table('challenge_reports')->count())->toBe(1);
});

it('caps the resolution note in the database', function () {
    $report = SchemaRows::report($this->challenge, $this->reporter);

    SchemaRows::assertViolates(
        SchemaRows::CHECK_VIOLATION,
        fn () => DB::table('challenge_reports')->where('id', $report)->update([
            'resolution_note' => str_repeat('a', 2001),
        ]),
        'resolution_note is capped at 2000 characters by a CHECK constraint.',
    );
});
