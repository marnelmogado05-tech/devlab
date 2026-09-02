<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Challenge;
use App\Services\Challenge\ChallengeEvaluator;
use App\Services\Challenge\EvaluationResult;

/**
 * A stand-in evaluator for testing the submission path.
 *
 * The scoring engine is built before any experience (§56 order), so there is no
 * real evaluator to test against yet. This one grades a submission against the
 * challenge's own `solution.answer`, which is enough to exercise every branch of
 * the completion path and nothing more.
 *
 * It counts its calls, so a test can prove that a duplicate submission does not
 * re-run evaluation.
 */
class FakeEvaluator implements ChallengeEvaluator
{
    public static int $calls = 0;

    public static function reset(): void
    {
        self::$calls = 0;
    }

    public function evaluate(Challenge $challenge, array $submission): EvaluationResult
    {
        self::$calls++;

        $expected = $challenge->solution['answer'] ?? null;
        $given = $submission['answer'] ?? null;

        if ($expected !== null && $given === $expected) {
            return EvaluationResult::correct(
                accuracy: 1.0,
                feedback: 'Correct.',
                details: ['matched' => true],
            );
        }

        return EvaluationResult::incorrect(
            feedback: 'Not quite.',
            details: ['matched' => false],
        );
    }

    public function submissionRules(Challenge $challenge): array
    {
        return ['answer' => ['required', 'string', 'max:500']];
    }
}
