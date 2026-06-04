<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign Catalog Consolidation — Phase 1c: add campaign columns to `triggers`
 * (absorbs advancement_attack_mod + advancement_tactical_mod). See the abilities
 * migration for the split rationale; columns are appended (no `->after()`) so MySQL
 * 8 can use ALGORITHM=INSTANT, and every add/index is existence-guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addColumns('triggers', [
            'campaign_flip_value' => fn (Blueprint $t) => $t->unsignedTinyInteger('campaign_flip_value')->nullable(),
            'campaign_is_always_available' => fn (Blueprint $t) => $t->boolean('campaign_is_always_available')->default(false),
            'campaign_joker_freechoice' => fn (Blueprint $t) => $t->boolean('campaign_joker_freechoice')->default(false),
            'campaign_grants_signature' => fn (Blueprint $t) => $t->boolean('campaign_grants_signature')->default(false),
            // 'trigger' (regular new trigger), 'skl' (Skl boost), 'signature',
            // 'joker' (free-choice). Matches the old `advancement_*` modifier_type column.
            'campaign_modifier_type' => fn (Blueprint $t) => $t->string('campaign_modifier_type', 16)->nullable(),
            'campaign_skl_from' => fn (Blueprint $t) => $t->unsignedTinyInteger('campaign_skl_from')->nullable(),
            'campaign_skl_to' => fn (Blueprint $t) => $t->unsignedTinyInteger('campaign_skl_to')->nullable(),
            // 'attack' vs 'tactical' — which advancement picker.
            'campaign_advancement_kind' => fn (Blueprint $t) => $t->string('campaign_advancement_kind', 24)->nullable(),
        ]);

        $this->addIndex('triggers', 'idx_tr_mode_kind', ['game_mode_type', 'campaign_advancement_kind']);
        $this->addIndex('triggers', 'idx_tr_mode_flip', ['game_mode_type', 'campaign_flip_value']);
    }

    public function down(): void
    {
        $this->dropIndexIfExists('triggers', 'idx_tr_mode_flip');
        $this->dropIndexIfExists('triggers', 'idx_tr_mode_kind');
        $this->dropColumnsIfExist('triggers', [
            'campaign_flip_value', 'campaign_is_always_available', 'campaign_joker_freechoice',
            'campaign_grants_signature', 'campaign_modifier_type',
            'campaign_skl_from', 'campaign_skl_to', 'campaign_advancement_kind',
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
