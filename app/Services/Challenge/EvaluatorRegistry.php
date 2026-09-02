<?php

namespace App\Services\Challenge;

use App\Models\Challenge;
use RuntimeException;

/**
 * Resolves the evaluator for a challenge from its experience slug.
 *
 * Empty at present, on purpose. The scoring engine is built before any
 * experience (§56 order), so the first entries arrive with Cursed Code and Bug
 * Hunter. Registering happens in a service provider, not here, so adding an
 * experience never edits this class.
 */
class EvaluatorRegistry
{
    /** @var array<string, class-string<ChallengeEvaluator>> */
    private array $evaluators = [];

    /**
     * @param  class-string<ChallengeEvaluator>  $evaluator
     */
    public function register(string $experienceSlug, string $evaluator): void
    {
        $this->evaluators[$experienceSlug] = $evaluator;
    }

    public function has(string $experienceSlug): bool
    {
        return isset($this->evaluators[$experienceSlug]);
    }

    /**
     * @throws RuntimeException when the experience has no evaluator
     */
    public function for(Challenge $challenge): ChallengeEvaluator
    {
        $slug = $challenge->experience->slug;

        if (! $this->has($slug)) {
            /*
             * A 500, deliberately. An experience published without an evaluator
             * is a deployment mistake, and failing loudly is better than quietly
             * marking every submission wrong — which would corrupt the very
             * statistics used to detect bad content.
             */
            throw new RuntimeException(
                "No evaluator is registered for the [{$slug}] experience."
            );
        }

        return app($this->evaluators[$slug]);
    }
}
