<?php

namespace App\Services\Challenge\BugHunter;

use App\Models\Challenge;
use Illuminate\Support\Facades\Validator;

/**
 * Validates the shape of `challenges.configuration` and `solution` for Bug Hunter.
 *
 * The contract is in docs/experiences/bug-hunter.md. The check that justifies
 * this class is the line-range one: a solution pointing at a line the snippet
 * does not have makes the challenge unsolvable, so every attempt fails, and the
 * difficulty calibration built on that success rate is quietly wrong with
 * nothing anywhere reporting an error.
 */
class BugHunterConfiguration
{
    public const MODE_LOCATE = 'locate';

    public const MODES = [self::MODE_LOCATE];

    /** A snippet shorter than this cannot hide anything worth finding. */
    private const MINIMUM_LINES = 3;

    /**
     * @return array<int, string> the problems found; empty means valid
     */
    public function problems(Challenge $challenge): array
    {
        $validator = Validator::make(
            ['configuration' => $challenge->configuration, 'solution' => $challenge->solution],
            [
                'configuration.language' => ['required', 'string', 'max:40'],
                'configuration.mode' => ['required', 'string', 'in:'.implode(',', self::MODES)],
                'configuration.snippet' => ['required', 'string', 'max:8000'],
                'configuration.context' => ['nullable', 'string', 'max:500'],
                'configuration.prompt' => ['nullable', 'string', 'max:200'],
                'solution.lines' => ['required', 'array', 'min:1'],
                'solution.lines.*' => ['required', 'integer', 'min:1'],
                'solution.summary' => ['nullable', 'string', 'max:500'],
            ],
        );

        $problems = array_merge(...array_values($validator->errors()->toArray())) ?: [];

        if ($problems !== []) {
            return $problems;
        }

        return $this->consistencyProblems($challenge);
    }

    public function isValid(Challenge $challenge): bool
    {
        return $this->problems($challenge) === [];
    }

    /**
     * How many lines the player will be shown.
     *
     * The single definition of "a line", used by the validator, the evaluator
     * and the React module, so the three cannot disagree about what line 7 is.
     */
    public function lineCount(Challenge $challenge): int
    {
        return count($this->lines($challenge));
    }

    /**
     * @return array<int, string>
     */
    public function lines(Challenge $challenge): array
    {
        $snippet = $challenge->configuration['snippet'] ?? '';

        // Normalise line endings first: a snippet authored on Windows would
        // otherwise number differently from the same snippet authored on Linux.
        return explode("\n", str_replace(["\r\n", "\r"], "\n", (string) $snippet));
    }

    /**
     * @return array<int, string>
     */
    private function consistencyProblems(Challenge $challenge): array
    {
        $problems = [];

        $count = $this->lineCount($challenge);

        if ($count < self::MINIMUM_LINES) {
            $problems[] = 'The snippet must be at least '.self::MINIMUM_LINES.' lines long.';
        }

        /** @var array<int, int> $lines */
        $lines = $challenge->solution['lines'];

        foreach ($lines as $line) {
            if ($line > $count) {
                $problems[] = "Solution line {$line} is beyond the end of the snippet ({$count} lines).";
            }
        }

        if (count($lines) !== count(array_unique($lines))) {
            $problems[] = 'Solution lines must be unique.';
        }

        return $problems;
    }
}
