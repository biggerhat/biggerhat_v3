<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flags a crew-domain CustomUpgrade as the snapshot copy StartingArsenalController
 * saves into a player's Card Creator library (mirrors is_campaign_leader/
 * is_campaign_totem on custom_characters). Used only to block deletion from the
 * generic Card Creator editor — unlike Leader/Totem rows this isn't the record
 * Campaign Mode reads back from (campaign_crews.crew_card_effect_id points at the
 * CampaignCrewCard catalog instead), so no campaign_crew_id FK is needed here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_upgrades', function (Blueprint $table) {
            $table->boolean('is_campaign_crew_card')->default(false)->after('domain');
        });
    }

    public function down(): void
    {
        Schema::table('custom_upgrades', function (Blueprint $table) {
            $table->dropColumn('is_campaign_crew_card');
        });
    }
};
