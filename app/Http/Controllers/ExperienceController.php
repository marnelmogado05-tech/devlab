<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\Experience;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The experience catalogue — the browsable surface of DevLab.
 *
 * Read-only. Starting an attempt is the next slice; nothing here creates state.
 */
class ExperienceController extends Controller
{
    /**
     * GET /experiences
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', Experience::class);

        $experiences = Experience::query()
            ->published()
            ->inCatalogueOrder()
            // The count is part of the card, so it is one aggregate here rather
            // than a query per experience while rendering.
            ->withCount(['challenges' => fn ($query) => $query->published()])
            ->get()
            ->map(fn (Experience $experience) => $this->toCard($experience))
            ->all();

        return Inertia::render('experiences/index', [
            'experiences' => $experiences,
        ]);
    }

    /**
     * GET /experiences/{experience}
     */
    public function show(Experience $experience): Response
    {
        Gate::authorize('view', $experience);

        $challenges = $experience->challenges()
            ->published()
            ->orderBy('difficulty')
            ->orderBy('title')
            ->paginate(config('devlab.catalogue.page_size'))
            ->through(fn (Challenge $challenge) => $this->toChallengeSummary($challenge));

        return Inertia::render('experiences/show', [
            'experience' => [
                ...$this->toCard($experience),
                'description' => $experience->description,
            ],
            'challenges' => $challenges,
        ]);
    }

    /**
     * The props an experience may expose. Nothing here is sensitive today, but
     * the whitelist is the habit that keeps `config` — which will hold
     * experience-level settings — from leaking when it starts holding something.
     *
     * @return array<string, mixed>
     */
    private function toCard(Experience $experience): array
    {
        return [
            'slug' => $experience->slug,
            'name' => $experience->name,
            'tagline' => $experience->tagline,
            'icon' => $experience->icon,
            'category' => $experience->category,
            'default_difficulty' => $experience->default_difficulty,
            'estimated_minutes' => $experience->estimated_minutes,
            'challenges_count' => $experience->challenges_count ?? 0,
        ];
    }

    /**
     * A challenge as it appears in a listing.
     *
     * `solution` and `explanation` are absent by construction rather than by
     * being unset later — see ChallengeController for why that distinction
     * matters (threat T3).
     *
     * @return array<string, mixed>
     */
    private function toChallengeSummary(Challenge $challenge): array
    {
        return [
            'slug' => $challenge->slug,
            'title' => $challenge->title,
            'difficulty' => $challenge->difficulty,
            'estimated_minutes' => $challenge->estimated_minutes,
            'points' => $challenge->points,
            'tags' => $challenge->tags,
        ];
    }
}
