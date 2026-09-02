<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\Experience;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The front door.
 *
 * DevLab's whole pitch is "open it, press the button, get handed something", so
 * the landing page's job is to make that button obvious and get out of the way
 * (§46, §47).
 *
 * The counts are real, read at request time. A landing page that claims "hundreds
 * of challenges" over an empty database is the kind of lie that costs a
 * contributor an evening, and this project already says documentation that
 * contradicts the code is worse than none.
 */
class LandingController extends Controller
{
    public function __invoke(): Response
    {
        $experiences = Experience::query()
            ->published()
            ->inCatalogueOrder()
            ->withCount(['challenges' => fn ($query) => $query->published()])
            ->get()
            ->map(fn (Experience $experience) => [
                'slug' => $experience->slug,
                'name' => $experience->name,
                'tagline' => $experience->tagline,
                'challenges_count' => $experience->challenges_count,
            ])
            ->all();

        return Inertia::render('welcome', [
            'experiences' => $experiences,
            'challengeCount' => Challenge::query()->published()->count(),
        ]);
    }
}
