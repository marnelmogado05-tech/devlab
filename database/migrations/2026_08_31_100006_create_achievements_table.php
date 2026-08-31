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
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();

            // Stable identifier used in code and in XP transaction source ids.
            // Renaming the display name must never change this.
            $table->string('key', 60)->unique();

            $table->string('name');
            $table->string('description');
            $table->string('icon')->nullable();
            $table->string('category')->nullable();

            // bronze | silver | gold — presentation weight, not difficulty.
            $table->string('tier', 20)->nullable();

            $table->unsignedSmallInteger('xp_bonus')->default(0);

            /*
             * The unlock rule, declaratively. Achievements are RULES, not `if`
             * statements in controllers — adding one must require zero changes
             * to challenge or attempt code (plan §15).
             */
            $table->jsonb('criteria')->default(DB::raw("'{}'::jsonb"));

            // Hidden until unlocked. The name and description stay server-side.
            $table->boolean('is_secret')->default(false);

            // Retire an achievement without deleting anyone's unlock history.
            $table->boolean('is_active')->default(true);

            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestampsTz();
        });

        Schema::table('achievements', function (Blueprint $table) {
            $table->index(['is_active', 'sort_order']);
            $table->index('category');
        });

        Schema::create('achievement_user', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('achievement_id')->constrained()->cascadeOnDelete();

            $table->timestampTz('unlocked_at')->useCurrent();

            // Progress toward a multi-step achievement, e.g. 47 of 100 bugs.
            $table->jsonb('progress')->default(DB::raw("'{}'::jsonb"));

            /*
             * Unlocking is idempotent by construction: attempt the insert and
             * treat a unique violation as "they already had it". No read-then-
             * write, so a retried listener cannot award the bonus twice.
             */
            $table->unique(['user_id', 'achievement_id']);

            // The profile's achievement case, newest first.
            $table->index(['user_id', 'unlocked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievement_user');
        Schema::dropIfExists('achievements');
    }
};
