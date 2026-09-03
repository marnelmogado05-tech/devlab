<?php

namespace App\Services\Challenge\SystemDesignLab;

use App\Models\Challenge;
use Illuminate\Support\Facades\Validator;

/**
 * Validates the shape of `challenges.configuration` and `solution` for System
 * Design Lab.
 *
 * The contract is in docs/experiences/system-design-lab.md.
 *
 * The check that justifies this class is the last one: a rubric condition
 * naming an option that does not exist can never be satisfied, so every attempt
 * fails — and nothing reports an error, because players getting it wrong is
 * exactly what this system expects to see. The success rate quietly reads 0% and
 * the difficulty calibration built on it is wrong.
 */
class SystemDesignLabConfiguration
{
    /** Below this a "design" is one decision, and one decision is a quiz. */
    private const MINIMUM_SLOTS = 2;

    /**
     * @return array<int, string> the problems found; empty means valid
     */
    public function problems(Challenge $challenge): array
    {
        $validator = Validator::make(
            ['configuration' => $challenge->configuration, 'solution' => $challenge->solution],
            [
                'configuration.scenario' => ['required', 'string', 'max:2000'],

                'configuration.requirements' => ['required', 'array', 'min:1', 'max:10'],
                'configuration.requirements.*.key' => ['required', 'string', 'max:40'],
                'configuration.requirements.*.text' => ['required', 'string', 'max:200'],

                'configuration.slots' => ['required', 'array', 'min:'.self::MINIMUM_SLOTS, 'max:8'],
                'configuration.slots.*.key' => ['required', 'string', 'max:40'],
                'configuration.slots.*.label' => ['required', 'string', 'max:80'],
                'configuration.slots.*.hint' => ['nullable', 'string', 'max:200'],
                'configuration.slots.*.options' => ['required', 'array', 'min:2', 'max:8'],
                'configuration.slots.*.options.*.key' => ['required', 'string', 'max:40'],
                'configuration.slots.*.options.*.text' => ['required', 'string', 'max:200'],

                'solution.rubric' => ['required', 'array', 'min:1'],
                'solution.rubric.*.requirement' => ['required', 'string', 'max:40'],
                'solution.rubric.*.explanation' => ['required', 'string', 'max:500'],
                'solution.rubric.*.all_of' => ['nullable', 'array'],
                'solution.rubric.*.any_of' => ['nullable', 'array'],
                'solution.rubric.*.none_of' => ['nullable', 'array'],
                'solution.reference' => ['required', 'array', 'min:1'],
                'solution.pass_mark' => ['required', 'numeric', 'min:0.5', 'max:1'],
            ],
        );

        $problems = array_merge(...array_values($validator->errors()->toArray())) ?: [];

        if ($problems !== []) {
            return $problems;
        }

        return [
            ...$this->uniquenessProblems($challenge),
            ...$this->coverageProblems($challenge),
            ...$this->conditionProblems($challenge),
            ...$this->referenceProblems($challenge),
        ];
    }

    public function isValid(Challenge $challenge): bool
    {
        return $this->problems($challenge) === [];
    }

    /**
     * The slots, keyed by slot key, each mapping to its option keys.
     *
     * The single definition of "what may be chosen", used by the validator and
     * the evaluator so the two cannot disagree about whether an answer exists.
     *
     * @return array<string, array<int, string>>
     */
    public function slotOptions(Challenge $challenge): array
    {
        $slots = [];

        foreach ($challenge->configuration['slots'] ?? [] as $slot) {
            if (! is_array($slot) || ! isset($slot['key'])) {
                continue;
            }

            $slots[(string) $slot['key']] = array_map(
                fn (array $option) => (string) ($option['key'] ?? ''),
                array_filter($slot['options'] ?? [], is_array(...)),
            );
        }

        return $slots;
    }

    /**
     * @return array<int, string>
     */
    private function uniquenessProblems(Challenge $challenge): array
    {
        $problems = [];

        $requirements = $this->requirementKeys($challenge);

        if (count($requirements) !== count(array_unique($requirements))) {
            $problems[] = 'Requirement keys must be unique.';
        }

        $slots = $this->slotOptions($challenge);

        if (count($slots) !== count($challenge->configuration['slots'])) {
            $problems[] = 'Slot keys must be unique.';
        }

        foreach ($slots as $slot => $options) {
            if (count($options) !== count(array_unique($options))) {
                $problems[] = "Option keys in slot '{$slot}' must be unique.";
            }
        }

        return $problems;
    }

