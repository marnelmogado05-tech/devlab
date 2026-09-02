<?php

namespace App\Http\Controllers;

use App\Services\Recommendation\BoredomRecommendationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * GET /bored (§75).
 *
 * The server decides. Nothing about the choice is negotiable from the client —
 * accepting a filter here would turn "I'm Bored" into a search box, which is
 * exactly the thing it exists not to be.
 *
 * A GET that redirects rather than creating anything: pressing the button does
 * not open an attempt, so a refresh, a prefetch or a crawler cannot leave a
 * trail of half-started challenges.
 */
class BoredController extends Controller
{
    public function __invoke(
        Request $request,
        BoredomRecommendationService $recommendations,
    ): RedirectResponse {
        $challenge = $recommendations->recommend($request->user());

        if ($challenge === null) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('Nothing to recommend yet — there are no published challenges.'),
            ]);

            return to_route('experiences.index');
        }

        return to_route('challenges.show', $challenge);
    }
}
