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
            $this->addColumnIfMissing($table, 'abilities', 'campaign_flip_value', fn () => $table->unsignedTinyInteger('campaign_flip_value')->nullable()->after('description'));
            $this->addColumnIfMissing($table, 'abilities', 'campaign_is_always_available', fn () => $table->boolean('campaign_is_always_available')->default(false)->after('campaign_flip_value'));
            $this->addColumnIfMissing($table, 'abilities', 'campaign_joker_freechoice', fn () => $table->boolean('campaign_joker_freechoice')->default(false)->after('campaign_is_always_available'));

            // Crew Card effect classification (from crew_card_effects)
            $this->addColumnIfMissing($table, 'abilities', 'is_crew_card_effect', fn () => $table->boolean('is_crew_card_effect')->default(false)->after('campaign_joker_freechoice'));
            $this->addColumnIfMissing($table, 'abilities', 'requires_token_choice', fn () => $table->boolean('requires_token_choice')->default(false)->after('is_crew_card_effect'));
            $this->addColumnIfMissing($table, 'abilities', 'requires_marker_choice', fn () => $table->boolean('requires_marker_choice')->default(false)->after('requires_token_choice'));
            $this->addColumnIfMissing($table, 'abilities', 'requires_upgrade_type_choice', fn () => $table->boolean('requires_upgrade_type_choice')->default(false)->after('requires_marker_choice'));

            $this->addIndexIfMissing($table, 'abilities', 'idx_ab_mode_flip', fn () => $table->index(['game_mode_type', 'campaign_flip_value'], 'idx_ab_mode_flip'));
            $this->addIndexIfMissing($table, 'abilities', 'idx_ab_mode_crew_card', fn () => $table->index(['game_mode_type', 'is_crew_card_effect'], 'idx_ab_mode_crew_card'));
        });

        // ──────────────────────────────────────────────────────────────
        // actions — absorbs advancement_action + summoning_advancement_catalog
        // ──────────────────────────────────────────────────────────────
        Schema::table('actions', function (Blueprint $table) {
            $this->addColumnIfMissing($table, 'actions', 'campaign_flip_value', fn () => $table->unsignedTinyInteger('campaign_flip_value')->nullable()->after('description'));
            $this->addColumnIfMissing($table, 'actions', 'campaign_is_always_available', fn () => $table->boolean('campaign_is_always_available')->default(false)->after('campaign_flip_value'));
            $this->addColumnIfMissing($table, 'actions', 'campaign_joker_freechoice', fn () => $table->boolean('campaign_joker_freechoice')->default(false)->after('campaign_is_always_available'));
            $this->addColumnIfMissing($table, 'actions', 'campaign_grants_signature', fn () => $table->boolean('campaign_grants_signature')->default(false)->after('campaign_joker_freechoice'));
            // 'attack' | 'tactical' | 'summoning' — disambiguates which Aftermath
            // advancement picker the row feeds.
            $this->addColumnIfMissing($table, 'actions', 'campaign_advancement_kind', fn () => $table->string('campaign_advancement_kind', 24)->nullable()->after('campaign_grants_signature'));

            $this->addIndexIfMissing($table, 'actions', 'idx_ac_mode_kind', fn () => $table->index(['game_mode_type', 'campaign_advancement_kind'], 'idx_ac_mode_kind'));
            $this->addIndexIfMissing($table, 'actions', 'idx_ac_mode_flip', fn () => $table->index(['game_mode_type', 'campaign_flip_value'], 'idx_ac_mode_flip'));
        });

        // ──────────────────────────────────────────────────────────────
        // triggers — absorbs advancement_attack_mod + advancement_tactical_mod
        // ──────────────────────────────────────────────────────────────
        Schema::table('triggers', function (Blueprint $table) {
            $this->addColumnIfMissing($table, 'triggers', 'campaign_flip_value', fn () => $table->unsignedTinyInteger('campaign_flip_value')->nullable()->after('description'));
            $this->addColumnIfMissing($table, 'triggers', 'campaign_is_always_available', fn () => $table->boolean('campaign_is_always_available')->default(false)->after('campaign_flip_value'));
            $this->addColumnIfMissing($table, 'triggers', 'campaign_joker_freechoice', fn () => $table->boolean('campaign_joker_freechoice')->default(false)->after('campaign_is_always_available'));
            $this->addColumnIfMissing($table, 'triggers', 'campaign_grants_signature', fn () => $table->boolean('campaign_grants_signature')->default(false)->after('campaign_joker_freechoice'));
            // 'trigger' (regular new trigger), 'skl' (Skl boost), 'signature',
            // 'joker' (free-choice). Matches the old `advancement_*` modifier_type column.
            $this->addColumnIfMissing($table, 'triggers', 'campaign_modifier_type', fn () => $table->string('campaign_modifier_type', 16)->nullable()->after('campaign_grants_signature'));
            $this->addColumnIfMissing($table, 'triggers', 'campaign_skl_from', fn () => $table->unsignedTinyInteger('campaign_skl_from')->nullable()->after('campaign_modifier_type'));
            $this->addColumnIfMissing($table, 'triggers', 'campaign_skl_to', fn () => $table->unsignedTinyInteger('campaign_skl_to')->nullable()->after('campaign_skl_from'));
            // 'attack' vs 'tactical' — which advancement picker.
            $this->addColumnIfMissing($table, 'triggers', 'campaign_advancement_kind', fn () => $table->string('campaign_advancement_kind', 24)->nullable()->after('campaign_skl_to'));

            $this->addIndexIfMissing($table, 'triggers', 'idx_tr_mode_kind', fn () => $table->index(['game_mode_type', 'campaign_advancement_kind'], 'idx_tr_mode_kind'));
            $this->addIndexIfMissing($table, 'triggers', 'idx_tr_mode_flip', fn () => $table->index(['game_mode_type', 'campaign_flip_value'], 'idx_tr_mode_flip'));
        });

        // ──────────────────────────────────────────────────────────────
        // upgrades — absorbs equipment_catalog + injury_catalog
        // ──────────────────────────────────────────────────────────────
        Schema::table('upgrades', function (Blueprint $table) {
            // Discriminator — 'equipment' vs 'injury' (the two distinct upgrade
            // kinds Campaign Mode introduces). Null for Standard upgrades.
            $this->addColumnIfMissing($table, 'upgrades', 'campaign_upgrade_kind', fn () => $table->string('campaign_upgrade_kind', 24)->nullable()->after('description'));

            // Equipment-specific columns (from equipment_catalog)
            $this->addColumnIfMissing($table, 'upgrades', 'campaign_br', fn () => $table->unsignedTinyInteger('campaign_br')->nullable()->after('campaign_upgrade_kind'));
            $this->addColumnIfMissing($table, 'upgrades', 'campaign_cc', fn () => $table->unsignedTinyInteger('campaign_cc')->nullable()->after('campaign_br'));
            $this->addColumnIfMissing($table, 'upgrades', 'campaign_pool_suit_a', fn () => $table->string('campaign_pool_suit_a', 12)->nullable()->after('campaign_cc'));
            $this->addColumnIfMissing($table, 'upgrades', 'campaign_pool_suit_b', fn () => $table->string('campaign_pool_suit_b', 12)->nullable()->after('campaign_pool_suit_a'));
            $this->addColumnIfMissing($table, 'upgrades', 'campaign_is_always_available', fn () => $table->boolean('campaign_is_always_available')->default(false)->after('campaign_pool_suit_b'));
            $this->addColumnIfMissing($table, 'upgrades', 'campaign_ttw_only', fn () => $table->boolean('campaign_ttw_only')->default(false)->after('campaign_is_always_available'));
            $this->addColumnIfMissing($table, 'upgrades', 'campaign_is_omens_mark', fn () => $table->boolean('campaign_is_omens_mark')->default(false)->after('campaign_ttw_only'));
            $this->addColumnIfMissing($table, 'upgrades', 'campaign_is_unique', fn () => $table->boolean('campaign_is_unique')->default(false)->after('campaign_is_omens_mark'));
            $this->addColumnIfMissing($table, 'upgrades', 'campaign_leader_only', fn () => $table->boolean('campaign_leader_only')->default(false)->after('campaign_is_unique'));
            $this->addColumnIfMissing($table, 'upgrades', 'campaign_non_unique_only', fn () => $table->boolean('campaign_non_unique_only')->default(false)->after('campaign_leader_only'));
            $this->addColumnIfMissing($table, 'upgrades', 'campaign_annihilate_after_game', fn () => $table->boolean('campaign_annihilate_after_game')->default(false)->after('campaign_non_unique_only'));
            $this->addColumnIfMissing($table, 'upgrades', 'campaign_is_red_joker_entry', fn () => $table->boolean('campaign_is_red_joker_entry')->default(false)->after('campaign_annihilate_after_game'));

            // Injury-specific columns (from injury_catalog)
            $this->addColumnIfMissing($table, 'upgrades', 'campaign_flip_value', fn () => $table->unsignedTinyInteger('campaign_flip_value')->nullable()->after('campaign_is_red_joker_entry'));
            // 'pc' | 'te' | 'black_joker' | 'red_joker'
            $this->addColumnIfMissing($table, 'upgrades', 'campaign_suit_pool', fn () => $table->string('campaign_suit_pool', 16)->nullable()->after('campaign_flip_value'));
            $this->addColumnIfMissing($table, 'upgrades', 'campaign_is_traitor', fn () => $table->boolean('campaign_is_traitor')->default(false)->after('campaign_suit_pool'));
            $this->addColumnIfMissing($table, 'upgrades', 'campaign_is_close_call', fn () => $table->boolean('campaign_is_close_call')->default(false)->after('campaign_is_traitor'));
            $this->addColumnIfMissing($table, 'upgrades', 'campaign_annihilates_model', fn () => $table->boolean('campaign_annihilates_model')->default(false)->after('campaign_is_close_call'));
            $this->addColumnIfMissing($table, 'upgrades', 'campaign_reflip_if_no_triggers', fn () => $table->boolean('campaign_reflip_if_no_triggers')->default(false)->after('campaign_annihilates_model'));
            $this->addColumnIfMissing($table, 'upgrades', 'campaign_reflip_if_master_or_totem', fn () => $table->boolean('campaign_reflip_if_master_or_totem')->default(false)->after('campaign_reflip_if_no_triggers'));

            $this->addIndexIfMissing($table, 'upgrades', 'idx_up_mode_kind', fn () => $table->index(['game_mode_type', 'campaign_upgrade_kind'], 'idx_up_mode_kind'));
            $this->addIndexIfMissing($table, 'upgrades', 'idx_up_mode_kind_br', fn () => $table->index(['game_mode_type', 'campaign_upgrade_kind', 'campaign_br'], 'idx_up_mode_kind_br'));
            $this->addIndexIfMissing($table, 'upgrades', 'idx_up_mode_kind_flip', fn () => $table->index(['game_mode_type', 'campaign_upgrade_kind', 'campaign_flip_value'], 'idx_up_mode_kind_flip'));
            $this->addIndexIfMissing($table, 'upgrades', 'idx_up_camp_pools', fn () => $table->index(['campaign_pool_suit_a', 'campaign_pool_suit_b'], 'idx_up_camp_pools'));
        });
    }

    public function down(): void
    {
        Schema::table('upgrades', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'upgrades', 'idx_up_camp_pools');
            $this->dropIndexIfExists($table, 'upgrades', 'idx_up_mode_kind_flip');
            $this->dropIndexIfExists($table, 'upgrades', 'idx_up_mode_kind_br');
            $this->dropIndexIfExists($table, 'upgrades', 'idx_up_mode_kind');
            $this->dropColumnsIfExist($table, 'upgrades', [
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
            $this->dropIndexIfExists($table, 'triggers', 'idx_tr_mode_flip');
            $this->dropIndexIfExists($table, 'triggers', 'idx_tr_mode_kind');
            $this->dropColumnsIfExist($table, 'triggers', [
                'campaign_flip_value', 'campaign_is_always_available', 'campaign_joker_freechoice',
                'campaign_grants_signature', 'campaign_modifier_type',
                'campaign_skl_from', 'campaign_skl_to', 'campaign_advancement_kind',
            ]);
        });

        Schema::table('actions', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'actions', 'idx_ac_mode_flip');
            $this->dropIndexIfExists($table, 'actions', 'idx_ac_mode_kind');
            $this->dropColumnsIfExist($table, 'actions', [
                'campaign_flip_value', 'campaign_is_always_available', 'campaign_joker_freechoice',
                'campaign_grants_signature', 'campaign_advancement_kind',
            ]);
        });

        Schema::table('abilities', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'abilities', 'idx_ab_mode_crew_card');
            $this->dropIndexIfExists($table, 'abilities', 'idx_ab_mode_flip');
            $this->dropColumnsIfExist($table, 'abilities', [
                'campaign_flip_value', 'campaign_is_always_available', 'campaign_joker_freechoice',
                'is_crew_card_effect',
                'requires_token_choice', 'requires_marker_choice', 'requires_upgrade_type_choice',
            ]);
        });
    }

    /**
     * Register a column on the blueprint only if it doesn't already exist —
     * makes a partial / re-run of this migration idempotent.
     */
    private function addColumnIfMissing(Blueprint $table, string $tableName, string $column, callable $define): void
    {
        if (! Schema::hasColumn($tableName, $column)) {
            $define();
        }
    }

    /**
     * Register an index on the blueprint only if it doesn't already exist.
     */
    private function addIndexIfMissing(Blueprint $table, string $tableName, string $index, callable $define): void
    {
        if (! Schema::hasIndex($tableName, $index)) {
            $define();
        }
    }

    private function dropIndexIfExists(Blueprint $table, string $tableName, string $index): void
    {
        if (Schema::hasIndex($tableName, $index)) {
            $table->dropIndex($index);
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function dropColumnsIfExist(Blueprint $table, string $tableName, array $columns): void
    {
        $existing = array_filter($columns, fn (string $column) => Schema::hasColumn($tableName, $column));

        if ($existing !== []) {
            $table->dropColumn(array_values($existing));
        }
    }
};
