<?php

namespace App\Console\Commands;

use App\Services\Challenge\ContentFingerprint;
use Illuminate\Console\Command;

/**
 * Check, or rewrite, the committed record of what every challenge asks.
 *
 * The manifest is the evidence a test compares against. Its job is to make one
 * specific mistake loud: editing an answer key, a snippet or a set of cases
 * without bumping `version`, which silently makes every attempt scored against
 * the old content indistinguishable from the new.
 *
 * Read the whole argument in {@see ContentFingerprint}.
 */
class ContentFingerprintCommand extends Command
{
    protected $signature = 'devlab:content-fingerprint
                            {--write : Rewrite the manifest instead of checking it}';

    protected $description = 'Verify no challenge changed its answer without bumping its version';

    public const MANIFEST = 'database/content-manifest.json';

    public function handle(ContentFingerprint $fingerprints): int
    {
        $current = $fingerprints->forAll();

        if ($current === []) {
            $this->error('No challenges in the database. Seed ContentSeeder first.');

            return self::FAILURE;
        }

        if ($this->option('write')) {
            file_put_contents(
                base_path(self::MANIFEST),
                json_encode($current, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n",
            );

            $this->info(sprintf('Wrote %d challenges to %s.', count($current), self::MANIFEST));

            return self::SUCCESS;
        }

        $problems = self::compare($current, self::read());

        foreach ($problems as $problem) {
            $problem['fatal'] ? $this->error($problem['message']) : $this->warn($problem['message']);
        }

        if ($problems === []) {
            $this->info(sprintf('%d challenges match the manifest.', count($current)));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->comment('If the change was intentional, bump that challenge\'s `version` in its');
        $this->comment('seeder, re-seed, then run this with --write to record it.');

        return self::FAILURE;
    }

    /**
     * The committed manifest, or an empty set if it has never been written.
     *
     * @return array<string, array{version: int, fingerprint: string}>
     */
    public static function read(): array
    {
        $path = base_path(self::MANIFEST);

        if (! is_file($path)) {
            return [];
        }

        /** @var array<string, array{version: int, fingerprint: string}> $decoded */
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * Everything wrong between what is seeded and what was recorded.
     *
     * Only one case is fatal: the content decides something different and the
     * version did not move. Added and removed challenges are real changes that
     * need recording, but neither one can corrupt a past score — nothing was
     * ever scored against a challenge that did not exist.
     *
     * @param  array<string, array{version: int, fingerprint: string}>  $current
     * @param  array<string, array{version: int, fingerprint: string}>  $recorded
     * @return array<int, array{fatal: bool, message: string}>
     */
    public static function compare(array $current, array $recorded): array
    {
        $problems = [];

        foreach ($current as $slug => $now) {
            if (! isset($recorded[$slug])) {
                $problems[] = [
                    'fatal' => false,
                    'message' => "New challenge `{$slug}` is not in the manifest yet.",
                ];

                continue;
            }

            $before = $recorded[$slug];

            if ($now['fingerprint'] === $before['fingerprint']) {
                continue;
            }

            if ($now['version'] === $before['version']) {
                $problems[] = [
                    'fatal' => true,
                    'message' => "`{$slug}` changed what it asks or accepts, but `version` is still "
                        ."{$now['version']}. Attempts scored against the old content would become "
                        .'indistinguishable from the new (§71).',
                ];

                continue;
            }

            $problems[] = [
                'fatal' => false,
                'message' => "`{$slug}` changed and bumped to version {$now['version']}. Record it with --write.",
            ];
        }

        foreach (array_diff_key($recorded, $current) as $slug => $_) {
            $problems[] = [
                'fatal' => false,
                'message' => "`{$slug}` is in the manifest but no longer seeded.",
            ];
        }

        return $problems;
    }
}
