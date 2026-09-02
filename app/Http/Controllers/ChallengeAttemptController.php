<?php

namespace App\Http\Controllers;

use App\Actions\Attempts\AbandonAttempt;
use App\Actions\Attempts\StartAttempt;
use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The attempt lifecycle: open one, look at it, walk away from it.
 *
 * Submission, evaluation and scoring are the next slice. Nothing here computes
 * or accepts a score.
 */
class ChallengeAttemptController extends Controller
{
    /**
     * POST /challenges/{challenge}/attempts
     */
    public function store(Request $request, Challenge $challenge, StartAttempt $startAttempt): RedirectResponse
    {
        $challenge->load('experience');

        // You may only attempt what you may see. Checking the challenge first
        // means an unpublished slug cannot be probed by trying to start it.
        Gate::authorize('view', $challenge);
        Gate::authorize('create', ChallengeAttempt::class);

        $attempt = $startAttempt->handle($request->user(), $challenge);

        return to_route('attempts.show', $attempt);
    }

    /**
     * GET /attempts/{attempt}
     */
    public function show(ChallengeAttempt $attempt): Response
    {
        Gate::authorize('view', $attempt);

        $attempt->load('challenge.experience');

        return Inertia::render('attempts/show', [
            'attempt' => [
                'id' => $attempt->id,
                'status' => $attempt->status,
                'started_at' => $attempt->started_at->toIso8601String(),
                /*
                 * Sent so the client can render a timer that starts from the
                 * right place. It is presentation: the client's clock never
                 * reaches a score, and the server recomputes elapsed time from
                 * `started_at` when the attempt closes (law 1).
                 */
                'elapsed_seconds' => $attempt->elapsedSeconds(),
                'challenge_version' => $attempt->challenge_version,
            ],
            'challenge' => $this->toPlayable($attempt->challenge),
            'experience' => [
                'slug' => $attempt->challenge->experience->slug,
                'name' => $attempt->challenge->experience->name,
            ],
        ]);
    }

    /**
     * DELETE /attempts/{attempt}
     */
    public function destroy(ChallengeAttempt $attempt, AbandonAttempt $abandonAttempt): RedirectResponse
    {
        Gate::authorize('abandon', $attempt);

        $attempt->load('challenge');

        $abandonAttempt->handle($attempt);

        return to_route('challenges.show', $attempt->challenge);
    }

    /**
     * What an in-progress attempt is allowed to see.
     *
     * `configuration` IS here — it is the playable payload, documented per
     * experience as client-safe, and withholding it would leave nothing to play.
     * `solution` and `explanation` are not, and this is the request where that
     * matters most: an attempt is in progress, so the answer key is worth
     * precisely as much as the score it would buy (threat T3).
     *
     * @return array<string, mixed>
     */
    private function toPlayable(Challenge $challenge): array
    {
        return [
            'slug' => $challenge->slug,
            'title' => $challenge->title,
            'description' => $challenge->description,
            'objective' => $challenge->objective,
            'rules' => $challenge->rules,
            'difficulty' => $challenge->difficulty,
            'type' => $challenge->type,
            'points' => $challenge->points,
            'estimated_minutes' => $challenge->estimated_minutes,
            'tags' => $challenge->tags,
            'configuration' => $challenge->configuration,
        ];
    }
}
