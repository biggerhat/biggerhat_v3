<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Player-authored crew background ("story note") and tokens/markers the owner adds to their Crew Card by hand
 * (the card otherwise only derives them from the arsenal's characters and the crew card effects).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_crews', function (Blueprint $table) {
            $table->text('story_note')->nullable();
            $table->json('manual_token_ids')->nullable();
            $table->json('manual_marker_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('campaign_crews', function (Blueprint $table) {
            $table->dropColumn(['story_note', 'manual_token_ids', 'manual_marker_ids']);
        });
    }
};
