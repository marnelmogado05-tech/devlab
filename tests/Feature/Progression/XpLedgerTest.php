<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\XpTransaction;
use App\Services\Progression\XpLedger;
use Illuminate\Database\QueryException;
use RuntimeException;

/*
 * Law 6: the ledger is append-only, and law 5: a reward is granted once.
 *
 * The unique index on (user_id, source_type, source_id) is what makes the second
 * grant impossible; these tests prove the service relies on it rather than on a
 * check that would race.
 */

beforeEach(function () {
    $this->ledger = app(XpLedger::class);
    $this->user = User::factory()->create();
});

it('grants xp', function () {
    $transaction = $this->ledger->grant(
        $this->user, 100, XpTransaction::SOURCE_CHALLENGE_COMPLETION, '1', 'Completed'
    );

    expect($transaction)->not->toBeNull()
        ->and($this->ledger->totalFor($this->user))->toBe(100);
});

it('refuses to grant the same award twice', function () {
    $this->ledger->grant($this->user, 100, XpTransaction::SOURCE_CHALLENGE_COMPLETION, '1', 'Completed');
    $second = $this->ledger->grant($this->user, 100, XpTransaction::SOURCE_CHALLENGE_COMPLETION, '1', 'Completed');

    // The second call reports "already granted" rather than failing: the caller
    // asked for a state, and the state already holds.
    expect($second)->toBeNull()
        ->and($this->ledger->totalFor($this->user))->toBe(100)
        ->and(XpTransaction::query()->count())->toBe(1);
});

it('scopes the award to one user', function () {
    $other = User::factory()->create();

    // A daily bonus is keyed by date, so the same source id is legitimately
    // reused across users.
    $this->ledger->grant($this->user, 25, XpTransaction::SOURCE_DAILY_BONUS, '2026-09-02', 'Daily');
    $this->ledger->grant($other, 25, XpTransaction::SOURCE_DAILY_BONUS, '2026-09-02', 'Daily');

    expect(XpTransaction::query()->count())->toBe(2);
});

it('allows the same source id under a different source type', function () {
    $this->ledger->grant($this->user, 100, XpTransaction::SOURCE_CHALLENGE_COMPLETION, '7', 'Completed');
    $this->ledger->grant($this->user, 50, XpTransaction::SOURCE_ACHIEVEMENT, '7', 'Unlocked');

    expect($this->ledger->totalFor($this->user))->toBe(150);
});

it('corrects a mistake with a compensating negative row', function () {
    $this->ledger->grant($this->user, 200, XpTransaction::SOURCE_CHALLENGE_COMPLETION, '9', 'Completed');

    $this->ledger->grant(
        $this->user, -200, XpTransaction::SOURCE_CORRECTION, 'reversal-of-9',
        'Reversed: challenge 9 had a wrong answer key'
    );

    // The total moves because a row was added, never because a row was edited.
    expect($this->ledger->totalFor($this->user))->toBe(0)
        ->and(XpTransaction::query()->count())->toBe(2);
});

it('refuses to update a ledger row', function () {
    $transaction = $this->ledger->grant(
        $this->user, 100, XpTransaction::SOURCE_CHALLENGE_COMPLETION, '1', 'Completed'
    );

    expect(fn () => $transaction->update(['amount' => 999]))
        ->toThrow(RuntimeException::class);

    expect($this->ledger->totalFor($this->user))->toBe(100);
});

it('refuses to delete a ledger row', function () {
    $transaction = $this->ledger->grant(
        $this->user, 100, XpTransaction::SOURCE_CHALLENGE_COMPLETION, '1', 'Completed'
    );

    expect(fn () => $transaction->delete())->toThrow(RuntimeException::class);

    expect($this->ledger->totalFor($this->user))->toBe(100);
});

it('rethrows a failure that is not a duplicate', function () {
    // A genuine error must not be swallowed by the duplicate branch.
    expect(fn () => $this->ledger->grant(
        $this->user, 100, XpTransaction::SOURCE_CHALLENGE_COMPLETION,
        str_repeat('x', 200), 'Too long for the column'
    ))->toThrow(QueryException::class);
});
