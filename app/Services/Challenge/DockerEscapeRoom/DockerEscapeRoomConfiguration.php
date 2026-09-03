<?php

namespace App\Services\Challenge\DockerEscapeRoom;

use App\Models\Challenge;
use Illuminate\Support\Facades\Validator;

/**
 * Validates the shape of `challenges.configuration` and `solution` for Docker
 * Escape Room.
 *
 * The contract is in docs/experiences/docker-escape-room.md.
 *
 * This is Bug Hunter's line-range check across several files at once, which is
 * where it stops being obvious: a solution can name a line that exists in the
 * Dockerfile and not in the compose file, and be wrong only because it names the
 * wrong panel. Nothing at runtime would report that — every attempt would simply
 * fail — so it is checked here.
 */
class DockerEscapeRoomConfiguration
{
    /** One panel is Bug Hunter. The reading-across is the experience. */
    private const MINIMUM_PANELS = 2;

    /** Two candidate fixes is a coin toss, not a diagnosis. */
    private const MINIMUM_FIXES = 3;

    /**
     * @return array<int, string> the problems found; empty means valid
     */
    public function problems(Challenge $challenge): array
    {
        $validator = Validator::make(
            ['configuration' => $challenge->configuration, 'solution' => $challenge->solution],
            [
                'configuration.symptom' => ['required', 'string', 'max:500'],

                'configuration.evidence' => ['required', 'array', 'min:'.self::MINIMUM_PANELS, 'max:6'],
                'configuration.evidence.*.key' => ['required', 'string', 'max:40'],
                'configuration.evidence.*.label' => ['required', 'string', 'max:60'],
                'configuration.evidence.*.language' => ['required', 'string', 'max:40'],
                'configuration.evidence.*.content' => ['required', 'string', 'max:6000'],
                'configuration.evidence.*.selectable' => ['nullable', 'boolean'],

                'configuration.fixes' => ['required', 'array', 'min:'.self::MINIMUM_FIXES, 'max:6'],
                'configuration.fixes.*.key' => ['required', 'string', 'max:40'],
                'configuration.fixes.*.text' => ['required', 'string', 'max:200'],

                'solution.evidence' => ['required', 'string', 'max:40'],
                'solution.line' => ['required', 'integer', 'min:1'],
                'solution.fix' => ['required', 'string', 'max:40'],
                'solution.summary' => ['nullable', 'string', 'max:500'],
            ],
        );

        $problems = array_merge(...array_values($validator->errors()->toArray())) ?: [];

        if ($problems !== []) {
            return $problems;
        }

        return [
            ...$this->uniquenessProblems($challenge),
            ...$this->faultProblems($challenge),
        ];
    }

    public function isValid(Challenge $challenge): bool
    {
        return $this->problems($challenge) === [];
    }

    /**
     * The lines of one evidence panel, or an empty array if there is no such
     * panel.
     *
     * The single definition of "a line" for this experience, shared by the
     * validator and the evaluator, and matched by the React module's own split,
     * so all three agree about what line 7 of the Dockerfile is.
     *
     * @return array<int, string>
     */
    public function lines(Challenge $challenge, string $evidence): array
    {
        $panel = $this->panel($challenge, $evidence);

        if ($panel === null) {
            return [];
        }

        // Normalise line endings first: a Dockerfile authored on Windows must
        // not number differently from the same file authored on Linux.
        return explode("\n", str_replace(["\r\n", "\r"], "\n", (string) ($panel['content'] ?? '')));
    }

    public function lineCount(Challenge $challenge, string $evidence): int
    {
        return count($this->lines($challenge, $evidence));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function panel(Challenge $challenge, string $evidence): ?array
    {
        foreach ($challenge->configuration['evidence'] ?? [] as $panel) {
            if (is_array($panel) && ($panel['key'] ?? null) === $evidence) {
                return $panel;
            }
        }

        return null;
    }

    public function isSelectable(Challenge $challenge, string $evidence): bool
    {
        $panel = $this->panel($challenge, $evidence);

        return $panel !== null && ($panel['selectable'] ?? true) === true;
    }

    /**
     * @return array<int, string>
     */
    public function fixKeys(Challenge $challenge): array
    {
        return array_map(
            fn (array $fix) => (string) ($fix['key'] ?? ''),
            array_filter($challenge->configuration['fixes'] ?? [], is_array(...)),
        );
    }

    /**
     * @return array<int, string>
     */
    private function uniquenessProblems(Challenge $challenge): array
    {
        $problems = [];

        $panels = array_map(
            fn (array $panel) => (string) ($panel['key'] ?? ''),
            $challenge->configuration['evidence'],
        );

        if (count($panels) !== count(array_unique($panels))) {
            $problems[] = 'Evidence keys must be unique.';
        }

        $fixes = $this->fixKeys($challenge);

        if (count($fixes) !== count(array_unique($fixes))) {
            $problems[] = 'Fix keys must be unique.';
        }

        return $problems;
    }

    /**
     * The recorded fault must be somewhere a player can actually point at.
     *
     * @return array<int, string>
     */
    private function faultProblems(Challenge $challenge): array
    {
        $problems = [];

        $evidence = (string) $challenge->solution['evidence'];
        $line = (int) $challenge->solution['line'];
        $fix = (string) $challenge->solution['fix'];

        if ($this->panel($challenge, $evidence) === null) {
            $problems[] = "The solution names evidence '{$evidence}', which this challenge does not include.";

            return $problems;
        }

        if (! $this->isSelectable($challenge, $evidence)) {
            /*
             * Logs and environment dumps are evidence you read, not code you
             * fix. Recording a fault on one asks the player for a line number
             * the interface deliberately does not offer.
             */
            $problems[] = "Evidence '{$evidence}' is not selectable, so no player can point at the fault on it.";
        }

        $count = $this->lineCount($challenge, $evidence);

        if ($line > $count) {
            $problems[] = "Solution line {$line} is beyond the end of '{$evidence}' ({$count} lines).";
        } elseif (trim($this->lines($challenge, $evidence)[$line - 1] ?? '') === '') {
            // A blank line cannot be the fault, and pointing at one usually
            // means the content was edited and the line number was not.
            $problems[] = "Solution line {$line} of '{$evidence}' is blank.";
        }

        if (! in_array($fix, $this->fixKeys($challenge), true)) {
            $problems[] = "The solution names fix '{$fix}', which this challenge does not offer.";
        }

        return $problems;
    }
}
