<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_crew_members', function (Blueprint $table) {
            $table->index('character_id');
        });

        Schema::table('game_turns', function (Blueprint $table) {
            $table->index('game_player_id');
        });
    }

    public function down(): void
    {
        Schema::table('game_crew_members', function (Blueprint $table) {
            $table->dropIndex(['character_id']);
        });

        Schema::table('game_turns', function (Blueprint $table) {
            $table->dropIndex(['game_player_id']);
        });
    }
};
