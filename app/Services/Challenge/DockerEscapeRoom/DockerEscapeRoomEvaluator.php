<?php

namespace App\Services\Challenge\DockerEscapeRoom;

use App\Models\Challenge;
use App\Services\Challenge\ChallengeEvaluator;
use App\Services\Challenge\EvaluationResult;

/**
 * Grades a Docker Escape Room submission: where the fault is, and what fixes it.
 *
 * Two independent halves. Locating the fault without knowing the remedy is
 * genuine partial understanding and is recorded as such — but it is not solving
 * the problem, and §9.5's own wording is "identifies AND fixes", so completion
 * needs both.
 *
 * Nothing is executed. There is no Docker daemon within reach of this code, and
 * that is deliberate: running a container to grade an answer is Phase 3, with
 * its own ADR and security review (§25).
 */
class DockerEscapeRoomEvaluator implements ChallengeEvaluator
{
    public function __construct(private readonly DockerEscapeRoomConfiguration $configuration) {}

    public function evaluate(Challenge $challenge, array $submission): EvaluationResult
    {
        $evidence = is_string($submission['evidence'] ?? null) ? $submission['evidence'] : '';
        $line = $this->toLine($submission['line'] ?? null);
        $fix = is_string($submission['fix'] ?? null) ? $submission['fix'] : '';

        $located = $this->hasLocated($challenge, $evidence, $line);
        $fixed = $fix !== '' && $fix === (string) ($challenge->solution['fix'] ?? '');

        $accuracy = ((int) $located + (int) $fixed) / 2;

        $details = [
            'evidence' => $evidence,
            'line' => $line,
            'fix' => $fix,
            'located' => $located,
            'fixed' => $fixed,
        ];

        if ($located && $fixed) {
            return EvaluationResult::correct(
                accuracy: 1.0,
                feedback: 'Found it, and you know why.',
                details: $details,
            );
        }

        return EvaluationResult::incorrect(
            accuracy: $accuracy,
            feedback: $this->feedback($located, $fixed),
            details: $details,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function submissionRules(Challenge $challenge): array
    {
        return [
            'evidence' => ['required', 'string', 'max:40'],
            /*
             * No max here. The panel a line belongs to is chosen in the same
             * payload, so any bound stated in these rules would be a bound for
             * some other panel — the evaluator range-checks it against the
             * evidence actually named.
             */
            'line' => ['required', 'integer', 'min:1'],
            'fix' => ['required', 'string', 'max:40'],
        ];
    }

    /**
     * Both halves of the location must match: the right line of the wrong file
     * is not finding anything.
     */
    private function hasLocated(Challenge $challenge, string $evidence, ?int $line): bool
    {
        if ($evidence === '' || $line === null) {
            return false;
        }

        /*
         * An unknown panel or an out-of-range line is wrong, not an error. A
         * stale tab can post against a version of the challenge that no longer
         * exists, and a 500 on submission is a worse outcome than a wrong answer.
         */
        if ($this->configuration->panel($challenge, $evidence) === null) {
            return false;
        }

        if ($line > $this->configuration->lineCount($challenge, $evidence)) {
            return false;
        }

        return $evidence === (string) ($challenge->solution['evidence'] ?? '')
            && $line === (int) ($challenge->solution['line'] ?? 0);
    }

    /**
     * Says which half was missed, and never which answer would have satisfied it.
     */
    private function feedback(bool $located, bool $fixed): string
    {
        if ($located) {
            return 'Right place, wrong remedy.';
        }

        if ($fixed) {
            /*
             * Worth naming separately. Choosing the right fix while pointing
             * somewhere else usually means the player recognised the failure
             * mode from experience without reading the evidence — which is a
             * real thing to learn about your own debugging.
             */
            return 'That would fix it, but the fault is not where you pointed.';
        }

        return 'Neither the location nor the remedy is right.';
    }

    private function toLine(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 1 ? $value : null;
        }

        if (is_string($value) && ctype_digit($value)) {
            return max(1, (int) $value);
        }

        return null;
    }
}
