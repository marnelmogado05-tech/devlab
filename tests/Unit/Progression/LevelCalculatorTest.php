<?php

declare(strict_types=1);

use App\Services\Progression\LevelCalculator;

/*
 * Levels are derived, never stored as an independently mutable field (§14).
 * Expectations read the ladder from config so a retune updates one place.
 */

beforeEach(function () {
    $this->levels = new LevelCalculator;
    $this->ladder = config('devlab.levels');
});

it('starts everyone at the first level', function () {
    expect($this->levels->levelForXp(0))->toBe($this->ladder[0]['level']);
});

it('never drops below the first level, even on a negative total', function () {
    // A compensating correction can take a total below zero. That is a bug
    // upstream, not a reason to hand back a level that does not exist.
    expect($this->levels->levelForXp(-500))->toBe($this->ladder[0]['level']);
});

it('reaches each rung exactly at its requirement', function () {
    foreach ($this->ladder as $rung) {
        expect($this->levels->levelForXp($rung['xp_required']))->toBe($rung['level']);
    }
});

it('stays on a rung until the next is met', function () {
    $second = $this->ladder[1];

    expect($this->levels->levelForXp($second['xp_required'] - 1))
        ->toBe($this->ladder[0]['level']);
});

it('caps at the top of the ladder', function () {
    $top = end($this->ladder);

    expect($this->levels->levelForXp($top['xp_required'] * 100))->toBe($top['level'])
        ->and($this->levels->nextAfter($top['xp_required']))->toBeNull();
});

it('reports the next rung', function () {
    expect($this->levels->nextAfter(0)['level'])->toBe($this->ladder[1]['level']);
});

it('reports progress towards the next level', function () {
    $second = $this->ladder[1];
    $halfway = (int) ($second['xp_required'] / 2);

    expect($this->levels->progressToNext(0))->toBe(0.0)
        ->and($this->levels->progressToNext($halfway))->toBeGreaterThan(0.4)
        ->and($this->levels->progressToNext($halfway))->toBeLessThan(0.6);
});

it('reads as complete at the top rather than as an error', function () {
    $top = end($this->ladder);

    expect($this->levels->progressToNext($top['xp_required']))->toBe(1.0);
});

it('carries a title for every rung', function () {
    // The UI shows these; a missing one would render an empty badge.
    foreach ($this->ladder as $rung) {
        expect($rung['title'])->toBeString()->not->toBeEmpty();
    }
});
