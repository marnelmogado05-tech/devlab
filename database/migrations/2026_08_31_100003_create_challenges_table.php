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
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();

            $table->foreignId('experience_id')->constrained()->cascadeOnDelete();

            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description');
            $table->text('objective');
            $table->text('rules')->nullable();

            // easy | medium | hard | expert
            $table->string('difficulty', 20);

            // Experience-specific challenge type, e.g. guess_output, fix_code.
            $table->string('type', 40)->nullable();

            $table->unsignedSmallInteger('points')->default(100);
            $table->unsignedSmallInteger('estimated_minutes')->default(5);

            /*
             * SAFE TO SEND TO THE CLIENT.
             *
             * The playable payload for this experience — the snippet, the logs,
             * the repository state, the answer options. Shape is defined per
             * experience in docs/experiences/<slug>.md and enforced by that
             * experience's validator.
             */
            $table->jsonb('configuration')->default(DB::raw("'{}'::jsonb"));

            /*
             * NEVER SEND TO THE CLIENT.
             *
             * The answer key, test cases and evaluation rubric. Kept in its own
             * column rather than inside `configuration` so that leaking it takes
             * a deliberate mistake rather than an absent-minded one. The model
             * marks this hidden; the controller must still whitelist props.
             */
            $table->jsonb('solution')->default(DB::raw("'{}'::jsonb"));

            /*
             * Revealed on completion, not before. This is the payoff — and the
             * thing an attacker wants early.
             */
            $table->text('explanation')->nullable();

            /*
             * Denormalised tag array (language, technology, concept) rather than
             * a tags table. Justification: tags are only ever read as a whole
             * set, never joined or aggregated, and the recommender filters on
             * them with a GIN index. Revisit if tags need their own metadata.
             */
            $table->jsonb('tags')->default(DB::raw("'[]'::jsonb"));

            // draft | published | archived
            $table->string('status', 20)->default('draft');

            /*
             * Bumped whenever evaluation, inputs or scoring change, so historical
             * attempts stay interpretable (plan §71). Attempts snapshot this.
             */
            $table->unsignedInteger('version')->default(1);

            // Contributor attribution. Keep the challenge if the author leaves.
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
        });

        Schema::table('challenges', function (Blueprint $table) {
            // Experience challenge listing.
            $table->index(['experience_id', 'status']);

            // "I'm Bored" and catalogue filtering by difficulty.
            $table->index(['status', 'difficulty']);

            // Author's contribution history.
            $table->index('author_id');
        });

        // Tag filtering for the recommender.
        DB::statement('CREATE INDEX challenges_tags_gin ON challenges USING GIN (tags)');
    }

    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
