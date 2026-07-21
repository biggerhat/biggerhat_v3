<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The prior migration (add_is_campaign_crew_card_to_custom_upgrades_table)
 * deliberately skipped a campaign_crew_id FK, since at the time nothing in
 * Campaign Mode read the saved Card Creator copy back — it was a one-way
 * snapshot into the player's library. An "Edit Crew Card" button on the
 * Arsenal Sheet now needs to reliably find *this crew's* copy, so the FK
 * is added after all — mirrors custom_characters.campaign_crew_id exactly
 * (nullable, nullOnDelete, since existing non-campaign CustomUpgrade rows
 * and the crew-card row itself should survive a crew's deletion as a
 * dangling personal card rather than being force-deleted).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_upgrades', function (Blueprint $table) {
            $table->foreignId('campaign_crew_id')->nullable()->after('user_id')
                ->constrained('campaign_crews')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('custom_upgrades', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_crew_id');
        });
    }
};
