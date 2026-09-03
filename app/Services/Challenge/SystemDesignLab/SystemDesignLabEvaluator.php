<?php

namespace App\Services\Challenge\SystemDesignLab;

use App\Models\Challenge;
use App\Services\Challenge\ChallengeEvaluator;
use App\Services\Challenge\EvaluationResult;

/**
 * Grades a System Design Lab submission: a set of choices, scored against a
 * declarative rubric.
 *
 * The first evaluator in DevLab that returns partial credit. An architecture
 * satisfying four requirements out of five is a real if incomplete answer, and
 * collapsing that to "wrong" throws away the only interesting thing the
 * experience produces.
 *
 * Declarative rather than a method per challenge, for the reason achievement
 * criteria are declarative: content authors add challenges, and a content change
 * that needs a code change is a content library nobody outside the team can add
 * to.
 */
class SystemDesignLabEvaluator implements ChallengeEvaluator
{
    public function __construct(private readonly SystemDesignLabConfiguration $configuration) {}

    public function evaluate(Challenge $challenge, array $submission): EvaluationResult
    {
        /** @var array<string, mixed> $raw */
        $raw = is_array($submission['choices'] ?? null) ? $submission['choices'] : [];

        $choices = $this->sanitise($challenge, $raw);

        $rubric = $challenge->solution['rubric'] ?? [];
        $unmet = $this->unmetRequirements($challenge, $choices);

        $total = count($rubric);
        $satisfied = $total - count($unmet);
        $accuracy = $total > 0 ? round($satisfied / $total, 4) : 0.0;

        $passMark = (float) ($challenge->solution['pass_mark'] ?? 1.0);

        $details = [
            'choices' => $choices,
            'satisfied' => $satisfied,
            'total' => $total,
            'unmet' => $unmet,
        ];

        if ($accuracy >= $passMark) {
            return EvaluationResult::correct(
                accuracy: $accuracy,
                feedback: $satisfied === $total
                    ? 'Every requirement met.'
                    : "Design accepted: {$satisfied} of {$total} requirements met.",
                details: $details,
            );
        }

        /*
         * Naming the unmet requirements is not leaking the answer. It says which
         * goal was missed, never which component would have met it — and the
         * attempt is closed by the time anyone reads it, so there is nothing
         * left to hint towards.
         */
        return EvaluationResult::incorrect(
            accuracy: $accuracy,
            feedback: "{$satisfied} of {$total} requirements met. Unmet: ".implode(', ', $unmet).'.',
            details: $details,
        );
    }

    /**
     * Which requirement keys this design fails to satisfy.
     *
     * Public because the configuration validator runs the author's reference
     * design through it. Checking a rubric by evaluating it is the only check
     * that proves it is achievable rather than merely well-formed.
     *
     * @param  array<string, string>  $choices
     * @return array<int, string>
     */
    public function unmetRequirements(Challenge $challenge, array $choices): array
    {
        $unmet = [];

        foreach ($challenge->solution['rubric'] ?? [] as $entry) {
            if (! $this->satisfies($entry, $choices)) {
                $unmet[] = (string) ($entry['requirement'] ?? '?');
            }
        }

        return $unmet;
    }

    /**
     * @return array<string, mixed>
     */
    public function submissionRules(Challenge $challenge): array
    {
        $slots = array_keys($this->configuration->slotOptions($challenge));

        $rules = [
            'choices' => ['required', 'array'],
        ];

        /*
         * Every slot is required. A partially filled design is not a design, and
         * accepting one would score it against requirements it never tried to
         * meet — which reads to the player as the rubric being unfair rather
         * than their submission being incomplete.
         */
        foreach ($slots as $slot) {
            $rules["choices.{$slot}"] = ['required', 'string', 'max:40'];
        }

        return $rules;
    }

    /**
     * Drop anything that is not a real option for a real slot.
     *
     * Not an error: a stale tab can post an option that a newer version of the
     * challenge no longer offers. An unrecognised choice simply satisfies
     * nothing, which is the same outcome as not choosing.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, string>
     */
    private function sanitise(Challenge $challenge, array $raw): array
    {
        $slots = $this->configuration->slotOptions($challenge);
        $choices = [];

        foreach ($raw as $slot => $option) {
            $slot = (string) $slot;

            if (! is_string($option) && ! is_int($option)) {
                continue;
            }

            if (array_key_exists($slot, $slots) && in_array((string) $option, $slots[$slot], true)) {
                $choices[$slot] = (string) $option;
            }
        }

        return $choices;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, string>  $choices
     */
    private function satisfies(array $entry, array $choices): bool
    {
        $allOf = $this->conditions($entry, 'all_of');
        $anyOf = $this->conditions($entry, 'any_of');
        $noneOf = $this->conditions($entry, 'none_of');

        foreach ($allOf as $condition) {
            if (! $this->holds($condition, $choices)) {
                return false;
            }
        }

        foreach ($noneOf as $condition) {
            if ($this->holds($condition, $choices)) {
                return false;
            }
        }

        if ($anyOf !== []) {
            foreach ($anyOf as $condition) {
                if ($this->holds($condition, $choices)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, string>  $choices
     */
    private function holds(string $condition, array $choices): bool
    {
        if (substr_count($condition, '=') !== 1) {
            return false;
        }

        [$slot, $option] = explode('=', $condition);

        return ($choices[$slot] ?? null) === $option;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<int, string>
     */
    private function conditions(array $entry, string $clause): array
    {
        return array_values(array_filter(
            is_array($entry[$clause] ?? null) ? $entry[$clause] : [],
            is_string(...),
        ));
    }
}
