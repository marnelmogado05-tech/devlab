<?php

declare(strict_types=1);

use App\Models\Challenge;
use App\Services\Challenge\EvaluationResult;
use App\Services\Scoring\ScoreCalculator;

/*
 * The calculator is a pure function, so these are unit tests with no database.
 * Every expectation is derived from config rather than hard-coded, so a tuning
 * change updates the test and the code from one place — a literal here would
 * turn a deliberate rebalance into a mysterious failure.
 */

function challengeFor(string $difficulty = 'medium', int $points = 100, int $minutes = 10): Challenge
{
    return new Challenge([
        'difficulty' => $difficulty,
        'points' => $points,
        'estimated_minutes' => $minutes,
    ]);
}

it('scores nothing for a wrong answer', function () {
    $score = (new ScoreCalculator)->calculate(
        challengeFor(),
        EvaluationResult::incorrect(),
        elapsedSeconds: 10,
    );

    expect($score->total)->toBe(0)
        ->and($score->base)->toBe(0)
        // Being wrong quickly is not worth anything.
        ->and($score->speedBonus)->toBe(0);
});

it('still reports the ceiling for a wrong answer', function () {
    // So a 0 is interpretable as "0 out of what".
    $score = (new ScoreCalculator)->calculate(
        challengeFor(),
        EvaluationResult::incorrect(),
        elapsedSeconds: 10,
    );

    expect($score->maxPossible)->toBeGreaterThan(0);
});

it('applies the difficulty multiplier to the base', function () {
    $calculator = new ScoreCalculator;

    foreach (config('devlab.scoring.difficulty_multiplier') as $difficulty => $multiplier) {
        $score = $calculator->calculate(
            challengeFor($difficulty, points: 100),
            EvaluationResult::correct(accuracy: 0.0),
            elapsedSeconds: 0,
        );

        expect($score->base)->toBe((int) round(100 * $multiplier));
    }
});

it('awards the full speed bonus for finishing well inside the estimate', function () {
    $score = (new ScoreCalculator)->calculate(
        challengeFor(minutes: 10),
        EvaluationResult::correct(accuracy: 0.0),
        elapsedSeconds: 60,
    );

    expect($score->speedBonus)->toBe((int) config('devlab.scoring.bonus.speed_max'));
});

it('awards no speed bonus past the floor', function () {
    $floor = 10 * 60 * (float) config('devlab.scoring.speed_floor_ratio');

    $score = (new ScoreCalculator)->calculate(
        challengeFor(minutes: 10),
        EvaluationResult::correct(accuracy: 0.0),
        elapsedSeconds: (int) $floor + 1,
    );

    expect($score->speedBonus)->toBe(0);
});

it('tapers the speed bonus between the ceiling and the floor', function () {
    $max = (int) config('devlab.scoring.bonus.speed_max');

    $score = (new ScoreCalculator)->calculate(
        challengeFor(minutes: 10),
        EvaluationResult::correct(accuracy: 0.0),
        // 600s estimate: ceiling 300s, floor 900s. 600s sits halfway.
        elapsedSeconds: 600,
    );

    expect($score->speedBonus)->toBeGreaterThan(0)
        ->and($score->speedBonus)->toBeLessThan($max);
});

it('does not punish a player for a missing time estimate', function () {
    $score = (new ScoreCalculator)->calculate(
        challengeFor(minutes: 0),
        EvaluationResult::correct(accuracy: 0.0),
        elapsedSeconds: 99_999,
    );

    expect($score->speedBonus)->toBe((int) config('devlab.scoring.bonus.speed_max'));
});

it('scales the accuracy bonus with the evaluator verdict', function () {
    $max = (int) config('devlab.scoring.bonus.accuracy_max');

    $half = (new ScoreCalculator)->calculate(
        challengeFor(),
        EvaluationResult::correct(accuracy: 0.5),
        elapsedSeconds: 0,
    );

    expect($half->accuracyBonus)->toBe((int) round($max * 0.5));
});

it('clamps an out-of-range accuracy', function () {
    // An evaluator is code someone else writes. A bad one must not be able to
    // mint points by reporting 5.0.
    $score = (new ScoreCalculator)->calculate(
        challengeFor(),
        EvaluationResult::correct(accuracy: 5.0),
        elapsedSeconds: 0,
    );

    expect($score->accuracyBonus)->toBe((int) config('devlab.scoring.bonus.accuracy_max'));
});

it('awards the no-hint bonus only when no hint was used', function () {
    $calculator = new ScoreCalculator;
    $bonus = (int) config('devlab.scoring.bonus.no_hint');

    $clean = $calculator->calculate(challengeFor(), EvaluationResult::correct(), 0, hintsUsed: 0);
    $helped = $calculator->calculate(challengeFor(), EvaluationResult::correct(), 0, hintsUsed: 1);

    expect($clean->noHintBonus)->toBe($bonus)
        ->and($helped->noHintBonus)->toBe(0);
});

it('caps the streak bonus', function () {
    $calculator = new ScoreCalculator;
    $max = (int) config('devlab.scoring.bonus.streak_max');

    expect($calculator->calculate(challengeFor(), EvaluationResult::correct(), 0, streakDays: 1)->streakBonus)->toBe(0)
        ->and($calculator->calculate(challengeFor(), EvaluationResult::correct(), 0, streakDays: 5)->streakBonus)->toBe($max)
        ->and($calculator->calculate(challengeFor(), EvaluationResult::correct(), 0, streakDays: 400)->streakBonus)->toBe($max);
});

it('keeps speed worth less than accuracy', function () {
    // §13: a model that mostly rewards typing fast turns DevLab into a race.
    expect((int) config('devlab.scoring.bonus.speed_max'))
        ->toBeLessThan((int) config('devlab.scoring.bonus.accuracy_max'));
});

it('totals its own components', function () {
    $score = (new ScoreCalculator)->calculate(
        challengeFor('hard', points: 100, minutes: 10),
        EvaluationResult::correct(accuracy: 1.0),
        elapsedSeconds: 60,
        hintsUsed: 0,
        streakDays: 5,
    );

    expect($score->total)->toBe(
        $score->base + $score->speedBonus + $score->accuracyBonus
        + $score->streakBonus + $score->noHintBonus
    );
});

it('never exceeds its own stated ceiling', function () {
    $score = (new ScoreCalculator)->calculate(
        challengeFor('expert', points: 500, minutes: 1),
        EvaluationResult::correct(accuracy: 1.0),
        elapsedSeconds: 0,
        hintsUsed: 0,
        streakDays: 99,
    );

    expect($score->total)->toBeLessThanOrEqual($score->maxPossible);
});
