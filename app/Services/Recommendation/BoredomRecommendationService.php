<?php

namespace App\Services\Recommendation;

use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\Experience;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Random\Randomizer;

/**
 * "I'm Bored": the server picks something for you (§10, §75).
 *
 * The randomness is the FEATURE, not a fallback. A recommender that always
 * returns the obvious next thing produces a personalised rut, and the reaction
 * DevLab is aiming for — "why am I still debugging a fake production server" —
 * comes from being handed something you would never have chosen.
 *
 * So the algorithm is deliberately simple (§75 says to keep it that way
 * initially): weight the pool, pick randomly from it, and some of the time throw
 * the weights away entirely.
 *
 * Every weight lives in config/devlab.php, and the Randomizer is injectable so
 * the behaviour can be tested rather than hoped at.
 */
class BoredomRecommendationService
{
    public function __construct(
        private readonly Randomizer $randomizer = new Randomizer,
    ) {}

    /**
     * Pick something to do, or null when there is nothing available.
     *
     * A guest gets a recommendation too — being handed something before signing
     * up is the entire pitch. They simply have no history to weight against.
     */
    public function recommend(?User $user = null): ?Challenge
    {
        $pool = $this->pool($user);

        if ($pool->isEmpty()) {
            return null;
        }

        /*
         * The wildcard. Ignore every preference and pick at random — this is the
         * mechanic that produces "I have never touched Docker and now I have
         * spent 45 minutes on it".
         */
        if ($this->rollsWildcard()) {
            return $pool->get($this->randomizer->getInt(0, $pool->count() - 1));
        }

        return $this->weightedPick($pool, $user);
    }

    /**
     * Everything a user could reasonably be given right now.
     *
     * @return Collection<int, Challenge>
     */
    private function pool(?User $user): Collection
    {
        $query = Challenge::query()
            ->published()
            ->whereHas('experience', function (Builder $experience): void {
                $experience->where('status', Experience::STATUS_PUBLISHED)
                    // An experience can be pulled from the pool without being
                    // unpublished — the escape hatch for one that is broken but
                    // should stay browsable.
                    ->where('available_in_bored', true);
            })
            ->with('experience');

        if ($user !== null) {
            $since = now()->subDays((int) config('devlab.bored.exclude_completed_days'));

            /*
             * Do not hand someone a challenge they just finished. Older
             * completions come back into the pool deliberately: replaying
             * something from months ago is a fine recommendation, and it stops
             * the pool from shrinking to nothing for an active user.
             */
            $query->whereNotIn('id', ChallengeAttempt::query()
                ->where('user_id', $user->id)
                ->where('status', ChallengeAttempt::STATUS_COMPLETED)
                ->where('completed_at', '>=', $since)
                ->select('challenge_id'));
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, Challenge>  $pool
     */
    private function weightedPick(Collection $pool, ?User $user): ?Challenge
    {
        $context = $this->context($user);

        $weights = $pool->map(fn (Challenge $challenge) => $this->weightFor($challenge, $context))->all();

        $total = array_sum($weights);

        if ($total <= 0.0) {
            // Every candidate scored zero or less. Falling back to a uniform
            // pick is better than returning nothing: the user asked for
            // something to do.
            return $pool->get($this->randomizer->getInt(0, $pool->count() - 1));
        }

        /*
         * Roulette-wheel selection over integers. getFloat would read more
         * naturally, but working in integers keeps the draw exactly reproducible
         * from a seed, which is what makes the weighting testable at all.
         */
        $target = $this->randomizer->getInt(0, (int) round($total * 1000));
        $running = 0.0;

        foreach ($weights as $index => $weight) {
            $running += $weight;

            if ($target <= (int) round($running * 1000)) {
                return $pool->get($index);
            }
        }

        return $pool->last();
    }

    /**
     * What we know about the user, gathered once rather than per candidate.
     *
     * @return array{played: array<int, int>, last_experience: int|null, preferred_difficulty: string|null, technologies: array<int, string>, popularity: array<int, int>, max_popularity: int}
     */
    private function context(?User $user): array
    {
        // Not `?? []` on the end of the chain: `??` would suppress the null
        // access and make the `?->` redundant, which reads as though the profile
        // were guaranteed. It is not — a user need not have one.
        $preferences = $user?->profile?->preferences;
        $preferences = is_array($preferences) ? $preferences : [];

        $played = [];
        $lastExperience = null;

        if ($user !== null) {
            $played = DB::table('challenge_attempts')
                ->join('challenges', 'challenges.id', '=', 'challenge_attempts.challenge_id')
                ->where('challenge_attempts.user_id', $user->id)
                ->distinct()
                ->pluck('challenges.experience_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $lastExperience = DB::table('challenge_attempts')
                ->join('challenges', 'challenges.id', '=', 'challenge_attempts.challenge_id')
                ->where('challenge_attempts.user_id', $user->id)
                ->orderByDesc('challenge_attempts.started_at')
                ->value('challenges.experience_id');
        }

        $popularity = DB::table('challenge_attempts')
            ->selectRaw('challenge_id, COUNT(*) AS attempts')
            ->groupBy('challenge_id')
            ->pluck('attempts', 'challenge_id')
            ->map(fn ($count) => (int) $count)
            ->all();

        return [
            'played' => $played,
            'last_experience' => $lastExperience !== null ? (int) $lastExperience : null,
            'preferred_difficulty' => is_string($preferences['difficulty'] ?? null)
                ? $preferences['difficulty']
                : null,
            'technologies' => array_values(array_filter(
                (array) ($preferences['technologies'] ?? []),
                is_string(...),
            )),
            'popularity' => $popularity,
            'max_popularity' => $popularity === [] ? 0 : max($popularity),
        ];
    }

    /**
     * @param  array{played: array<int, int>, last_experience: int|null, preferred_difficulty: string|null, technologies: array<int, string>, popularity: array<int, int>, max_popularity: int}  $context
     */
    private function weightFor(Challenge $challenge, array $context): float
    {
        $weights = config('devlab.bored.weights');

        // Everything starts on the wheel. A challenge that matches nothing must
        // still be reachable, or the recommender becomes a filter.
        $weight = 1.0;

        if (! in_array($challenge->experience_id, $context['played'], true)) {
            $weight += (float) $weights['unplayed_experience'];
        }

        if ($context['preferred_difficulty'] !== null
            && $challenge->difficulty === $context['preferred_difficulty']) {
            $weight += (float) $weights['preferred_difficulty'];
        }

        if ($context['technologies'] !== []
            && array_intersect($context['technologies'], $challenge->tags) !== []) {
            $weight += (float) $weights['preferred_technology'];
        }

        if ($context['max_popularity'] > 0) {
            $attempts = $context['popularity'][$challenge->id] ?? 0;

            $weight += (float) $weights['popularity'] * ($attempts / $context['max_popularity']);
        }

        /*
         * Push away from whatever they were just doing. This is the diversity
         * lever: without it, an active user's most-played experience also
         * carries the most popularity weight and the recommender simply hands
         * back more of the same.
         */
        if ($context['last_experience'] !== null
            && $challenge->experience_id === $context['last_experience']) {
            $weight += (float) $weights['recency_penalty'];
        }

        // A negative weight is an exclusion, not a debt against the next
        // candidate — floor it so one penalty cannot distort the whole wheel.
        return max(0.0, $weight);
    }

    private function rollsWildcard(): bool
    {
        $chance = (float) config('devlab.bored.wildcard_chance');

        if ($chance <= 0.0) {
            return false;
        }

        return $this->randomizer->getInt(1, 1000) <= (int) round($chance * 1000);
    }
}
