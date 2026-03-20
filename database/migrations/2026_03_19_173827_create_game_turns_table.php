<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_turns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->unsignedTinyInteger('turn_number');
            $table->foreignId('game_player_id')->constrained('game_players')->cascadeOnDelete();
            $table->foreignId('scheme_id')->nullable()->constrained('schemes')->nullOnDelete();
            $table->unsignedSmallInteger('points_scored')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'turn_number', 'game_player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_turns');
    }
};
