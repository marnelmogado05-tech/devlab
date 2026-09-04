<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One trip through the sandbox, recorded (ADR 0008).
 *
 * A run is the artefact an attempt is graded against. It exists as a row rather
 * than as a transient job result because the verdict is computed from it later,
 * by a different process, inside a transaction that must not itself execute
 * anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_runs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('challenge_attempt_id')->constrained()->cascadeOnDelete();

            /*
             * Denormalised from the attempt on purpose. The queue reads this to
             * charge a quota and to attribute cost, and a job that has to join
             * two tables to find out whose run it is holds a lock longer and
             * fails differently when the attempt is gone.
             */
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Which sandbox image, e.g. 'php-8.4'. Refused by name if unknown.
            $table->string('runtime', 40);

            /*
             * The player's code. Untrusted text: it is stored, shown back to its
             * own author, and handed to the orchestrator. It is never rendered
             * as markup and never reaches an interpreter in this process (law 2).
             */
            $table->text('source');

            // queued | running | finished | unavailable
            $table->string('status', 20)->default('queued');

            /*
             * Why the platform declined, when it did — 'quota' or 'unavailable'.
             * Kept apart from the outcome fields because a run the platform
             * refused is not a run that produced a result (S7), and a column
             * that cannot tell them apart makes that unauditable.
             */
            $table->string('failure_reason', 40)->nullable();

            $table->smallInteger('exit_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('killed_by', 20)->nullable();
            $table->boolean('truncated')->default(false);

            /*
             * What the sandbox observed, per case: the value the player's code
             * returned, and whatever it printed while returning it. Values, not
             * verdicts — nothing in here says whether a case passed, because
             * that comparison happens in the evaluator against a key the sandbox
             * never receives (ADR 0008).
             */
            $table->jsonb('observed')->nullable();

            // Anything the harness itself said. Capped upstream by the output
            // sanitiser; the column is text because 64KB is the configured cap
            // and a cap is not a schema constraint.
            $table->text('stderr')->nullable();

            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();

            $table->timestampsTz();
        });

        Schema::table('execution_runs', function (Blueprint $table) {
            // The attempt's own history — the hot path, read on every poll.
            $table->index(['challenge_attempt_id', 'id']);

            // Cost attribution and abuse review, both per user over time.
            $table->index(['user_id', 'created_at']);

            // Finding runs left behind by a worker that died holding one.
            $table->index(['status', 'created_at']);
        });

        /*
         * A run may be graded once and only in one attempt. Nothing enforces
         * "one queued run per attempt" — a player is allowed to queue several —
         * but a run belongs to exactly one attempt by construction, which is
         * what stops one attempt being graded against another's work.
         */
        DB::statement(<<<'SQL'
            ALTER TABLE execution_runs
            ADD CONSTRAINT execution_runs_status_check
            CHECK (status IN ('queued', 'running', 'finished', 'unavailable'))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_runs');
    }
};
