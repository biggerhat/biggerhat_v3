<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crew lists are per round, not per player: Gaining Grounds players bring a different crew each game. The pairing
 * already carries a crew per side (`player_one_crew_build_id` / `player_two_crew_build_id`), so that is where players
 * attach theirs, and the per-player column added earlier goes away.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_players', function (Blueprint $table) {
            $table->dropConstrainedForeignId('crew_build_id');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_players', function (Blueprint $table) {
            $table->foreignId('crew_build_id')->nullable()->constrained('crew_builds')->nullOnDelete();
        });
    }
};
