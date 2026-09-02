<?php

namespace App\Http\Controllers;

use App\Actions\Reports\ReportChallenge;
use App\Http\Requests\Reports\StoreChallengeReportRequest;
use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Filing a report. There is no read endpoint.
 *
 * Reports are never publicly visible and never shown on a challenge page: a
 * visible report count is a spoiler — "this one is broken" changes how you play
 * it — and a harassment vector against the author. Maintainers read the table
 * with `devlab:reports` until the Phase 7 moderation UI (§69).
 */
class ChallengeReportController extends Controller
{
    /**
     * POST /challenges/{challenge}/reports
     */
    public function store(
        StoreChallengeReportRequest $request,
        Challenge $challenge,
        ReportChallenge $reportChallenge,
    ): RedirectResponse {
        $challenge->load('experience');

        // You may only report what you may see, or an unpublished slug could be
        // probed by reporting it.
        $this->authorize('view', $challenge);

        $reportChallenge->handle(
            reporter: $request->user(),
            challenge: $challenge,
            reason: $request->string('reason')->value(),
            details: $request->input('details'),
            attempt: $this->contextAttempt($request, $challenge),
        );

        /*
         * The same response whether this created a report or found an existing
         * one. A reporter learns nothing from the difference, and there is
         * nothing useful they could do with it.
         */
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Thanks — that has been passed on.'),
        ]);

        return back();
    }

    /**
     * The attempt this was reported from, when it is genuinely theirs.
     *
     * A supplied attempt id is user input: it is accepted only if it belongs to
     * the reporter AND to this challenge. Anything else is dropped rather than
     * refused, because the report itself is still worth keeping.
     */
    private function contextAttempt(StoreChallengeReportRequest $request, Challenge $challenge): ?ChallengeAttempt
    {
        $attemptId = $request->integer('attempt_id');

        if ($attemptId <= 0) {
            return null;
        }

        return ChallengeAttempt::query()
            ->whereKey($attemptId)
            ->where('user_id', $request->user()->id)
            ->where('challenge_id', $challenge->id)
            ->first();
    }
}
