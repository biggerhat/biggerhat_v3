<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Now that Attack Mod / Tactical Mod / Action / Ability advancements live on
 * their own tables (see create_advancement_catalog_tables), drop the
 * `campaign_*` columns on triggers/actions/abilities that only ever existed
 * for those four kinds. `actions.campaign_advancement_kind` is kept — it's
 * still used for `'summoning'`, which stays on the shared catalog. Crew Card
 * columns on `abilities` (`is_crew_card_effect`, `requires_*`) are untouched.
 *
 * Also restores the polymorphic pointer on campaign_leader_advancements that
 * Phase 2 of Campaign Catalog Consolidation collapsed away: `catalog_core_id`
 * (which advancement table row was applied to the leader) stays; a new
 * `advancement_catalog_id` records which row on the new advancement_* tables
 * was picked, since that's no longer the same row.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dropIndexIfExists('triggers', 'idx_tr_mode_kind');
        $this->dropIndexIfExists('triggers', 'idx_tr_mode_flip');
        $this->dropColumnsIfExist('triggers', [
            'campaign_flip_value', 'campaign_is_always_available', 'campaign_joker_freechoice',
            'campaign_grants_signature', 'campaign_modifier_type',
            'campaign_skl_from', 'campaign_skl_to', 'campaign_advancement_kind',
        ]);

        $this->dropIndexIfExists('actions', 'idx_ac_mode_flip');
        $this->dropColumnsIfExist('actions', [
            'campaign_flip_value', 'campaign_is_always_available',
            'campaign_joker_freechoice', 'campaign_grants_signature',
        ]);

        $this->dropIndexIfExists('abilities', 'idx_ab_mode_flip');
        $this->dropColumnsIfExist('abilities', [
            'campaign_flip_value', 'campaign_is_always_available', 'campaign_joker_freechoice',
        ]);

        if (! Schema::hasColumn('campaign_leader_advancements', 'advancement_catalog_id')) {
            Schema::table('campaign_leader_advancements', function (Blueprint $table) {
                $table->unsignedBigInteger('advancement_catalog_id')->nullable()->after('source_table');
                $table->index(['source_table', 'advancement_catalog_id'], 'cla_source_advancement_idx');
            });
        }
    }

    public function down(): void
    {
        $this->dropIndexIfExists('campaign_leader_advancements', 'cla_source_advancement_idx');
        $this->dropColumnsIfExist('campaign_leader_advancements', ['advancement_catalog_id']);

        $this->addColumns('abilities', [
            'campaign_flip_value' => fn (Blueprint $t) => $t->unsignedTinyInteger('campaign_flip_value')->nullable(),
            'campaign_is_always_available' => fn (Blueprint $t) => $t->boolean('campaign_is_always_available')->default(false),
            'campaign_joker_freechoice' => fn (Blueprint $t) => $t->boolean('campaign_joker_freechoice')->default(false),
        ]);
        $this->addIndex('abilities', 'idx_ab_mode_flip', ['game_mode_type', 'campaign_flip_value']);

        $this->addColumns('actions', [
            'campaign_flip_value' => fn (Blueprint $t) => $t->unsignedTinyInteger('campaign_flip_value')->nullable(),
            'campaign_is_always_available' => fn (Blueprint $t) => $t->boolean('campaign_is_always_available')->default(false),
            'campaign_joker_freechoice' => fn (Blueprint $t) => $t->boolean('campaign_joker_freechoice')->default(false),
            'campaign_grants_signature' => fn (Blueprint $t) => $t->boolean('campaign_grants_signature')->default(false),
        ]);
        $this->addIndex('actions', 'idx_ac_mode_flip', ['game_mode_type', 'campaign_flip_value']);

        $this->addColumns('triggers', [
            'campaign_flip_value' => fn (Blueprint $t) => $t->unsignedTinyInteger('campaign_flip_value')->nullable(),
            'campaign_is_always_available' => fn (Blueprint $t) => $t->boolean('campaign_is_always_available')->default(false),
            'campaign_joker_freechoice' => fn (Blueprint $t) => $t->boolean('campaign_joker_freechoice')->default(false),
            'campaign_grants_signature' => fn (Blueprint $t) => $t->boolean('campaign_grants_signature')->default(false),
            'campaign_modifier_type' => fn (Blueprint $t) => $t->string('campaign_modifier_type', 16)->nullable(),
            'campaign_skl_from' => fn (Blueprint $t) => $t->unsignedTinyInteger('campaign_skl_from')->nullable(),
            'campaign_skl_to' => fn (Blueprint $t) => $t->unsignedTinyInteger('campaign_skl_to')->nullable(),
            'campaign_advancement_kind' => fn (Blueprint $t) => $t->string('campaign_advancement_kind', 24)->nullable(),
        ]);
        $this->addIndex('triggers', 'idx_tr_mode_kind', ['game_mode_type', 'campaign_advancement_kind']);
        $this->addIndex('triggers', 'idx_tr_mode_flip', ['game_mode_type', 'campaign_flip_value']);
    }

    /**
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
