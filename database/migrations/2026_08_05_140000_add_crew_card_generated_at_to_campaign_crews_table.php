<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('campaign_crews', function (Blueprint $table) {
            // Set only when CombinedCrewCardImageGenerator::generate() actually
            // finishes — same reasoning as custom_characters.card_image_generated_at
            // (see 2026_07_28_225848): updated_at gets bumped synchronously by
            // whatever save dispatched the async render job, before it starts.
            $table->timestamp('crew_card_generated_at')->nullable()->after('crew_card_back_image');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_crews', function (Blueprint $table) {
            $table->dropColumn('crew_card_generated_at');
        });
    }
};
