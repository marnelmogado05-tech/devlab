<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenge_attempts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();

            /*
             * The challenge version played. Without this, fixing a wrong answer
             * key leaves no way to identify which attempts were scored against
             * the broken version (plan §71).
             */
            $table->unsignedInteger('challenge_version');

            // started | completed | failed | abandoned | expired (plan §12)
            $table->string('status', 20)->default('started');

            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->unsignedInteger('time_taken_seconds')->nullable();

            /*
             * Computed SERVER-SIDE from the evaluation result and the attempt
             * record. Nothing in the request body contributes to these.
             */
            $table->unsignedSmallInteger('score')->nullable();
            $table->unsignedSmallInteger('max_score')->nullable();

            $table->unsignedSmallInteger('hints_used')->default(0);

            // The user's submitted answer, and the evaluator's verdict. Kept for
            // dispute resolution and for the content audit that detects a wrong
            // answer key from a cluster of identical "wrong" answers.
            $table->jsonb('submission')->nullable();
            $table->jsonb('evaluation')->nullable();

            $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));

            $table->timestampsTz();
        });

        Schema::table('challenge_attempts', function (Blueprint $table) {
            // A user's history, and their in-progress attempts.
            $table->index(['user_id', 'status']);

            // Per-challenge statistics: success rate, median time, abandonment.
            $table->index(['challenge_id', 'status']);

            // "Have they already done this one?" — the recommender's hot path.
            $table->index(['user_id', 'challenge_id']);

            // Expiry sweep, and recent-activity feeds.
            $table->index(['status', 'started_at']);
        });

        /*
         * One open attempt per user per challenge.
         *
         * A partial unique index, because the constraint only applies while the
         * attempt is live — a user may complete the same challenge many times.
         * This is what makes a double-clicked "Start" physically unable to
         * create two attempts, rather than relying on a check that races.
         */
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX challenge_attempts_one_open_per_challenge
            ON challenge_attempts (user_id, challenge_id)
            WHERE status = 'started'
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_attempts');
    }
};
