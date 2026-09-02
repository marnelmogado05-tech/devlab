<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The achievement catalogue (§74).
 *
 * Public, like the rest of the catalogue: seeing what there is to earn is part
 * of the pitch. Which ones YOU hold obviously requires being signed in.
 */
class AchievementController extends Controller
{
    /**
     * GET /achievements
     */
    public function index(Request $request): Response
    {
        $held = $request->user()
            ? $request->user()->achievements()->pluck('achievements.id')->all()
            : [];

        $achievements = Achievement::query()
            ->active()
            ->inDisplayOrder()
            ->get()
            ->map(fn (Achievement $achievement) => $this->toCard(
                $achievement,
                unlocked: in_array($achievement->id, $held, true),
            ))
            ->all();

        return Inertia::render('achievements/index', [
            'achievements' => $achievements,
            'unlocked_count' => count($held),
        ]);
    }

    /**
     * A secret achievement keeps its name and description until it is earned —
     * that is the whole point of the flag, and the withholding happens HERE
     * rather than in the component, because a hidden field in a prop is not
     * hidden at all.
     *
     * `criteria` is never sent. It is not secret exactly, but it is the rule
     * engine's input and the client has no use for it.
     *
     * @return array<string, mixed>
     */
    private function toCard(Achievement $achievement, bool $unlocked): array
    {
        $concealed = $achievement->is_secret && ! $unlocked;

        return [
            'key' => $concealed ? null : $achievement->key,
            'name' => $concealed ? 'Secret achievement' : $achievement->name,
            'description' => $concealed
                ? 'Earn it to find out what it was.'
                : $achievement->description,
            'icon' => $concealed ? 'Lock' : $achievement->icon,
            'category' => $concealed ? null : $achievement->category,
            'tier' => $achievement->tier,
            'xp_bonus' => $concealed ? null : $achievement->xp_bonus,
            'is_secret' => $achievement->is_secret,
            'unlocked' => $unlocked,
        ];
    }
}
