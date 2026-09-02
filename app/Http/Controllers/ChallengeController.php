<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public face of a single challenge: what it is, before you attempt it.
 *
 * This page must be safe to hand to someone who has not solved the challenge, so
 * it carries the briefing and nothing that spoils it.
 */
class ChallengeController extends Controller
{
    /**
     * GET /challenges/{challenge}
     */
    public function show(Challenge $challenge): Response
    {
        $challenge->load('experience');

        Gate::authorize('view', $challenge);

        return Inertia::render('challenges/show', [
            'challenge' => $this->toDetail($challenge),
            'experience' => [
                'slug' => $challenge->experience->slug,
                'name' => $challenge->experience->name,
            ],
        ]);
    }

    /**
     * The props a challenge may expose before it has been solved.
     *
     * Built by naming what goes IN, never by taking the model and removing what
     * must stay out. A whitelist that gains a field does so deliberately; a
     * blacklist leaks the moment someone adds a column. Absent here, and absent
     * for a reason:
     *
     *   solution     the answer key, test cases and rubric — trivially converts
     *                into rank, which is what makes it worth stealing (T3)
     *   explanation  the payoff, revealed on completion and not before
     *
     * `configuration` IS sent: it is the playable payload — the snippet, the
     * logs, the options — and is documented per experience as client-safe.
     *
     * @return array<string, mixed>
     */
    private function toDetail(Challenge $challenge): array
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
            // Attempts snapshot this, so showing it makes a report about "the
            // version I played" possible (plan §71).
            'version' => $challenge->version,
        ];
    }
}
