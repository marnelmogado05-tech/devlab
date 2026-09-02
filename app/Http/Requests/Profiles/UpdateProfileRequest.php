<?php

namespace App\Http\Requests\Profiles;

use App\Models\Profile;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the public profile a user controls.
 *
 * Everything here is user-supplied text that other people will read, so the
 * rules are about what may be stored, not about what looks tidy.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $profile = $this->user()->profile;

        return [
            'username' => [
                'required', 'string', 'min:2', 'max:39',
                /*
                 * Letters, numbers and single hyphens. A username appears in a
                 * URL and beside other people's names, so the character set is
                 * restricted rather than merely escaped — it closes off
                 * homoglyph impersonation instead of relying on every future
                 * view to render it safely.
                 */
                'regex:/^[a-zA-Z0-9]+(-[a-zA-Z0-9]+)*$/',
                /*
                 * Case-insensitive uniqueness, matching the database index:
                 * "marnel" and "Marnel" must not be two people.
                 *
                 * A closure rather than Rule::unique, because that rule ANDs its
                 * own `username = ?` comparison onto whatever `where` adds — so
                 * a differently-cased duplicate matched nothing and reached the
                 * database, which threw. This asks the question the index asks.
                 */
                function (string $attribute, mixed $value, Closure $fail) use ($profile): void {
                    $taken = Profile::query()
                        ->whereRaw('LOWER(username) = ?', [mb_strtolower((string) $value)])
                        ->when($profile !== null, fn ($query) => $query->whereKeyNot($profile->id))
                        ->exists();

                    if ($taken) {
                        $fail('That username is taken.');
                    }
                },
            ],
            'display_name' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'location' => ['nullable', 'string', 'max:255'],
            // A URL, not just a string: `javascript:` in an href is the reason.
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'github_handle' => ['nullable', 'string', 'max:39', 'regex:/^[a-zA-Z0-9]+(-[a-zA-Z0-9]+)*$/'],
            'is_public' => ['required', 'boolean'],

            /*
             * Recommendation inputs. Presentation and preference only — nothing
             * here may influence scoring or authorization, and the recommender
             * treats them as weights rather than filters.
             */
            'preferred_difficulty' => [
                'nullable', 'string',
                Rule::in(config('devlab.difficulty.levels')),
            ],
            // A comma-separated list, because that is what a single text input
            // can express. Split and capped in `technologies()` below.
            'technologies' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * The preferred technologies, normalised.
     *
     * Lowercased and de-duplicated so they can be compared against challenge
     * tags, and capped so a pasted essay cannot become twenty thousand
     * preferences.
     *
     * @return array<int, string>
     */
    public function technologies(): array
    {
        $raw = (string) $this->input('technologies', '');

        $technologies = collect(explode(',', $raw))
            ->map(fn (string $value) => mb_strtolower(trim($value)))
            ->filter(fn (string $value) => $value !== '' && mb_strlen($value) <= 40)
            ->unique()
            ->take(20)
            ->values();

        return $technologies->all();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'Usernames may contain letters, numbers and single hyphens.',
            'username.unique' => 'That username is taken.',
        ];
    }
}
