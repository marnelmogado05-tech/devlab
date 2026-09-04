<?php

namespace App\Http\Requests\Execution;

use App\Models\ChallengeAttempt;
use App\Models\ExecutionRun;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a request to run code against an attempt.
 *
 * The size cap is the first of the execution limits to apply and the only one
 * that costs nothing: a submission rejected here never reaches a queue, a row or
 * a container. Everything below this — CPU, memory, PIDs, time — is enforced
 * after something has already been spent.
 */
class StoreExecutionRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', [ExecutionRun::class, $this->attempt()]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /*
             * `present` rather than `required`: an empty editor is a legitimate
             * thing to run, and it fails honestly in the sandbox by defining no
             * function. Rejecting it here would be a different message for the
             * same mistake.
             */
            'source' => ['present', 'string', 'max:'.$this->maxBytes()],
        ];
    }

    public function source(): string
    {
        return (string) $this->validated()['source'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source.max' => 'That is more code than a challenge solution should need ('
                .number_format($this->maxBytes()).' characters at most).',
        ];
    }

    private function attempt(): ChallengeAttempt
    {
        /** @var ChallengeAttempt $attempt */
        $attempt = $this->route('attempt');

        return $attempt;
    }

    private function maxBytes(): int
    {
        return (int) config('devlab.execution.max_source_bytes', 20_000);
    }
}
