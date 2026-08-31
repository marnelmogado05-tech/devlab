<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Player-facing "something is wrong with this challenge" channel.
     *
     * Pulled forward from Phase 7 into the MVP by ADR 0003: a wrong answer key
     * is silent, corrupts every score derived from it, and is otherwise
     * undetectable. Full contract in docs/architecture/challenge-reports.md.
     */
    public function up(): void
    {
        Schema::create('challenge_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();

            /*
             * A report is about a VERSION, not a title. Fixing a key means
             * bumping the version, and the affected attempts are the ones
             * scored against the old one.
             */
            $table->unsignedInteger('challenge_version');

            // Keep the report if the reporter deletes their account.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Context when reported mid-play. Optional.
            $table->foreignId('attempt_id')->nullable()
                ->constrained('challenge_attempts')->nullOnDelete();

            /*
             * wrong_answer | unclear | broken | wrong_difficulty
             * offensive | copyright | security | other
             *
             * Validated against config('devlab.reports.reasons').
             * `wrong_answer` outranks everything else in triage.
             */
            $table->string('reason', 30);

            $table->text('details')->nullable();

            // open | resolved | dismissed
            $table->string('status', 20)->default('open');

            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();

            $table->timestampsTz();
        });

        Schema::table('challenge_reports', function (Blueprint $table) {
            // The maintainer's read path for one challenge.
            $table->index(['challenge_id', 'status']);

            // The triage queue.
            $table->index(['status', 'created_at']);

            // Wrong answer keys first.
            $table->index(['reason', 'status']);
        });

        /*
         * One open report per person, per reason, per challenge.
         *
         * Partial unique index: the constraint only applies while the report is
         * open, so the same person may report the same thing again after it has
         * been resolved and regressed. Doubles as the anti-spam guard and the
         * idempotency guard for a double-clicked submit.
         */
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX challenge_reports_one_open_per_reporter
            ON challenge_reports (challenge_id, user_id, reason)
            WHERE status = 'open' AND user_id IS NOT NULL
        SQL);

        // Length caps enforced in the database, not only in validation.
        DB::statement('ALTER TABLE challenge_reports ADD CONSTRAINT challenge_reports_details_length CHECK (char_length(details) <= 2000)');
        DB::statement('ALTER TABLE challenge_reports ADD CONSTRAINT challenge_reports_resolution_note_length CHECK (char_length(resolution_note) <= 2000)');
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_reports');
    }
};
