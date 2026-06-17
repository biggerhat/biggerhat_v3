<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_arsenal_models', function (Blueprint $table) {
            // JSON array of characteristic names gained via Back-Alley Doctor
            // outcomes (GainedUndead / GainedConstruct), e.g. ["Undead"].
            $table->json('gained_characteristics')->nullable()->after('title_group_key');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_arsenal_models', function (Blueprint $table) {
            $table->dropColumn('gained_characteristics');
        });
    }
};
