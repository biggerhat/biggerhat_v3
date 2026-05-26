<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the post-catalog foreign keys that the initial state migration left
 * as plain unsignedBigInteger columns with "FK after catalog migration"
 * comments. Now that the catalog tables (equipment_catalog, injury_catalog,
 * lucky_miss_catalog, totem_catalog, weekly_events_catalog, crew_card_effects)
 * exist, we can wire the FKs without circular-creation issues.
 *
 * `campaign_leader_advancements.catalog_id` is polymorphic (points to one of
 * the four advancement_* tables plus totem/summoning/crew_card depending on
 * source_table). No single FK is possible, so we just add an index.
 *
 * SQLite-tested via RefreshDatabase; MySQL prod gets the real constraints.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_weeks', function (Blueprint $table) {
            $table->foreign('weekly_event_id')
                ->references('id')->on('weekly_events_catalog')
                ->nullOnDelete();
            $table->index('weekly_event_id', 'cw_weekly_event_idx');
        });

        Schema::table('campaign_crews', function (Blueprint $table) {
            $table->foreign('crew_card_effect_id')
                ->references('id')->on('crew_card_effects')
                ->nullOnDelete();
            $table->index('crew_card_effect_id', 'cc_crew_card_effect_idx');
        });

        Schema::table('campaign_crew_card_advancements', function (Blueprint $table) {
            $table->foreign('crew_card_effect_id')
                ->references('id')->on('crew_card_effects')
                ->cascadeOnDelete();
            $table->foreign('acquired_aftermath_id')
                ->references('id')->on('campaign_aftermaths')
                ->nullOnDelete();
            $table->index('crew_card_effect_id', 'ccca_crew_card_effect_idx');
        });

        Schema::table('campaign_totem_origins', function (Blueprint $table) {
            $table->foreign('totem_catalog_id')
                ->references('id')->on('totem_catalog')
                ->cascadeOnDelete();
        });

        Schema::table('campaign_leader_advancements', function (Blueprint $table) {
            $table->foreign('source_aftermath_id')
                ->references('id')->on('campaign_aftermaths')
                ->nullOnDelete();
            // catalog_id is polymorphic (depends on source_table) — no FK,
            // just an index on the compound for lookups.
            $table->index(['source_table', 'catalog_id'], 'cla_table_catalog_idx');
        });

        Schema::table('campaign_arsenal_model_injuries', function (Blueprint $table) {
            $table->foreign('injury_catalog_id')
                ->references('id')->on('injury_catalog')
                ->cascadeOnDelete();
            $table->foreign('acquired_aftermath_id')
                ->references('id')->on('campaign_aftermaths')
                ->nullOnDelete();
        });

        Schema::table('campaign_arsenal_model_equipment', function (Blueprint $table) {
            $table->foreign('campaign_equipment_id')
                ->references('id')->on('campaign_equipment')
                ->cascadeOnDelete();
            $table->foreign('attached_for_game_id')
                ->references('id')->on('games')
                ->nullOnDelete();
        });

        Schema::table('campaign_equipment', function (Blueprint $table) {
            $table->foreign('equipment_catalog_id')
                ->references('id')->on('equipment_catalog')
                ->cascadeOnDelete();
            $table->foreign('acquired_aftermath_id')
                ->references('id')->on('campaign_aftermaths')
                ->nullOnDelete();
        });

        Schema::table('campaign_games', function (Blueprint $table) {
            $table->foreign('weekly_event_id')
                ->references('id')->on('weekly_events_catalog')
                ->nullOnDelete();
        });

        Schema::table('campaign_aftermath_injury', function (Blueprint $table) {
            $table->foreign('resulting_injury_id')
                ->references('id')->on('injury_catalog')
                ->nullOnDelete();
            $table->foreign('lucky_miss_catalog_id')
                ->references('id')->on('lucky_miss_catalog')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // SQLite drops FKs implicitly; MySQL needs the named drop. The
        // dropForeignSafe macro on Blueprint handles both (in test/sqlite
        // it's a no-op, otherwise dropForeign is called).
        Schema::table('campaign_aftermath_injury', function (Blueprint $table) {
            $table->dropForeignSafe(['resulting_injury_id']);
            $table->dropForeignSafe(['lucky_miss_catalog_id']);
        });
        Schema::table('campaign_games', function (Blueprint $table) {
            $table->dropForeignSafe(['weekly_event_id']);
        });
        Schema::table('campaign_equipment', function (Blueprint $table) {
            $table->dropForeignSafe(['equipment_catalog_id']);
            $table->dropForeignSafe(['acquired_aftermath_id']);
        });
        Schema::table('campaign_arsenal_model_equipment', function (Blueprint $table) {
            $table->dropForeignSafe(['campaign_equipment_id']);
            $table->dropForeignSafe(['attached_for_game_id']);
        });
        Schema::table('campaign_arsenal_model_injuries', function (Blueprint $table) {
            $table->dropForeignSafe(['injury_catalog_id']);
            $table->dropForeignSafe(['acquired_aftermath_id']);
        });
        Schema::table('campaign_leader_advancements', function (Blueprint $table) {
            $table->dropIndex('cla_table_catalog_idx');
            $table->dropForeignSafe(['source_aftermath_id']);
        });
        Schema::table('campaign_totem_origins', function (Blueprint $table) {
            $table->dropForeignSafe(['totem_catalog_id']);
        });
        Schema::table('campaign_crew_card_advancements', function (Blueprint $table) {
            $table->dropIndex('ccca_crew_card_effect_idx');
            $table->dropForeignSafe(['acquired_aftermath_id']);
            $table->dropForeignSafe(['crew_card_effect_id']);
        });
        Schema::table('campaign_crews', function (Blueprint $table) {
            $table->dropIndex('cc_crew_card_effect_idx');
            $table->dropForeignSafe(['crew_card_effect_id']);
        });
        Schema::table('campaign_weeks', function (Blueprint $table) {
            $table->dropIndex('cw_weekly_event_idx');
            $table->dropForeignSafe(['weekly_event_id']);
        });
    }
};
