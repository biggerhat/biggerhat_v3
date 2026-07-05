<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `campaign_crew_card_advancements` (Tier-4 crew-card borrows, pg 32) has
 * existed since the original campaign-state migration but was never wired
 * into application code — its `crew_card_effect_id` FK originally pointed at
 * the (now-dropped) `crew_card_effects` table and lost its constraint during
 * the Campaign Catalog Consolidation. The table is empty in every environment
 * (never written to). This re-points the FK at the current
 * `campaign_crew_cards` table so the column is safe to start using.
 *
 * Idempotent via try/catch (mirrors tryDropForeign() elsewhere in this
 * codebase) rather than an information_schema existence check, so it works
 * the same on MySQL and the SQLite test database. Some environments already
 * carry this FK from prior migration-history drift — this migration is a
 * no-op there.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->tryAddForeign('campaign_crew_card_advancements', 'crew_card_effect_id', function (Blueprint $table) {
            $table->foreign('crew_card_effect_id')
                ->references('id')->on('campaign_crew_cards')
                ->cascadeOnDelete();
        });

        if (! Schema::hasIndex('campaign_crew_card_advancements', 'ccca_crew_card_effect_idx')) {
            Schema::table('campaign_crew_card_advancements', function (Blueprint $table) {
                $table->index('crew_card_effect_id', 'ccca_crew_card_effect_idx');
            });
        }

        $this->tryAddForeign('campaign_crew_card_advancements', 'acquired_aftermath_id', function (Blueprint $table) {
            $table->foreign('acquired_aftermath_id')
                ->references('id')->on('campaign_aftermaths')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        $this->tryDropForeign('campaign_crew_card_advancements', ['crew_card_effect_id']);
        $this->tryDropForeign('campaign_crew_card_advancements', ['acquired_aftermath_id']);
        if (Schema::hasIndex('campaign_crew_card_advancements', 'ccca_crew_card_effect_idx')) {
            Schema::table('campaign_crew_card_advancements', function (Blueprint $table) {
                $table->dropIndex('ccca_crew_card_effect_idx');
            });
        }
    }

    private function tryAddForeign(string $table, string $column, \Closure $add): void
    {
        try {
            Schema::table($table, $add);
        } catch (\Throwable $e) {
            // Constraint already exists — fine, the migration is rerunnable.
        }
    }

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
};
