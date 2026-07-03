<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leader Advancement — dedicated tables (pg 38–51). QA wants these split off
 * the shared game catalog (see the `campaign_*` columns Phase 1/2 of the June
 * "Campaign Catalog Consolidation" added to triggers/actions/abilities) back
 * into campaign-specific tables. Totem, Summoning, and Crew Card advancements
 * stay on the shared catalog — only Attack Mod, Tactical Mod, Action, and
 * Ability move.
 *
 * Attack Mod / Tactical Mod share one shape (flip-gated trigger / skl-boost /
 * signature modification of an existing leader action). Action / Ability
 * share a different shape (a brand-new action/ability granted to the leader,
 * either a bespoke campaign-only entry or a lookup pointer to a real catalog
 * row that already exists elsewhere in the game).
 */
return new class extends Migration
{
    public function up(): void
    {
        $attackTacticalColumns = function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('flip_value')->nullable();
            $table->boolean('is_black_joker')->default(false);
            $table->boolean('is_red_joker')->default(false);
            $table->boolean('is_always_available')->default(false);
            // trigger | skl_boost | signature
            $table->string('modifier_type', 16);
            $table->string('name');
            $table->text('effect_text');
            // SuitEnum value — trigger rows only.
            $table->string('suit')->nullable();
            // skl_boost rows only.
            $table->unsignedTinyInteger('skl_from')->nullable();
            $table->unsignedTinyInteger('skl_to')->nullable();
            // Trigger Lookup — set when this row grants a trigger that already
            // exists on some model's card; null for bespoke campaign-only rows.
            $table->foreignId('trigger_id')->nullable()->constrained('triggers')->nullOnDelete();
            $table->timestamps();
            $table->index(['modifier_type', 'flip_value']);
        };

        Schema::create('advancement_attack_mods', $attackTacticalColumns);
        Schema::create('advancement_tactical_mods', $attackTacticalColumns);

        Schema::create('advancement_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('flip_value')->nullable();
            // The one "Any Joker" free-choice row per chart (pg 49) — choose
            // any action on a non-master/non-totem model sharing a keyword,
            // cost <= 10, same as Building a Leader.
            $table->boolean('is_joker')->default(false);
            $table->boolean('is_always_available')->default(false);
            $table->string('talent_name');
            $table->text('effect_text');
            // Action Lookup — set when this row grants an action that already
            // exists on some model's card.
            $table->foreignId('action_id')->nullable()->constrained('actions')->nullOnDelete();
            // Bespoke stat block (rg/skl/rst/tn/dmg/...) — only used when
            // action_id is null.
            $table->json('stat_block')->nullable();
            $table->timestamps();
            $table->index('flip_value');
        });

        Schema::create('advancement_abilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('flip_value')->nullable();
            $table->boolean('is_joker')->default(false);
            $table->boolean('is_always_available')->default(false);
            $table->string('talent_name');
            $table->text('effect_text');
            // Ability Lookup — set when this row grants an ability that
            // already exists on some model's card.
            $table->foreignId('ability_id')->nullable()->constrained('abilities')->nullOnDelete();
            // Bespoke ability shape — only used when ability_id is null.
            $table->string('suits')->nullable();
            $table->string('defensive_ability_type')->nullable();
            $table->timestamps();
            $table->index('flip_value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advancement_abilities');
        Schema::dropIfExists('advancement_actions');
        Schema::dropIfExists('advancement_tactical_mods');
        Schema::dropIfExists('advancement_attack_mods');
    }
};
