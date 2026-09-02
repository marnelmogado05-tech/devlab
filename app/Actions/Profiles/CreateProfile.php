<?php

namespace App\Actions\Profiles;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Give a user a public identity.
 *
 * Every user gets one automatically at registration rather than being asked to
 * choose. Two reasons: `/profile/{username}` then always resolves for every
 * user, and the leaderboard has a real name to show instead of falling back to
 * the account name. They can change it afterwards.
 *
 * Idempotent — a user who already has a profile keeps it.
 */
class CreateProfile
{
    /** The `profiles.username` column caps at 39, matching GitHub. */
    private const MAX_LENGTH = 39;

    public function handle(User $user, ?string $username = null): Profile
    {
        $existing = Profile::query()->where('user_id', $user->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->createWithUniqueUsername($user, $username ?? $this->derive($user));
    }

    /**
     * Insert, and on a collision try the next candidate.
     *
     * The uniqueness check is the DATABASE's — a case-insensitive unique index on
     * LOWER(username). Asking "is this taken?" first would race two simultaneous
     * registrations into the same handle, and the index is what makes "marnel"
     * and "Marnel" the same person rather than two.
     */
    private function createWithUniqueUsername(User $user, string $base): Profile
    {
        $base = $this->sanitise($base);

        for ($suffix = 0; $suffix < 50; $suffix++) {
            $candidate = $suffix === 0
                ? $base
                : $this->truncate($base, self::MAX_LENGTH - strlen((string) $suffix) - 1).'-'.$suffix;

            try {
                // A savepoint: a collision must not abort a transaction the
                // caller may already have open, such as registration.
                return DB::transaction(fn () => Profile::query()->create([
                    'user_id' => $user->id,
                    'username' => $candidate,
                    'display_name' => $user->name,
                    'preferences' => [],
                    'is_public' => true,
                ]));
            } catch (QueryException $e) {
                if (! $this->isDuplicate($e)) {
                    throw $e;
                }
            }
        }

        // Fifty collisions on a derived handle means something is wrong with the
        // derivation, not with this user. Fail loudly rather than looping.
        throw new \RuntimeException("Could not find a free username based on [{$base}].");
    }

    /**
     * A handle from whatever the account already knows.
     *
     * The account name first, since it is what the person chose to be called;
     * the email local part when the name yields nothing usable — a name written
     * entirely in a non-Latin script slugs to an empty string.
     */
    private function derive(User $user): string
    {
        foreach ([$user->name, Str::before($user->email, '@')] as $source) {
            $slug = Str::slug((string) $source);

            /*
             * Slug each source RAW and only substitute at the very end. Running
             * every candidate through sanitise() first would mean the name
             * always produced something usable, and the email fallback would
             * never be reached.
             */
            if ($slug !== '' && ! ctype_digit($slug)) {
                return $this->truncate($slug, self::MAX_LENGTH);
            }
        }

        return 'dev';
    }

    private function sanitise(string $value): string
    {
        $slug = Str::slug($value);

        // Never empty, never longer than the column, and never a bare number —
        // a numeric handle reads as an id and invites confusion with one.
        if ($slug === '' || ctype_digit($slug)) {
            $slug = 'dev'.($slug === '' ? '' : $slug);
        }

        return $this->truncate($slug, self::MAX_LENGTH);
    }

    private function truncate(string $value, int $length): string
    {
        return rtrim(substr($value, 0, max(1, $length)), '-');
    }

    private function isDuplicate(QueryException $e): bool
    {
        return ($e->errorInfo[0] ?? null) === '23505';
    }
}
