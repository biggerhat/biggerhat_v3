<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Back face of the combined per-crew card (T2-22): starter effect on the
 * front (crew_card_front_image), every held Tier-4 borrowed effect on the
 * back — see CombinedCrewCardFace's `side` prop and CombinedCrewCardEffects'
 * per-item `source` tag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_crews', function (Blueprint $table) {
            $table->string('crew_card_back_image')->nullable()->after('crew_card_front_image');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_crews', function (Blueprint $table) {
            $table->dropColumn('crew_card_back_image');
        });
    }
};
