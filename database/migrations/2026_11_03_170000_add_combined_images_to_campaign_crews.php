<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Side-by-side download image and small gallery thumbnail of the crew's combined Crew Card, for My Cards. */
    public function up(): void
    {
        Schema::table('campaign_crews', function (Blueprint $table) {
            $table->string('crew_card_combination_image')->nullable();
            $table->string('crew_card_thumb_image')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('campaign_crews', function (Blueprint $table) {
            $table->dropColumn(['crew_card_combination_image', 'crew_card_thumb_image']);
        });
    }
};
