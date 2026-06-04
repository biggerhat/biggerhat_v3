<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign Catalog Consolidation — Phase 1d: add campaign columns to `upgrades`
 * (absorbs equipment_catalog + injury_catalog). This is the widest table (~21 new
 * columns + 4 indexes), so it was the most likely point for the original combined
 * migration to stall. See the abilities migration for the split rationale; columns
 * are appended (no `->after()`) so MySQL 8 can use ALGORITHM=INSTANT, and every
 * add/index is existence-guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addColumns('upgrades', [
            // Discriminator — 'equipment' vs 'injury'. Null for Standard upgrades.
            'campaign_upgrade_kind' => fn (Blueprint $t) => $t->string('campaign_upgrade_kind', 24)->nullable(),

            // Equipment-specific columns (from equipment_catalog)
            'campaign_br' => fn (Blueprint $t) => $t->unsignedTinyInteger('campaign_br')->nullable(),
            'campaign_cc' => fn (Blueprint $t) => $t->unsignedTinyInteger('campaign_cc')->nullable(),
            'campaign_pool_suit_a' => fn (Blueprint $t) => $t->string('campaign_pool_suit_a', 12)->nullable(),
            'campaign_pool_suit_b' => fn (Blueprint $t) => $t->string('campaign_pool_suit_b', 12)->nullable(),
            'campaign_is_always_available' => fn (Blueprint $t) => $t->boolean('campaign_is_always_available')->default(false),
            'campaign_ttw_only' => fn (Blueprint $t) => $t->boolean('campaign_ttw_only')->default(false),
            'campaign_is_omens_mark' => fn (Blueprint $t) => $t->boolean('campaign_is_omens_mark')->default(false),
            'campaign_is_unique' => fn (Blueprint $t) => $t->boolean('campaign_is_unique')->default(false),
            'campaign_leader_only' => fn (Blueprint $t) => $t->boolean('campaign_leader_only')->default(false),
            'campaign_non_unique_only' => fn (Blueprint $t) => $t->boolean('campaign_non_unique_only')->default(false),
            'campaign_annihilate_after_game' => fn (Blueprint $t) => $t->boolean('campaign_annihilate_after_game')->default(false),
            'campaign_is_red_joker_entry' => fn (Blueprint $t) => $t->boolean('campaign_is_red_joker_entry')->default(false),

            // Injury-specific columns (from injury_catalog)
            'campaign_flip_value' => fn (Blueprint $t) => $t->unsignedTinyInteger('campaign_flip_value')->nullable(),
            // 'pc' | 'te' | 'black_joker' | 'red_joker'
            'campaign_suit_pool' => fn (Blueprint $t) => $t->string('campaign_suit_pool', 16)->nullable(),
            'campaign_is_traitor' => fn (Blueprint $t) => $t->boolean('campaign_is_traitor')->default(false),
            'campaign_is_close_call' => fn (Blueprint $t) => $t->boolean('campaign_is_close_call')->default(false),
            'campaign_annihilates_model' => fn (Blueprint $t) => $t->boolean('campaign_annihilates_model')->default(false),
            'campaign_reflip_if_no_triggers' => fn (Blueprint $t) => $t->boolean('campaign_reflip_if_no_triggers')->default(false),
            'campaign_reflip_if_master_or_totem' => fn (Blueprint $t) => $t->boolean('campaign_reflip_if_master_or_totem')->default(false),
        ]);

        $this->addIndex('upgrades', 'idx_up_mode_kind', ['game_mode_type', 'campaign_upgrade_kind']);
        $this->addIndex('upgrades', 'idx_up_mode_kind_br', ['game_mode_type', 'campaign_upgrade_kind', 'campaign_br']);
        $this->addIndex('upgrades', 'idx_up_mode_kind_flip', ['game_mode_type', 'campaign_upgrade_kind', 'campaign_flip_value']);
        $this->addIndex('upgrades', 'idx_up_camp_pools', ['campaign_pool_suit_a', 'campaign_pool_suit_b']);
    }

    public function down(): void
    {
        $this->dropIndexIfExists('upgrades', 'idx_up_camp_pools');
        $this->dropIndexIfExists('upgrades', 'idx_up_mode_kind_flip');
        $this->dropIndexIfExists('upgrades', 'idx_up_mode_kind_br');
        $this->dropIndexIfExists('upgrades', 'idx_up_mode_kind');
        $this->dropColumnsIfExist('upgrades', [
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
    }

    /**
     * Add every missing column in a single ALTER (skips ones already present).
     *
     * @param  array<string, callable(Blueprint): mixed>  $defs
     */
    private function addColumns(string $tableName, array $defs): void
    {
        $missing = array_filter($defs, fn (string $col) => ! Schema::hasColumn($tableName, $col), ARRAY_FILTER_USE_KEY);

        if ($missing === []) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($missing) {
            foreach ($missing as $define) {
                $define($table);
            }
        });
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function addIndex(string $tableName, string $name, array $columns): void
    {
        if (! Schema::hasIndex($tableName, $name)) {
            Schema::table($tableName, fn (Blueprint $table) => $table->index($columns, $name));
        }
    }

    private function dropIndexIfExists(string $tableName, string $name): void
    {
        if (Schema::hasIndex($tableName, $name)) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropIndex($name));
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function dropColumnsIfExist(string $tableName, array $columns): void
    {
        $existing = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn($tableName, $col)));

        if ($existing !== []) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($existing));
        }
    }
};
