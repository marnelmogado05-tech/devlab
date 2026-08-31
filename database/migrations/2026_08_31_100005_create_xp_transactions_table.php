<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The XP ledger — append-only.
     *
     * A user's XP total is the SUM of these rows. `user_statistics.total_xp` is
     * a cached read model derived from here and rebuildable from it; this table
     * is the source of truth (plan §14).
     *
     * Nothing in the application may UPDATE or DELETE a row here. Corrections
     * are made by inserting a compensating negative transaction, so the history
     * stays auditable.
     */
    public function up(): void
    {
        Schema::create('xp_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Signed: a correction is a negative transaction, never a deletion.
            $table->integer('amount');

            /*
             * What earned it. `source_type` is a stable slug —
             * challenge_attempt, achievement, daily_bonus — and `source_id`
             * identifies the specific thing: an attempt id, an achievement key,
             * or a date for a daily bonus. String, because those are not all
             * integers.
             */
            $table->string('source_type', 40);
            $table->string('source_id', 100);

            $table->string('description');
            $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));

            // Append-only: there is no updated_at, because rows are never updated.
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::table('xp_transactions', function (Blueprint $table) {
            /*
             * THE anti-double-award constraint.
             *
             * A retried job, a replayed request or a double-clicked submit
             * cannot insert the same award twice — the database refuses it.
             * This is deliberately not an application-level existence check,
             * which races under concurrency.
             *
             * `user_id` is part of the key because some sources are only unique
             * per user (a daily bonus keyed by date).
             */
            $table->unique(['user_id', 'source_type', 'source_id'], 'xp_transactions_source_unique');

            // A user's XP history, newest first.
            $table->index(['user_id', 'created_at']);

            // Rebuilding a total, and auditing one source type.
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xp_transactions');
    }
};
