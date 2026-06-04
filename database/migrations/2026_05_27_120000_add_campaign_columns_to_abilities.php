<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign Catalog Consolidation — Phase 1a: add campaign columns to `abilities`
 * (absorbs crew_card_effects + advancement_ability). Split out from the original
 * mega-migration so each table is recorded independently and a stall on one table
 * doesn't lose the others' progress.
 *
 * All adds are nullable / defaulted and appended at the end of the table (no
 * `->after()`), so MySQL 8 can use ALGORITHM=INSTANT and hold only a brief
 * metadata lock. Helpers guard on existence so a re-run after a partial apply is
 * a no-op.
 *
 * See `/home/maczirr/.claude/plans/floating-whistling-waterfall.md`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addColumns('abilities', [
            // Advancement gating (from advancement_ability)
            'campaign_flip_value' => fn (Blueprint $t) => $t->unsignedTinyInteger('campaign_flip_value')->nullable(),
            'campaign_is_always_available' => fn (Blueprint $t) => $t->boolean('campaign_is_always_available')->default(false),
            'campaign_joker_freechoice' => fn (Blueprint $t) => $t->boolean('campaign_joker_freechoice')->default(false),

            // Crew Card effect classification (from crew_card_effects)
            'is_crew_card_effect' => fn (Blueprint $t) => $t->boolean('is_crew_card_effect')->default(false),
            'requires_token_choice' => fn (Blueprint $t) => $t->boolean('requires_token_choice')->default(false),
            'requires_marker_choice' => fn (Blueprint $t) => $t->boolean('requires_marker_choice')->default(false),
            'requires_upgrade_type_choice' => fn (Blueprint $t) => $t->boolean('requires_upgrade_type_choice')->default(false),
        ]);

        $this->addIndex('abilities', 'idx_ab_mode_flip', ['game_mode_type', 'campaign_flip_value']);
        $this->addIndex('abilities', 'idx_ab_mode_crew_card', ['game_mode_type', 'is_crew_card_effect']);
    }

    public function down(): void
    {
        $this->dropIndexIfExists('abilities', 'idx_ab_mode_crew_card');
        $this->dropIndexIfExists('abilities', 'idx_ab_mode_flip');
        $this->dropColumnsIfExist('abilities', [
            'campaign_flip_value', 'campaign_is_always_available', 'campaign_joker_freechoice',
            'is_crew_card_effect',
            'requires_token_choice', 'requires_marker_choice', 'requires_upgrade_type_choice',
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
