<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TOS Garrison Builder schema.
 *
 *   tos_garrisons             — one row per saved Garrison (per user, per format)
 *   tos_garrison_units        — Commanders + Units in the pool, ordered by position
 *   tos_garrison_assets       — Asset pool with quantity (counts against scrip cap)
 *   tos_garrison_stratagems   — Stratagem pool, each chosen at most once
 *   tos_garrison_envoys       — Envoy slot pool. Per the in-codebase decision to
 *                                fold Envoy Cards into the Allegiance Card Primary
 *                                tier (see 2026_04_29_120000_drop_tos_envoys_tables.php),
 *                                the FK points at tos_allegiance_cards. The pivot
 *                                stays named `envoys` so the Garrison rules language
 *                                survives in the schema even if Wyrd ever splits
 *                                them back out.
 *
 * Allegiance is locked at the garrison level (Garrisons in Fields-of-Glory are
 * single-Allegiance). Format selects the validation profile (commander cap,
 * scrip budget, stratagem count, envoy count) — see GarrisonFormatEnum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tos_garrisons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('allegiance_id')->constrained('tos_allegiances')->cascadeOnDelete();
            $table->string('slug')->unique();
            // Mirrors tos_companies sharing — share_code is generated on
            // create, is_public flips the public-share route on/off.
            $table->string('share_code', 24)->nullable()->unique();
            $table->boolean('is_public')->default(false);
            $table->string('name');
            // GarrisonFormatEnum value: one_commander, one_commander_plus_10,
            // two_commanders, theater_of_war, no_mans_land.
            $table->string('format', 32);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
            $table->index('format');
        });

        Schema::create('tos_garrison_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('garrison_id')->constrained('tos_garrisons')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('tos_units')->cascadeOnDelete();
            // Optional sculpt selection (mirror tos_company_units). Falls back
            // to the Unit's first sculpt at render time when null.
            $table->foreignId('sculpt_id')->nullable()
                ->constrained('tos_unit_sculpts')->nullOnDelete();
            // Multiple Commander rows are allowed up to format.max_commanders;
            // application-layer enforcement, not a partial unique index, since
            // SQLite (test env) doesn't support those.
            $table->boolean('is_commander')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['garrison_id', 'position']);
            $table->index('unit_id');
        });

        Schema::create('tos_garrison_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('garrison_id')->constrained('tos_garrisons')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('tos_assets')->cascadeOnDelete();
            // Quantity column rather than per-instance rows — Garrisons declare
            // "I have 2x Ammunition Cache in my pool" and total scrip cost
            // is quantity × asset.scrip_cost.
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['garrison_id', 'asset_id']);
        });

        Schema::create('tos_garrison_stratagems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('garrison_id')->constrained('tos_garrisons')->cascadeOnDelete();
            $table->foreignId('stratagem_id')->constrained('tos_stratagems')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['garrison_id', 'stratagem_id']);
        });

        Schema::create('tos_garrison_envoys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('garrison_id')->constrained('tos_garrisons')->cascadeOnDelete();
            // Envoy = Allegiance Card (Primary tier). See migration header.
            $table->foreignId('allegiance_card_id')->constrained('tos_allegiance_cards')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['garrison_id', 'allegiance_card_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_garrison_envoys');
        Schema::dropIfExists('tos_garrison_stratagems');
        Schema::dropIfExists('tos_garrison_assets');
        Schema::dropIfExists('tos_garrison_units');
        Schema::dropIfExists('tos_garrisons');
    }
};
