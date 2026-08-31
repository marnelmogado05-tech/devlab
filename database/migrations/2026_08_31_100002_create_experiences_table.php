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
        Schema::create('experiences', function (Blueprint $table) {
            $table->id();

            $table->string('slug')->unique();
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();

            // Lucide icon name, resolved client-side. Not a URL.
            $table->string('icon')->nullable();

            $table->string('category')->nullable();

            // draft | published | archived
            $table->string('status', 20)->default('draft');

            $table->string('default_difficulty', 20)->default('medium');
            $table->unsignedSmallInteger('estimated_minutes')->default(10);

            /*
             * Whether the "I'm Bored" recommender may select this experience.
             * An experience is not shipped until it can be reached from here
             * (plan §10) — but a broken one can be pulled without unpublishing.
             */
            $table->boolean('available_in_bored')->default(true);

            $table->unsignedSmallInteger('sort_order')->default(0);

            // Experience-level settings. Per-challenge data belongs on the
            // challenge, not here.
            $table->jsonb('config')->default(DB::raw("'{}'::jsonb"));

            $table->timestampsTz();
        });

        // Catalogue listing.
        Schema::table('experiences', function (Blueprint $table) {
            $table->index(['status', 'sort_order']);
            $table->index(['status', 'available_in_bored'], 'experiences_bored_pool_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experiences');
    }
};
