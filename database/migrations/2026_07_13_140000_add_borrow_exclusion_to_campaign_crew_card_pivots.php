<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tier-4 Crew Card Advancement (pg 32, 54) may not borrow an effect that
     * "references a power bar or causes the crew card to be swapped with a
     * different crew card." Nullable — null means eligible for borrowing.
     */
    public function up(): void
    {
        Schema::table('campaign_crew_card_actions', function (Blueprint $table) {
            $table->string('borrow_exclusion')->nullable()->after('is_signature_action');
        });

        Schema::table('campaign_crew_card_abilities', function (Blueprint $table) {
            $table->string('borrow_exclusion')->nullable()->after('ability_id');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_crew_card_actions', function (Blueprint $table) {
            $table->dropColumn('borrow_exclusion');
        });

        Schema::table('campaign_crew_card_abilities', function (Blueprint $table) {
            $table->dropColumn('borrow_exclusion');
        });
    }
};