    /**
     * Every requirement is scored, and nothing is scored twice.
     *
     * A requirement with no rubric entry is shown to the player as a goal and
     * then silently ignored, which makes the displayed brief a lie about what is
     * being measured.
     *
     * @return array<int, string>
     */
    private function coverageProblems(Challenge $challenge): array
    {
        $problems = [];

        $requirements = $this->requirementKeys($challenge);
        $scored = array_map(
            fn (array $entry) => (string) ($entry['requirement'] ?? ''),
            $challenge->solution['rubric'],
        );

        foreach (array_diff($requirements, $scored) as $unscored) {
            $problems[] = "Requirement '{$unscored}' is shown to the player but never scored.";
        }

        foreach (array_diff($scored, $requirements) as $unknown) {
            $problems[] = "The rubric scores '{$unknown}', which is not a declared requirement.";
        }

        if (count($scored) !== count(array_unique($scored))) {
            $problems[] = 'Each requirement must be scored by exactly one rubric entry.';
        }

        return $problems;
    }

    /**
     * Every condition names a slot that exists and an option that slot offers.
     *
     * @return array<int, string>
     */
    private function conditionProblems(Challenge $challenge): array
    {
        $problems = [];
        $slots = $this->slotOptions($challenge);

        foreach ($challenge->solution['rubric'] as $entry) {
            $requirement = (string) ($entry['requirement'] ?? '?');

            foreach (['all_of', 'any_of', 'none_of'] as $clause) {
                foreach ($entry[$clause] ?? [] as $condition) {
                    if (! is_string($condition) || substr_count($condition, '=') !== 1) {
                        $problems[] = "Condition '".(is_string($condition) ? $condition : gettype($condition))
                            ."' in '{$requirement}' is not of the form slot=option.";

                        continue;
                    }

                    [$slot, $option] = explode('=', $condition);

                    if (! array_key_exists($slot, $slots)) {
                        $problems[] = "Condition '{$condition}' in '{$requirement}' names slot '{$slot}', which does not exist.";

                        continue;
                    }

                    if (! in_array($option, $slots[$slot], true)) {
                        $problems[] = "Condition '{$condition}' in '{$requirement}' names option '{$option}', which slot '{$slot}' does not offer.";
                    }
                }
            }

            if (($entry['all_of'] ?? []) === [] && ($entry['any_of'] ?? []) === [] && ($entry['none_of'] ?? []) === []) {
                $problems[] = "Requirement '{$requirement}' has no conditions, so every design satisfies it.";
            }
        }

        return $problems;
    }

    /**
     * The author's own design must score full marks.
     *
     * The only check that proves the rubric is achievable rather than merely
     * well-formed, and it proves it the way anything is really proved: by
     * running it.
     *
     * @return array<int, string>
     */
    private function referenceProblems(Challenge $challenge): array
    {
        $problems = [];
        $slots = $this->slotOptions($challenge);

        /** @var array<string, mixed> $reference */
        $reference = $challenge->solution['reference'];

        foreach (array_keys($slots) as $slot) {
            if (! array_key_exists($slot, $reference)) {
                $problems[] = "The reference design does not choose anything for slot '{$slot}'.";
            }
        }

        foreach ($reference as $slot => $option) {
            if (! array_key_exists((string) $slot, $slots)) {
                $problems[] = "The reference design chooses for slot '{$slot}', which does not exist.";
            } elseif (! in_array((string) $option, $slots[(string) $slot], true)) {
                $problems[] = "The reference design chooses '{$option}' for slot '{$slot}', which does not offer it.";
            }
        }

        if ($problems !== []) {
            return $problems;
        }

        $unmet = (new SystemDesignLabEvaluator($this))
            ->unmetRequirements($challenge, array_map(strval(...), $reference));

        if ($unmet !== []) {
            $problems[] = 'The reference design fails its own rubric: '.implode(', ', $unmet).'.';
        }

        return $problems;
    }

    /**
     * @return array<int, string>
     */
    private function requirementKeys(Challenge $challenge): array
    {
        return array_map(
            fn (array $requirement) => (string) ($requirement['key'] ?? ''),
            $challenge->configuration['requirements'],
        );
    }
}
