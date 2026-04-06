<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->string('crew_upgrade_mode')->default('select_one')->after('is_unhirable');
        });

        Schema::table('game_players', function (Blueprint $table) {
            $table->foreignId('active_crew_upgrade_id')->nullable()->after('crew_build_id')
                ->constrained('upgrades')->nullOnDelete();
        });

        // Backfill active_crew_upgrade_id from crew_build.crew_upgrade_id
        // Skip on empty databases (tests/fresh installs)
        if (\App\Models\GamePlayer::whereNotNull('crew_build_id')->exists()) {
            \App\Models\GamePlayer::whereNotNull('crew_build_id')
                ->whereNull('active_crew_upgrade_id')
                ->each(function ($player) {
                    $crewBuild = \App\Models\CrewBuild::withTrashed()->find($player->crew_build_id);
                    if ($crewBuild?->crew_upgrade_id) {
                        $player->update(['active_crew_upgrade_id' => $crewBuild->crew_upgrade_id]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('crew_upgrade_mode');
        });

        Schema::table('game_players', function (Blueprint $table) {
            $table->dropForeign(['active_crew_upgrade_id']);
            $table->dropColumn('active_crew_upgrade_id');
        });
    }
};
