<?php

namespace App\Services\Challenge;

/**
 * The verdict on one submission, in the shared shape every experience produces.
 *
 * Immutable, and deliberately free of any score: an evaluator says what happened,
 * the scoring service decides what it is worth. Keeping those apart means a
 * tuning change to the score formula cannot alter what "correct" means, and an
 * experience author never has to reason about points.
 */
final readonly class EvaluationResult
{
    /**
     * @param  bool  $correct  whether the attempt satisfied the challenge
     * @param  float  $accuracy  0.0–1.0, for partially credited experiences. A
     *                           binary experience passes 1.0 or 0.0.
     * @param  string|null  $feedback  shown to the player; must not leak the answer
     * @param  array<string, mixed>  $details  evaluator-specific, persisted on the
     *                                         attempt for dispute resolution and
     *                                         the content-health audit
     */
    public function __construct(
        public bool $correct,
        public float $accuracy = 0.0,
        public ?string $feedback = null,
        public array $details = [],
    ) {}

    /**
     * @param  array<string, mixed>  $details
     */
    public static function correct(float $accuracy = 1.0, ?string $feedback = null, array $details = []): self
    {
        return new self(true, $accuracy, $feedback, $details);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function incorrect(float $accuracy = 0.0, ?string $feedback = null, array $details = []): self
    {
        return new self(false, $accuracy, $feedback, $details);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'correct' => $this->correct,
            'accuracy' => $this->accuracy,
            'feedback' => $this->feedback,
            'details' => $this->details,
        ];
    }
}
