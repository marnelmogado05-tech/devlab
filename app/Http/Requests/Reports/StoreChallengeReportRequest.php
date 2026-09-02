<?php

namespace App\Http\Requests\Reports;

use App\Models\ChallengeReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a report before it reaches the action.
 *
 * The reason list and the "details required" set both come from
 * config/devlab.php, so the form, the validator and the model cannot drift apart.
 */
class StoreChallengeReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ChallengeReport::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::in(ChallengeReport::reasons())],

            /*
             * Capped at the same length the database CHECK constraint enforces,
             * so an over-long report is a field error rather than a 500.
             */
            'details' => [
                Rule::requiredIf(fn () => in_array(
                    $this->input('reason'),
                    ChallengeReport::reasonsRequiringDetails(),
                    true,
                )),
                'nullable',
                'string',
                'max:'.(int) config('devlab.reports.details_max_length'),
            ],

            // Optional context when reported mid-play. Ownership is checked in
            // the controller, not here — a form request should not authorize a
            // second object.
            'attempt_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'details.required' => 'Please say what is wrong — this reason needs an explanation.',
        ];
    }
}
