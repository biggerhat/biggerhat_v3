<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_aftermaths', function (Blueprint $table) {
            // Optional player-written journal entry for this game, shown
            // chronologically on the Arsenal Sheet. Free text, not a rules
            // mechanic — purely narrative.
            $table->text('story_entry')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_aftermaths', function (Blueprint $table) {
            $table->dropColumn('story_entry');
        });
    }
};
