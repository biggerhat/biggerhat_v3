<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Some crew card effects require the player to choose a token, marker, or
     * upgrade when the card is selected (pg 17 — e.g. "choose a token listed on
     * a crew card belonging to a master with either of this crew's keywords").
     * Stored as { type: 'token'|'marker'|'upgrade', id: int, name: string }.
     */
    public function up(): void
    {
        Schema::table('campaign_crews', function (Blueprint $table) {
            $table->json('crew_card_choice')->nullable()->after('crew_card_effect_id');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_crews', function (Blueprint $table) {
            $table->dropColumn('crew_card_choice');
        });
    }
};
