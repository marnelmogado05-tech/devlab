<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Derived read model — every column here is DENORMALISED and rebuildable.
     *
     * Source of truth: `xp_transactions` (total_xp) and `challenge_attempts`
     * (everything else). Nothing may be written here that cannot be recomputed
     * from those two tables.
     *
     * Justification for the denormalisation, per plan §63: the profile page,
     * every leaderboard and the "I'm Bored" recommender all need these figures
     * on the read path. Computing them per request means summing a user's whole
     * ledger and attempt history on every page load. This table is also what
     * Redis leaderboard sorted sets are built and rebuilt from, so losing Redis
     * costs latency, not data (plan §16, §23).
     */
    public function up(): void
    {
        Schema::create('user_statistics', function (Blueprint $table) {
            // One row per user; the user id is the primary key.
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();

            // SUM(xp_transactions.amount). Never incremented blindly.
            $table->bigInteger('total_xp')->default(0);

            // DERIVED from total_xp via config('devlab.levels'). Stored only so
            // leaderboards and profile cards avoid recomputing it per row.
            $table->unsignedSmallInteger('level')->default(1);

            $table->unsignedInteger('challenges_started')->default(0);
            $table->unsignedInteger('challenges_completed')->default(0);
            $table->unsignedInteger('challenges_failed')->default(0);
            $table->unsignedInteger('challenges_abandoned')->default(0);

            $table->unsignedBigInteger('total_time_seconds')->default(0);

            $table->unsignedInteger('current_streak_days')->default(0);
            $table->unsignedInteger('longest_streak_days')->default(0);
            $table->date('last_activity_on')->nullable();

            $table->unsignedSmallInteger('experiences_played')->default(0);
            $table->unsignedInteger('achievements_unlocked')->default(0);

            // Cached "best category" and per-experience breakdown for the
            // profile. Presentation only.
            $table->string('best_category')->nullable();
            $table->jsonb('per_experience')->default(DB::raw("'{}'::jsonb"));

            /*
             * When this row was last rebuilt from source. A stale timestamp is
             * the signal that the rebuild job has stopped running.
             */
            $table->timestampTz('recalculated_at')->nullable();

            $table->timestampsTz();
        });

        // Global leaderboard ordering, and the Redis rebuild scan.
        DB::statement('CREATE INDEX user_statistics_total_xp_index ON user_statistics (total_xp DESC)');

        Schema::table('user_statistics', function (Blueprint $table) {
            // Streak maintenance sweep.
            $table->index('last_activity_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_statistics');
    }
};
