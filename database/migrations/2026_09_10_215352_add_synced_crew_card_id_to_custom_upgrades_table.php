<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * StartingArsenalController::saveCrewCardToCardCreator() previously
 * rebuilt content_blocks from the catalog CampaignCrewCard on every single
 * re-save (e.g. renaming, or re-saving the wizard for an unrelated reason),
 * silently wiping out anything the player had since added via the Card
 * Creator editor. This column remembers which catalog row was last synced
 * so a re-save with the SAME crew_card_effect_id can skip the rebuild
 * (preserving edits) while a genuine re-pick of a different crew card still
 * refreshes content_blocks from the new selection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_upgrades', function (Blueprint $table) {
            $table->foreignId('synced_crew_card_id')->nullable()->after('campaign_crew_id')
                ->constrained('campaign_crew_cards')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('custom_upgrades', function (Blueprint $table) {
            $table->dropConstrainedForeignId('synced_crew_card_id');
        });
    }
};
