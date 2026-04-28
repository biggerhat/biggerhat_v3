<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allegiance Cards now carry two tiers per the new rulebook layout:
 *
 *   Standard tier:  abilities (existing), actions (new), triggers (new),
 *                   body text (existing column reused).
 *   Primary tier:   abilities, actions, triggers, body text — each a fresh
 *                   pivot or column, since the Primary side prints its
 *                   own headline rules independent of the Standard side.
 *
 * Five new pivot tables + one new column. Existing
 * `tos_allegiance_card_ability` remains the Standard-tier ability link;
 * `tos_allegiance_card_primary_ability` mirrors its shape for the Primary
 * tier so the FormRequest validator and admin UI can treat the two
 * symmetrically.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Idempotent guards — a prior partial run on this dev DB landed
        // primary_body + three of the five pivots; the migration should
        // catch up the rest cleanly without re-creating those.
        if (! Schema::hasColumn('tos_allegiance_cards', 'primary_body')) {
            Schema::table('tos_allegiance_cards', function (Blueprint $table) {
                $table->longText('primary_body')->nullable()->after('body');
            });
        }

        if (! Schema::hasTable('tos_allegiance_card_action')) {
            Schema::create('tos_allegiance_card_action', function (Blueprint $table) {
                $table->id();
                $table->foreignId('allegiance_card_id')->constrained('tos_allegiance_cards')->cascadeOnDelete();
                $table->foreignId('action_id')->constrained('tos_actions')->cascadeOnDelete();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->unique(['allegiance_card_id', 'action_id']);
            });
        }

        if (! Schema::hasTable('tos_allegiance_card_trigger')) {
            Schema::create('tos_allegiance_card_trigger', function (Blueprint $table) {
                $table->id();
                $table->foreignId('allegiance_card_id')->constrained('tos_allegiance_cards')->cascadeOnDelete();
                $table->foreignId('trigger_id')->constrained('tos_triggers')->cascadeOnDelete();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->unique(['allegiance_card_id', 'trigger_id']);
            });
        }

        // The auto-generated index names for these `_primary_*` tables blow
        // past MySQL's 64-char identifier limit, so each unique index is
        // named explicitly with a short, deterministic key.
        if (! Schema::hasTable('tos_allegiance_card_primary_ability')) {
            Schema::create('tos_allegiance_card_primary_ability', function (Blueprint $table) {
                $table->id();
                $table->foreignId('allegiance_card_id')->constrained('tos_allegiance_cards')->cascadeOnDelete();
                $table->foreignId('ability_id')->constrained('tos_abilities')->cascadeOnDelete();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->unique(['allegiance_card_id', 'ability_id'], 'ac_primary_ability_unique');
            });
        }

        if (! Schema::hasTable('tos_allegiance_card_primary_action')) {
            Schema::create('tos_allegiance_card_primary_action', function (Blueprint $table) {
                $table->id();
                $table->foreignId('allegiance_card_id')->constrained('tos_allegiance_cards')->cascadeOnDelete();
                $table->foreignId('action_id')->constrained('tos_actions')->cascadeOnDelete();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->unique(['allegiance_card_id', 'action_id'], 'ac_primary_action_unique');
            });
        }

        if (! Schema::hasTable('tos_allegiance_card_primary_trigger')) {
            Schema::create('tos_allegiance_card_primary_trigger', function (Blueprint $table) {
                $table->id();
                $table->foreignId('allegiance_card_id')->constrained('tos_allegiance_cards')->cascadeOnDelete();
                $table->foreignId('trigger_id')->constrained('tos_triggers')->cascadeOnDelete();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->unique(['allegiance_card_id', 'trigger_id'], 'ac_primary_trigger_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_allegiance_card_primary_trigger');
        Schema::dropIfExists('tos_allegiance_card_primary_action');
        Schema::dropIfExists('tos_allegiance_card_primary_ability');
        Schema::dropIfExists('tos_allegiance_card_trigger');
        Schema::dropIfExists('tos_allegiance_card_action');

        Schema::table('tos_allegiance_cards', function (Blueprint $table) {
            $table->dropColumn('primary_body');
        });
    }
};
