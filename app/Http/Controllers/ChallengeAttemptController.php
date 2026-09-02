<?php

namespace App\Http\Controllers;

use App\Actions\Attempts\AbandonAttempt;
use App\Actions\Attempts\StartAttempt;
use App\Actions\Attempts\SubmitAttempt;
use App\Http\Requests\Attempts\SubmitAttemptRequest;
use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\XpTransaction;
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
            'result' => $attempt->isOpen() ? null : $this->toResult($attempt),
            'experience' => [
                'slug' => $attempt->challenge->experience->slug,
                'name' => $attempt->challenge->experience->name,
            ],
        ]);
    }

    /**
     * POST /attempts/{attempt}/submit
     *
     * Authorization and validation both happen in SubmitAttemptRequest — the
     * request cannot reach here without an owned, open attempt and a payload the
     * experience's own evaluator declared it can read.
     */
    public function submit(
        SubmitAttemptRequest $request,
        ChallengeAttempt $attempt,
        SubmitAttempt $submitAttempt,
    ): RedirectResponse {
        $submitAttempt->handle($attempt, $request->validatedSubmission());

        return to_route('attempts.show', $attempt);
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
     * The outcome, released only once the attempt is closed.
     *
     * `explanation` appears HERE and nowhere earlier: it is the payoff, and the
     * thing an attacker wants before solving rather than after (§72). The
     * evaluator's `details` stay server-side — they can describe how an answer
     * was checked, which is a shorter path to the answer than the explanation is.
     *
     * @return array<string, mixed>
     */
    private function toResult(ChallengeAttempt $attempt): array
    {
        $evaluation = $attempt->evaluation ?? [];

        return [
            'status' => $attempt->status,
            'correct' => (bool) ($evaluation['correct'] ?? false),
            'feedback' => $evaluation['feedback'] ?? null,
            'score' => $attempt->score,
            'max_score' => $attempt->max_score,
            'breakdown' => $evaluation['score_breakdown'] ?? null,
            'time_taken_seconds' => $attempt->time_taken_seconds,
            'explanation' => $attempt->challenge->explanation,
            /*
             * Read back from the ledger rather than recomputed for display, so
             * what the player is told they earned is what was actually written.
             * Null on a replay of a challenge already paid for — the attempt
             * counts, the XP does not.
             */
            'xp_awarded' => $this->xpAwardedFor($attempt),
        ];
    }

    /**
     * The ledger row this completion wrote, if it wrote one.
     */
    private function xpAwardedFor(ChallengeAttempt $attempt): ?int
    {
        if ($attempt->status !== ChallengeAttempt::STATUS_COMPLETED) {
            return null;
        }

        $transaction = XpTransaction::query()
            ->where('user_id', $attempt->user_id)
            ->where('source_type', XpTransaction::SOURCE_CHALLENGE_COMPLETION)
            ->where('source_id', (string) $attempt->challenge_id)
            ->first();

        // Only the attempt that actually earned it reports it, so a replay does
        // not claim to have paid again.
        return $transaction !== null
            && ($transaction->metadata['attempt_id'] ?? null) === $attempt->id
                ? $transaction->amount
                : null;
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
