<?php

namespace App\Services\Challenge\CursedCode;

use App\Models\Challenge;
use Illuminate\Support\Facades\Validator;

/**
 * Validates the shape of `challenges.configuration` for Cursed Code, and of the
 * `solution` that goes with it.
 *
 * The contract is documented in docs/experiences/cursed-code.md; this is what
 * enforces it. Content is data — seeded, and eventually community-submitted — so
 * "the author will get it right" is not a control.
 *
 * The dangerous failure is a `solution.answer` that matches no option: the
 * challenge would then be unsolvable, every attempt would fail, and the
 * difficulty calibration built on that success rate would be quietly wrong.
 * That check is the reason this class exists.
 */
class CursedCodeConfiguration
{
    public const MODE_GUESS_OUTPUT = 'guess_output';

    public const MODE_EXPLAIN_BEHAVIOUR = 'explain_behaviour';

    public const MODES = [self::MODE_GUESS_OUTPUT, self::MODE_EXPLAIN_BEHAVIOUR];

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
                'configuration.snippet' => ['required', 'string', 'max:4000'],
                'configuration.prompt' => ['nullable', 'string', 'max:200'],
                'configuration.options' => ['required', 'array', 'min:2', 'max:6'],
                'configuration.options.*.key' => ['required', 'string', 'max:10'],
                'configuration.options.*.text' => ['required', 'string', 'max:500'],
                'solution.answer' => ['required', 'string'],
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
     * The rules a field-by-field validator cannot express.
     *
     * @return array<int, string>
     */
    private function consistencyProblems(Challenge $challenge): array
    {
        $problems = [];

        $keys = array_column($challenge->configuration['options'], 'key');
        $texts = array_column($challenge->configuration['options'], 'text');

        if (count($keys) !== count(array_unique($keys))) {
            // Two options sharing a key makes the answer ambiguous.
            $problems[] = 'Option keys must be unique.';
        }

        if (count($texts) !== count(array_unique($texts))) {
            // Two options reading identically means one of them is wrong however
            // the player chooses.
            $problems[] = 'Option text must be unique.';
        }

        if (! in_array($challenge->solution['answer'], $keys, true)) {
            $problems[] = 'The solution answer must match one of the option keys.';
        }

        return $problems;
    }
}
