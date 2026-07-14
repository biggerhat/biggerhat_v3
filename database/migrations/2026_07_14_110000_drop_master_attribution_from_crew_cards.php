<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the "master this card is printed on" concept entirely. It was a
 * static, admin-set field on a *shared catalog row* — but the same generic
 * CampaignCrewCard row can be picked as the starter/borrowed effect by many
 * different crews with different Leaders, so baking one master's name/
 * faction into the row (and into its cached generated card image) produced
 * wrong or coincidental theming for every crew but the one the admin had in
 * mind. Master/faction context for a *held* card is now derived live from
 * the holding crew's own current Leader at display time instead.
 *
 * `campaign_crew_card_advancements.source_master_id/_type` ("who was this
 * borrowed from") goes for the same reason — nothing can populate it
 * meaningfully anymore now that Tier-4 borrowing sources from a keyword
 * match (no single associated master) or the always-generic pg 15-16 list.
 * No FK constraints should exist on the two _id columns (already dropped by
 * 2026_07_11_220534_convert_campaign_crew_card_master_to_polymorphic), but
 * the last deploy incident (2026_07_13_130100's identifier-length failure)
 * is reason enough not to take that on faith — tryDropForeign() first so
 * this can't hard-fail if that assumption is ever wrong.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->tryDropForeign('campaign_crew_cards', ['master_id']);
        Schema::table('campaign_crew_cards', function (Blueprint $table) {
            $table->dropColumn(['master_id', 'master_type']);
        });

        $this->tryDropForeign('campaign_crew_card_advancements', ['source_master_id']);
        Schema::table('campaign_crew_card_advancements', function (Blueprint $table) {
            $table->dropColumn(['source_master_id', 'source_master_type']);
        });
    }

    public function down(): void
    {
        Schema::table('campaign_crew_cards', function (Blueprint $table) {
            $table->foreignId('master_id')->nullable()->after('description')->constrained('characters')->nullOnDelete();
            $table->string('master_type')->nullable()->after('master_id');
        });

        Schema::table('campaign_crew_card_advancements', function (Blueprint $table) {
            $table->foreignId('source_master_id')->nullable()->after('crew_card_effect_id')->constrained('characters')->nullOnDelete();
            $table->string('source_master_type')->nullable()->after('source_master_id');
        });
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
