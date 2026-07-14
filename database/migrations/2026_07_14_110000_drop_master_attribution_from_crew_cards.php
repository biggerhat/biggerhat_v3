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
 * No FK constraints exist on any of these four columns (already dropped by
 * 2026_07_11_220534_convert_campaign_crew_card_master_to_polymorphic), so
 * this is a plain column drop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_crew_cards', function (Blueprint $table) {
            $table->dropColumn(['master_id', 'master_type']);
        });

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
};
