<?php

namespace App\Services\Challenge;

use App\Models\Challenge;

/**
 * A stable hash of everything about a challenge that decides correctness.
 *
 * Every seeder carries the same instruction in its header: "Changing an answer,
 * the options or the snippet must also bump `version`, so historical attempts
 * stay interpretable (§71)." Nothing enforced it. All 51 challenges ship
 * `version => 1`, no code path bumps it, and the only guard was a sentence
 * printed by `devlab:reports:resolve` after the fact.
 *
 * That convention is what keeps a corrected answer key survivable. An attempt
 * records the `challenge_version` it was scored against, so fixing a wrong key
 * and bumping the version leaves the bad scores identifiable forever. Fixing it
 * WITHOUT bumping makes them indistinguishable from the good ones —
 * permanently, silently, and worse the more traffic the site has had.
 *
 * WHAT IS FINGERPRINTED, and what deliberately is not.
 *
 * Only the decision-bearing fields: `configuration` (the snippet, the options,
 * the cases), `solution` (the key itself) and `type` (which evaluator reads
 * them). Title, description, objective, rules, explanation, tags, points and
 * difficulty are excluded on purpose — a typo fix in prose must not demand a
 * version bump, because a version bump is not free. It declares past attempts
 * incomparable, and a guard that cried wolf over punctuation would train people
 * to bump reflexively, which destroys the signal it exists to protect.
 *
 * The hash is canonical: keys are sorted recursively before encoding, because
 * `jsonb` gives no ordering guarantee and a re-seed must not look like an edit.
 */
class ContentFingerprint
{
    /** Bump when the fingerprinted field set changes, to invalidate the manifest. */
    public const ALGORITHM_VERSION = 1;

    /**
     * The fields that decide whether an answer is right.
     *
     * @var array<int, string>
     */
    private const DECIDING_FIELDS = ['type', 'configuration', 'solution'];

    public function for(Challenge $challenge): string
    {
        $payload = [];

        foreach (self::DECIDING_FIELDS as $field) {
            $payload[$field] = $challenge->getAttribute($field);
        }

        return hash('sha256', (string) json_encode(
            [self::ALGORITHM_VERSION, $this->canonicalise($payload)],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    /**
     * Every published challenge, as slug => [version, fingerprint].
     *
     * @return array<string, array{version: int, fingerprint: string}>
     */
    public function forAll(): array
    {
        $manifest = [];

        Challenge::query()
            ->orderBy('slug')
            ->each(function (Challenge $challenge) use (&$manifest): void {
                $manifest[$challenge->slug] = [
                    'version' => (int) $challenge->version,
                    'fingerprint' => $this->for($challenge),
                ];
            });

        return $manifest;
    }

    /**
     * Sort keys recursively so a hash depends on content and never on ordering.
     *
     * Lists keep their order — the order of test cases or multiple-choice
     * options is content, not incidental — so only string-keyed maps are sorted.
     */
    private function canonicalise(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(fn (mixed $item): mixed => $this->canonicalise($item), $value);

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
