<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reverts 2026_07_13_140000_add_borrow_exclusion_to_campaign_crew_card_pivots —
     * Tier-4 borrow-eligibility exclusion belongs on the general Crew Card
     * Upgrade catalog (`upgradeables` pivot, see
     * 2026_07_14_090500_add_borrow_exclusion_to_upgradeables_table), not on
     * CampaignCrewCard.
     */
    public function up(): void
    {
        Schema::table('campaign_crew_card_actions', function (Blueprint $table) {
            $table->dropColumn('borrow_exclusion');
        });

        Schema::table('campaign_crew_card_abilities', function (Blueprint $table) {
            $table->dropColumn('borrow_exclusion');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_crew_card_actions', function (Blueprint $table) {
            $table->string('borrow_exclusion')->nullable()->after('is_signature_action');
        });

        Schema::table('campaign_crew_card_abilities', function (Blueprint $table) {
            $table->string('borrow_exclusion')->nullable()->after('ability_id');
        });
    }
};
