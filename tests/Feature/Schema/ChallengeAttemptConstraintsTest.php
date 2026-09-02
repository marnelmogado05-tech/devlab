<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tests\Support\SchemaRows;

/*
 * challenge_attempts partial unique index on (user_id, challenge_id)
 * WHERE status = 'started'.
 *
 * One open attempt per user per challenge. Partial, because the constraint only
 * applies while the attempt is live — a user may complete the same challenge
 * many times. This is what makes a double-clicked "Start" physically unable to
 * open two attempts.
 */

beforeEach(function () {
    $this->user = SchemaRows::user();
    $this->challenge = SchemaRows::challenge(SchemaRows::experience());
});

it('refuses a second open attempt on the same challenge', function () {
    SchemaRows::attempt($this->user, $this->challenge);

    SchemaRows::assertViolates(
        SchemaRows::UNIQUE_VIOLATION,
        fn () => SchemaRows::attempt($this->user, $this->challenge),
        'A double-clicked Start must not open two attempts to submit against.',
    );

    expect(DB::table('challenge_attempts')->where('status', 'started')->count())->toBe(1);
});

it('allows a new attempt once the previous one is finished', function () {
    $first = SchemaRows::attempt($this->user, $this->challenge);

    DB::table('challenge_attempts')->where('id', $first)->update([
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    SchemaRows::attempt($this->user, $this->challenge);

    expect(DB::table('challenge_attempts')->count())->toBe(2);
});

it('does not constrain finished attempts at all', function () {
    // Replaying a challenge is a feature. Only the live attempt is exclusive.
    foreach (['completed', 'completed', 'failed', 'abandoned', 'expired'] as $status) {
        SchemaRows::attempt($this->user, $this->challenge, ['status' => $status]);
    }

    expect(DB::table('challenge_attempts')->count())->toBe(5);
});

it('scopes the open attempt to one user and one challenge', function () {
    $other = SchemaRows::challenge(SchemaRows::experience());

    SchemaRows::attempt($this->user, $this->challenge);
    SchemaRows::attempt($this->user, $other);
    SchemaRows::attempt(SchemaRows::user(), $this->challenge);

    expect(DB::table('challenge_attempts')->where('status', 'started')->count())->toBe(3);
});
