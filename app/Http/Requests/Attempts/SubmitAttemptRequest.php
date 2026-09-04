<?php

namespace App\Http\Requests\Attempts;

use App\Models\ChallengeAttempt;
use App\Services\Challenge\AttemptScopedRules;
use App\Services\Challenge\EvaluatorRegistry;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a submission against the rules its own experience declares.
 *
 * The rules come from the evaluator rather than from a fixed list here, because
 * a submission's shape is experience-specific — and this class must not become
 * the switch statement the evaluator interface exists to avoid.
 *
 * Everything under `submission` is user-controlled. It is validated, stored and
 * handed to the evaluator, and it never reaches a score directly.
 */
class SubmitAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('submit', $this->attempt()) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(EvaluatorRegistry $evaluators): array
    {
        $attempt = $this->attempt();
        $attempt->load('challenge.experience');

        $evaluator = $evaluators->for($attempt->challenge);

        /*
         * Most experiences answer with a value and need only the challenge to
         * describe it. An experience whose answer NAMES A ROW — Code Arena
         * submits the id of a run that already happened (ADR 0008) — needs the
         * attempt as well, or the only rule it could write would accept any
         * row belonging to anybody.
         *
         * An `instanceof` rather than a slug switch: this class must not become
         * the conditional the evaluator interface exists to avoid, and an
         * experience opts in by implementing an interface rather than by being
         * added to a list here.
         */
        $rules = $evaluator instanceof AttemptScopedRules
            ? $evaluator->attemptSubmissionRules($attempt)
            : $evaluator->submissionRules($attempt->challenge);

        return [
            'submission' => ['required', 'array'],
            ...$this->prefixed($rules),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedSubmission(): array
    {
        /** @var array<string, mixed> $submission */
        $submission = $this->validated()['submission'] ?? [];

        return $submission;
    }

    private function attempt(): ChallengeAttempt
    {
        /** @var ChallengeAttempt $attempt */
        $attempt = $this->route('attempt');

        return $attempt;
    }

    /**
     * An evaluator describes its payload as `answer`, not `submission.answer` —
     * it should not have to know how the request wraps it.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function prefixed(array $rules): array
    {
        $prefixed = [];

        foreach ($rules as $field => $rule) {
            $prefixed["submission.{$field}"] = $rule;
        }

        return $prefixed;
    }
}
