<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A single generated card image per crew, combining the crew's starter
 * effect (CampaignCrew::crewCardEffect) with every currently-held Tier-4
 * borrowed effect (CampaignCrew::crewCardAdvancements) onto one face —
 * distinct from CampaignCrewCard::front_image, which is a per-catalog-row
 * admin preview image, not what's actually shown to players in play.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_crews', function (Blueprint $table) {
            $table->string('crew_card_front_image')->nullable()->after('crew_card_choice');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_crews', function (Blueprint $table) {
            $table->dropColumn('crew_card_front_image');
        });
    }
};
