<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Services\Recommendation\BoredomRecommendationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * GET /bored — Dev Roulette (§9.1, §75).
 *
 * The server decides; nothing about the choice is negotiable from the client.
 * Accepting a filter here would turn the one feature the product is named for
 * into a worse version of the catalogue.
 *
 * It RENDERS the assignment rather than redirecting to it. The redirect worked
 * and threw away the product's moment: DevLab is named for pressing a button and
 * being handed something, and a silent redirect makes that indistinguishable
 * from clicking a link. See docs/experiences/dev-roulette.md.
 *
 * Still a GET that creates nothing — a refresh, a prefetch or a crawler produces
 * another assignment and no state. Starting remains a POST.
 */
class BoredController extends Controller
{
    public function __invoke(
        Request $request,
        BoredomRecommendationService $recommendations,
    ): Response|RedirectResponse {
        $challenge = $recommendations->recommend($request->user());

        if ($challenge === null) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('Nothing to recommend yet — there are no published challenges.'),
            ]);

            // A dead end is not an error page. Send them somewhere they can browse.
            return to_route('experiences.index');
        }

        return Inertia::render('roulette/index', [
            'assignment' => $this->toAssignment($challenge),
            'signedIn' => $request->user() !== null,
        ]);
    }

    /**
     * What the reveal is allowed to show.
     *
     * The same whitelist discipline as every other challenge response: named in,
     * never stripped out. No `solution`, no `explanation`, and no
     * `configuration` — the payload belongs to the play page, and putting it here
     * would spoil the challenge for anyone who re-spins past it.
     *
     * @return array<string, mixed>
     */
    private function toAssignment(Challenge $challenge): array
    {
        return [
            'slug' => $challenge->slug,
            'title' => $challenge->title,
            'description' => $challenge->description,
            'difficulty' => $challenge->difficulty,
            'estimated_minutes' => $challenge->estimated_minutes,
            'points' => $challenge->points,
            'tags' => $challenge->tags,
            'experience' => [
                'slug' => $challenge->experience->slug,
                'name' => $challenge->experience->name,
                'tagline' => $challenge->experience->tagline,
                'icon' => $challenge->experience->icon,
            ],
        ];
    }
}
