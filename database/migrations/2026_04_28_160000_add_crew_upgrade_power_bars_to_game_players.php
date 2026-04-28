<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            // Per-game state for the master's crew-level upgrade power bars.
            // Shape: { "<upgrade_id>": <int current_power_bar>, ... }. The max
            // comes from upgrades.power_bar_count — only the live counter lives
            // here so we never duplicate the source-of-truth max.
            $table->json('crew_upgrade_power_bars')->nullable()->after('active_crew_upgrade_id');
        });
    }

    public function down(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->dropColumn('crew_upgrade_power_bars');
        });
    }
};
