<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_crew_card_advancements', function (Blueprint $table) {
            // Mirrors campaign_crews.crew_card_choice ({ type, id, name }) —
            // the token/marker/upgrade-type pick a borrowed crew card effect
            // requires (pg 17), when it has one.
            $table->json('crew_card_choice')->nullable()->after('source_master_id');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_crew_card_advancements', function (Blueprint $table) {
            $table->dropColumn('crew_card_choice');
        });
    }
};
