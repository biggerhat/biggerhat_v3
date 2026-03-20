<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->boolean('is_solo')->default(false)->after('is_tie');
            $table->unsignedTinyInteger('winner_slot')->nullable()->after('winner_id');
        });

        Schema::table('game_players', function (Blueprint $table) {
            $table->dropUnique(['game_id', 'user_id']);
            $table->string('opponent_name')->nullable()->after('slot');
            $table->boolean('crew_skipped')->default(false)->after('crew_build_id');
        });

        // Make user_id nullable for solo opponent players
        Schema::table('game_players', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['is_solo', 'winner_slot']);
        });

        Schema::table('game_players', function (Blueprint $table) {
            $table->dropColumn(['opponent_name', 'crew_skipped']);
            $table->foreignId('user_id')->nullable(false)->change();
            $table->unique(['game_id', 'user_id']);
        });
    }
};
