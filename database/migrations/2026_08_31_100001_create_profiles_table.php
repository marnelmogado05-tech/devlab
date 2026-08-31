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
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();

            // One profile per user. Deleting the user deletes the profile.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Public identity. Uniqueness is enforced case-insensitively below —
            // "marnel" and "Marnel" must not be two different people.
            $table->string('username', 39);

            $table->string('display_name')->nullable();
            $table->text('bio')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('location')->nullable();
            $table->string('website')->nullable();
            $table->string('github_handle', 39)->nullable();

            /*
             * Recommendation inputs for "I'm Bored": preferred difficulty,
             * technologies, time available. Presentation and preference only —
             * nothing here may influence scoring or authorization.
             */
            $table->jsonb('preferences')->default(DB::raw("'{}'::jsonb"));

            // Profiles are public by default; a private profile still ranks on
            // leaderboards but hides its activity detail.
            $table->boolean('is_public')->default(true);

            $table->timestampsTz();
        });

        // Case-insensitive unique username.
        DB::statement('CREATE UNIQUE INDEX profiles_username_lower_unique ON profiles (LOWER(username))');

        // Public profile lookup by handle.
        DB::statement('CREATE INDEX profiles_is_public_index ON profiles (is_public)');
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
