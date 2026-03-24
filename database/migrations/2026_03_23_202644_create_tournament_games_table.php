<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_one_id')->constrained('tournament_players')->cascadeOnDelete();
            $table->foreignId('player_two_id')->nullable()->constrained('tournament_players')->nullOnDelete();
            // Player 1 game details
            $table->string('player_one_faction')->nullable();
            $table->string('player_one_master')->nullable();
            $table->string('player_one_title')->nullable();
            $table->foreignId('player_one_crew_build_id')->nullable()->constrained('crew_builds')->nullOnDelete();
            $table->unsignedTinyInteger('player_one_vp')->nullable();
            $table->unsignedTinyInteger('player_one_strategy_vp')->nullable();
            $table->unsignedTinyInteger('player_one_scheme_vp')->nullable();
            // Player 2 game details
            $table->string('player_two_faction')->nullable();
            $table->string('player_two_master')->nullable();
            $table->string('player_two_title')->nullable();
            $table->foreignId('player_two_crew_build_id')->nullable()->constrained('crew_builds')->nullOnDelete();
            $table->unsignedTinyInteger('player_two_vp')->nullable();
            $table->unsignedTinyInteger('player_two_strategy_vp')->nullable();
            $table->unsignedTinyInteger('player_two_scheme_vp')->nullable();
            $table->boolean('is_bye')->default(false);
            $table->boolean('is_forfeit')->default(false);
            $table->foreignId('forfeit_player_id')->nullable()->constrained('tournament_players')->nullOnDelete();
            $table->string('result')->default('pending');
            $table->unsignedTinyInteger('table_number')->nullable();
            $table->foreignId('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_games');
    }
};
