<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign Catalog Consolidation — Phase 5: drop the dedicated catalog tables
 * + their FK constraints on campaign pivot tables now that everything reads
 * from the core catalog (abilities / actions / triggers / upgrades) via the
 * `campaign_*` columns added in Phase 1 and the pivot FK columns wired in
 * Phase 2.
 *
 * Kept (not dropped) — pure flip-value lookup tables with no core-catalog
 * analog:
 *   - back_alley_doctor_results
 *   - lucky_miss_catalog
 *   - weekly_events_catalog
 *
 * See `/home/maczirr/.claude/plans/floating-whistling-waterfall.md`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ──────────────────────────────────────────────────────────────
        // Drop FK constraints + legacy columns on pivot tables.
        //
        // We DON'T use the dropForeignSafe() macro here — that macro no-ops
        // on SQLite tests, which then leaves the FK definition referencing
        // a column we're about to drop, and SQLite's strict-table validation
        // rejects the column drop. Use tryDropForeign() instead, which
        // attempts the drop on every driver and swallows "doesn't exist"
        // errors so the migration is rerunnable.
        // ──────────────────────────────────────────────────────────────

        $this->tryDropForeign('campaign_equipment', ['equipment_catalog_id']);
        if (Schema::hasColumn('campaign_equipment', 'equipment_catalog_id')) {
            Schema::table('campaign_equipment', function (Blueprint $table) {
                $table->dropColumn('equipment_catalog_id');
            });
        }

        $this->tryDropForeign('campaign_arsenal_model_injuries', ['injury_catalog_id']);
        if (Schema::hasColumn('campaign_arsenal_model_injuries', 'injury_catalog_id')) {
            Schema::table('campaign_arsenal_model_injuries', function (Blueprint $table) {
                $table->dropColumn('injury_catalog_id');
            });
        }

        // campaign_aftermath_injury.resulting_injury_id FKs to injury_catalog.
        // Drop the constraint before injury_catalog goes; the column itself
        // is no longer read by app code so we drop it too.
        $this->tryDropForeign('campaign_aftermath_injury', ['resulting_injury_id']);
        if (Schema::hasColumn('campaign_aftermath_injury', 'resulting_injury_id')) {
            Schema::table('campaign_aftermath_injury', function (Blueprint $table) {
                $table->dropColumn('resulting_injury_id');
            });
        }

        // campaign_leader_advancements.catalog_id was polymorphic — no FK to
        // drop. There's a composite index on (source_table, catalog_id) that
        // must be dropped before the column can go.
        if (Schema::hasColumn('campaign_leader_advancements', 'catalog_id')) {
            try {
                Schema::table('campaign_leader_advancements', function (Blueprint $table) {
                    $table->dropIndex('cla_table_catalog_idx');
                });
            } catch (\Throwable $e) {
                // Index already gone — fine.
            }
            Schema::table('campaign_leader_advancements', function (Blueprint $table) {
                $table->dropColumn('catalog_id');
            });
        }

        // campaign_totem_origins.totem_catalog_id had a FK to totem_catalog.
        $this->tryDropForeign('campaign_totem_origins', ['totem_catalog_id']);

        // crew_card_effect_id columns previously FK'd to crew_card_effects.
        // After Phase 2 remapping these now hold abilities.id values, but the
        // old FK still references the (about-to-be-dropped) crew_card_effects
        // table. Drop both before dropping the table.
        try {
            Schema::table('campaign_crews', function (Blueprint $table) {
                $table->dropIndex('cc_crew_card_effect_idx');
            });
        } catch (\Throwable $e) {
            // Index already gone — fine.
        }
        $this->tryDropForeign('campaign_crews', ['crew_card_effect_id']);

        try {
            Schema::table('campaign_crew_card_advancements', function (Blueprint $table) {
                $table->dropIndex('ccca_crew_card_effect_idx');
            });
        } catch (\Throwable $e) {
            // Index already gone — fine.
        }
        $this->tryDropForeign('campaign_crew_card_advancements', ['crew_card_effect_id']);
        $this->tryDropForeign('campaign_crew_card_advancements', ['acquired_aftermath_id']);

        // ──────────────────────────────────────────────────────────────
        // Drop the dedicated catalog tables themselves.
        // ──────────────────────────────────────────────────────────────
        Schema::dropIfExists('crew_card_effects');
        Schema::dropIfExists('equipment_catalog');
        Schema::dropIfExists('injury_catalog');
        Schema::dropIfExists('advancement_attack_mod');
        Schema::dropIfExists('advancement_tactical_mod');
        Schema::dropIfExists('advancement_action');
        Schema::dropIfExists('advancement_ability');
        Schema::dropIfExists('summoning_advancement_catalog');
        Schema::dropIfExists('totem_catalog');
    }

    /**
     * Drop a foreign key by column. Idempotent — swallows the doctrine
     * "constraint does not exist" exception when the FK was never created
     * (e.g. test DB rebuilt without the original FK migration).
     */
    private function tryDropForeign(string $table, array $columns): void
    {
        try {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                $t->dropForeign($columns);
            });
        } catch (\Throwable $e) {
            // Constraint already absent — fine, the migration is rerunnable.
        }
    }

    public function down(): void
    {
        // Rollback isn't practical — restoring data requires the pre-Phase-2
        // backup. Schema-only re-creation would leave tables empty and FK
        // references in pivot tables broken.
        //
        // To roll back: restore the database from a pre-migration backup,
        // then revert the entire Campaign Catalog Consolidation migration
        // chain (Phase 5 → Phase 2 → Phase 1).
    }
};
