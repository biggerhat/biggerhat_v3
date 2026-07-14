<?php

use App\Models\Campaign\CampaignCrewCard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tier-4 Crew Card Advancement (pg 32, 54) can now borrow an effect from
 * either the generic pg 15-16 catalog (`CampaignCrewCard`) or a real,
 * keyword-matched Crew Card Upgrade (`Upgrade::forCrews()`). One FK column
 * can't reference two tables, so `crew_card_effect_id` becomes polymorphic —
 * mirrors this same model's existing `source_master_id`/`source_master_type`
 * pattern. Drops the hard FK/cascade-delete added in
 * 2026_07_04_120000_add_crew_card_effect_fk_to_crew_card_advancements in
 * favor of app-level handling, same tradeoff already accepted for
 * source_master_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->tryDropForeign('campaign_crew_card_advancements', ['crew_card_effect_id']);

        Schema::table('campaign_crew_card_advancements', function (Blueprint $table) {
            $table->string('crew_card_effect_type')->nullable()->after('crew_card_effect_id');
        });

        // Every existing row (if any) was borrowed under the old,
        // CampaignCrewCard-only mechanism.
        DB::table('campaign_crew_card_advancements')
            ->whereNull('crew_card_effect_type')
            ->update(['crew_card_effect_type' => CampaignCrewCard::class]);
    }

    public function down(): void
    {
        Schema::table('campaign_crew_card_advancements', function (Blueprint $table) {
            $table->dropColumn('crew_card_effect_type');
        });

        $this->tryAddForeign('campaign_crew_card_advancements', 'crew_card_effect_id', function (Blueprint $table) {
            $table->foreign('crew_card_effect_id')
                ->references('id')->on('campaign_crew_cards')
                ->cascadeOnDelete();
        });
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
