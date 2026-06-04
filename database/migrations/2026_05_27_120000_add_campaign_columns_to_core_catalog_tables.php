<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign Catalog Consolidation — Phase 1: Add campaign-specific columns to
 * the core catalog tables (abilities / actions / triggers / upgrades) so
 * Campaign Mode content can live alongside Standard catalog rows with
 * `game_mode_type = 'campaign'`. All new columns are nullable + default null
 * so existing Standard rows are unaffected.
 *
 * Phase 2 (next migration) copies data from the dedicated `equipment_catalog`,
 * `injury_catalog`, advancement_* tables, etc. into these consolidated rows.
 * Phase 5 drops the dedicated tables once consumers have been rewired.
 *
 * See `/home/maczirr/.claude/plans/floating-whistling-waterfall.md` for the
 * full plan.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ──────────────────────────────────────────────────────────────
        // abilities — absorbs crew_card_effects + advancement_ability
        // ──────────────────────────────────────────────────────────────
        Schema::table('abilities', function (Blueprint $table) {
            // Advancement gating (from advancement_ability)
            $table->unsignedTinyInteger('campaign_flip_value')->nullable()->after('description');
            $table->boolean('campaign_is_always_available')->default(false)->after('campaign_flip_value');
            $table->boolean('campaign_joker_freechoice')->default(false)->after('campaign_is_always_available');

            // Crew Card effect classification (from crew_card_effects)
            $table->boolean('is_crew_card_effect')->default(false)->after('campaign_joker_freechoice');
            $table->boolean('requires_token_choice')->default(false)->after('is_crew_card_effect');
            $table->boolean('requires_marker_choice')->default(false)->after('requires_token_choice');
            $table->boolean('requires_upgrade_type_choice')->default(false)->after('requires_marker_choice');

            $table->index(['game_mode_type', 'campaign_flip_value'], 'idx_ab_mode_flip');
            $table->index(['game_mode_type', 'is_crew_card_effect'], 'idx_ab_mode_crew_card');
        });

        // ──────────────────────────────────────────────────────────────
        // actions — absorbs advancement_action + summoning_advancement_catalog
        // ──────────────────────────────────────────────────────────────
        Schema::table('actions', function (Blueprint $table) {
            $table->unsignedTinyInteger('campaign_flip_value')->nullable()->after('description');
            $table->boolean('campaign_is_always_available')->default(false)->after('campaign_flip_value');
            $table->boolean('campaign_joker_freechoice')->default(false)->after('campaign_is_always_available');
            $table->boolean('campaign_grants_signature')->default(false)->after('campaign_joker_freechoice');
            // 'attack' | 'tactical' | 'summoning' — disambiguates which Aftermath
            // advancement picker the row feeds.
            $table->string('campaign_advancement_kind', 24)->nullable()->after('campaign_grants_signature');

            $table->index(['game_mode_type', 'campaign_advancement_kind'], 'idx_ac_mode_kind');
            $table->index(['game_mode_type', 'campaign_flip_value'], 'idx_ac_mode_flip');
        });

        // ──────────────────────────────────────────────────────────────
        // triggers — absorbs advancement_attack_mod + advancement_tactical_mod
        // ──────────────────────────────────────────────────────────────
        Schema::table('triggers', function (Blueprint $table) {
            $table->unsignedTinyInteger('campaign_flip_value')->nullable()->after('description');
            $table->boolean('campaign_is_always_available')->default(false)->after('campaign_flip_value');
            $table->boolean('campaign_joker_freechoice')->default(false)->after('campaign_is_always_available');
            $table->boolean('campaign_grants_signature')->default(false)->after('campaign_joker_freechoice');
            // 'trigger' (regular new trigger), 'skl' (Skl boost), 'signature',
            // 'joker' (free-choice). Matches the old `advancement_*` modifier_type column.
            $table->string('campaign_modifier_type', 16)->nullable()->after('campaign_grants_signature');
            $table->unsignedTinyInteger('campaign_skl_from')->nullable()->after('campaign_modifier_type');
            $table->unsignedTinyInteger('campaign_skl_to')->nullable()->after('campaign_skl_from');
            // 'attack' vs 'tactical' — which advancement picker.
            $table->string('campaign_advancement_kind', 24)->nullable()->after('campaign_skl_to');

            $table->index(['game_mode_type', 'campaign_advancement_kind'], 'idx_tr_mode_kind');
            $table->index(['game_mode_type', 'campaign_flip_value'], 'idx_tr_mode_flip');
        });

        // ──────────────────────────────────────────────────────────────
        // upgrades — absorbs equipment_catalog + injury_catalog
        // ──────────────────────────────────────────────────────────────
        Schema::table('upgrades', function (Blueprint $table) {
            // Discriminator — 'equipment' vs 'injury' (the two distinct upgrade
            // kinds Campaign Mode introduces). Null for Standard upgrades.
            $table->string('campaign_upgrade_kind', 24)->nullable()->after('description');

            // Equipment-specific columns (from equipment_catalog)
            $table->unsignedTinyInteger('campaign_br')->nullable()->after('campaign_upgrade_kind');
            $table->unsignedTinyInteger('campaign_cc')->nullable()->after('campaign_br');
            $table->string('campaign_pool_suit_a', 12)->nullable()->after('campaign_cc');
            $table->string('campaign_pool_suit_b', 12)->nullable()->after('campaign_pool_suit_a');
            $table->boolean('campaign_is_always_available')->default(false)->after('campaign_pool_suit_b');
            $table->boolean('campaign_ttw_only')->default(false)->after('campaign_is_always_available');
            $table->boolean('campaign_is_omens_mark')->default(false)->after('campaign_ttw_only');
            $table->boolean('campaign_is_unique')->default(false)->after('campaign_is_omens_mark');
            $table->boolean('campaign_leader_only')->default(false)->after('campaign_is_unique');
            $table->boolean('campaign_non_unique_only')->default(false)->after('campaign_leader_only');
            $table->boolean('campaign_annihilate_after_game')->default(false)->after('campaign_non_unique_only');
            $table->boolean('campaign_is_red_joker_entry')->default(false)->after('campaign_annihilate_after_game');

            // Injury-specific columns (from injury_catalog)
            $table->unsignedTinyInteger('campaign_flip_value')->nullable()->after('campaign_is_red_joker_entry');
            // 'pc' | 'te' | 'black_joker' | 'red_joker'
            $table->string('campaign_suit_pool', 16)->nullable()->after('campaign_flip_value');
            $table->boolean('campaign_is_traitor')->default(false)->after('campaign_suit_pool');
            $table->boolean('campaign_is_close_call')->default(false)->after('campaign_is_traitor');
            $table->boolean('campaign_annihilates_model')->default(false)->after('campaign_is_close_call');
            $table->boolean('campaign_reflip_if_no_triggers')->default(false)->after('campaign_annihilates_model');
            $table->boolean('campaign_reflip_if_master_or_totem')->default(false)->after('campaign_reflip_if_no_triggers');

            $table->index(['game_mode_type', 'campaign_upgrade_kind'], 'idx_up_mode_kind');
            $table->index(['game_mode_type', 'campaign_upgrade_kind', 'campaign_br'], 'idx_up_mode_kind_br');
            $table->index(['game_mode_type', 'campaign_upgrade_kind', 'campaign_flip_value'], 'idx_up_mode_kind_flip');
            $table->index(['campaign_pool_suit_a', 'campaign_pool_suit_b'], 'idx_up_camp_pools');
        });
    }

    public function down(): void
    {
        Schema::table('upgrades', function (Blueprint $table) {
            $table->dropIndex('idx_up_camp_pools');
            $table->dropIndex('idx_up_mode_kind_flip');
            $table->dropIndex('idx_up_mode_kind_br');
            $table->dropIndex('idx_up_mode_kind');
            $table->dropColumn([
                'campaign_upgrade_kind',
                'campaign_br', 'campaign_cc',
                'campaign_pool_suit_a', 'campaign_pool_suit_b',
                'campaign_is_always_available', 'campaign_ttw_only', 'campaign_is_omens_mark',
                'campaign_is_unique', 'campaign_leader_only', 'campaign_non_unique_only',
                'campaign_annihilate_after_game', 'campaign_is_red_joker_entry',
                'campaign_flip_value', 'campaign_suit_pool',
                'campaign_is_traitor', 'campaign_is_close_call', 'campaign_annihilates_model',
                'campaign_reflip_if_no_triggers', 'campaign_reflip_if_master_or_totem',
            ]);
        });

        Schema::table('triggers', function (Blueprint $table) {
            $table->dropIndex('idx_tr_mode_flip');
            $table->dropIndex('idx_tr_mode_kind');
            $table->dropColumn([
                'campaign_flip_value', 'campaign_is_always_available', 'campaign_joker_freechoice',
                'campaign_grants_signature', 'campaign_modifier_type',
                'campaign_skl_from', 'campaign_skl_to', 'campaign_advancement_kind',
            ]);
        });

        Schema::table('actions', function (Blueprint $table) {
            $table->dropIndex('idx_ac_mode_flip');
            $table->dropIndex('idx_ac_mode_kind');
            $table->dropColumn([
                'campaign_flip_value', 'campaign_is_always_available', 'campaign_joker_freechoice',
                'campaign_grants_signature', 'campaign_advancement_kind',
            ]);
        });

        Schema::table('abilities', function (Blueprint $table) {
            $table->dropIndex('idx_ab_mode_crew_card');
            $table->dropIndex('idx_ab_mode_flip');
            $table->dropColumn([
                'campaign_flip_value', 'campaign_is_always_available', 'campaign_joker_freechoice',
                'is_crew_card_effect',
                'requires_token_choice', 'requires_marker_choice', 'requires_upgrade_type_choice',
            ]);
        });
    }
};
