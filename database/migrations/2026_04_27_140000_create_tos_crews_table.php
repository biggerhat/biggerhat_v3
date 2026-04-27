<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TOS Crew Builder schema.
 *
 *   tos_crews                — one row per saved crew (per user)
 *   tos_crew_units           — units in the crew, ordered by position
 *   tos_crew_unit_assets     — assets attached to a specific crew_unit slot
 *
 * Allegiance is locked at the crew level (rulebook: a Company is built
 * around a single Allegiance). The Commander is one of the crew_units flagged
 * via `is_commander` on the pivot; we don't store it on tos_crews so a unit
 * swap doesn't need a second update.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tos_crews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('allegiance_id')->constrained('tos_allegiances')->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('tos_crew_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crew_id')->constrained('tos_crews')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('tos_units')->cascadeOnDelete();
            // The Commander provides the starting Scrip budget (rulebook p. 9).
            // Exactly one row per crew may carry this flag — enforced at the
            // application layer rather than via a partial unique index since
            // SQLite doesn't support those.
            $table->boolean('is_commander')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['crew_id', 'position']);
        });

        Schema::create('tos_crew_unit_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crew_unit_id')->constrained('tos_crew_units')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('tos_assets')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['crew_unit_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_crew_unit_assets');
        Schema::dropIfExists('tos_crew_units');
        Schema::dropIfExists('tos_crews');
    }
};
