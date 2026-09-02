<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Assert;

/**
 * Row builders and constraint assertions for the MVP schema.
 *
 * Deliberately model-free. These tests pin guarantees the DATABASE makes, not
 * guarantees the application makes, so they must keep failing loudly even if a
 * model, factory, observer or service later changes what the app writes. A
 * constraint test that goes through an Eloquent model tests the model.
 */
final class SchemaRows
{
    /** PostgreSQL SQLSTATE for a unique constraint violation. */
    public const UNIQUE_VIOLATION = '23505';

    /** PostgreSQL SQLSTATE for a CHECK constraint violation. */
    public const CHECK_VIOLATION = '23514';

    public static function user(): int
    {
        return (int) User::factory()->create()->id;
    }

    /** @param  array<string, mixed>  $overrides */
    public static function profile(int $userId, string $username, array $overrides = []): int
    {
        return (int) DB::table('profiles')->insertGetId(array_merge([
            'user_id' => $userId,
            'username' => $username,
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    public static function experience(array $overrides = []): int
    {
        return (int) DB::table('experiences')->insertGetId(array_merge([
            'slug' => 'experience-'.uniqid(),
            'name' => 'Cursed Code',
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    public static function challenge(int $experienceId, array $overrides = []): int
    {
        return (int) DB::table('challenges')->insertGetId(array_merge([
            'experience_id' => $experienceId,
            'slug' => 'challenge-'.uniqid(),
            'title' => 'What does this print?',
            'description' => 'A snippet that does not do what it looks like.',
            'objective' => 'Predict the output.',
            'difficulty' => 'medium',
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    public static function attempt(int $userId, int $challengeId, array $overrides = []): int
    {
        return (int) DB::table('challenge_attempts')->insertGetId(array_merge([
            'user_id' => $userId,
            'challenge_id' => $challengeId,
            'challenge_version' => 1,
            'status' => 'started',
            'started_at' => now(),
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    public static function xp(int $userId, array $overrides = []): int
    {
        return (int) DB::table('xp_transactions')->insertGetId(array_merge([
            'user_id' => $userId,
            'amount' => 100,
            'source_type' => 'challenge_attempt',
            'source_id' => '1',
            'description' => 'Completed a challenge',
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    public static function achievement(array $overrides = []): int
    {
        return (int) DB::table('achievements')->insertGetId(array_merge([
            'key' => 'achievement-'.uniqid(),
            'name' => 'Bug Whisperer',
            'description' => 'Found the planted defect ten times.',
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    public static function unlock(int $userId, int $achievementId, array $overrides = []): int
    {
        return (int) DB::table('achievement_user')->insertGetId(array_merge([
            'user_id' => $userId,
            'achievement_id' => $achievementId,
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    public static function report(int $challengeId, ?int $userId, array $overrides = []): int
    {
        return (int) DB::table('challenge_reports')->insertGetId(array_merge([
            'challenge_id' => $challengeId,
            'challenge_version' => 1,
            'user_id' => $userId,
            'reason' => 'wrong_answer',
            'status' => 'open',
        ], $overrides));
    }

    /**
     * Assert that the database REFUSES a write, with the expected SQLSTATE.
     *
     * The SQLSTATE is checked rather than merely catching QueryException, so a
     * test cannot pass because of an unrelated failure — a NOT NULL violation
     * from a mistyped column name would otherwise look like proof that a unique
     * index is doing its job.
     *
     * The write runs inside a nested transaction so it becomes a SAVEPOINT.
     * PostgreSQL puts a transaction into an aborted state after any error, and
     * RefreshDatabase has already opened one; without the savepoint every
     * assertion after this one would fail with 25P02 instead of running.
     */
    public static function assertViolates(string $sqlState, callable $write, string $because): void
    {
        try {
            DB::transaction(static fn () => $write());
        } catch (QueryException $e) {
            $actual = (string) ($e->errorInfo[0] ?? $e->getCode());

            Assert::assertSame(
                $sqlState,
                $actual,
                "Expected SQLSTATE {$sqlState}. {$because} Got {$actual}: {$e->getMessage()}"
            );

            return;
        }

        Assert::fail("The database accepted the write. {$because}");
    }
}
