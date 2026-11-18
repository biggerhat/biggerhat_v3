<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per card: 54 per player, created when the Fate deck is first used. Rows move between zones; nothing is deleted.
        Schema::create('game_fate_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('game_player_id')->constrained('game_players')->cascadeOnDelete();
            $table->unsignedTinyInteger('card'); // 0-51 the suits in order (Rams, Masks, Tomes, Crows) then 52 Red Joker, 53 Black Joker
            $table->string('zone', 16)->default('deck');
            $table->smallInteger('position')->default(0); // order inside the zone; the deck's top is the lowest, the discard pile is oldest first
            $table->string('joker_suit', 8)->nullable(); // a Red Joker's chosen suit while it is in the conflict
            $table->foreignId('flip_id')->nullable(); // the flip that put it in the conflict
            $table->unique(['game_player_id', 'card'], 'fate_cards_player_card_unique');
            $table->index(['game_id', 'game_player_id', 'zone', 'position'], 'fate_cards_zone_idx');
        });

        Schema::create('game_fate_flips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('game_player_id')->constrained('game_players')->cascadeOnDelete();
            $table->smallInteger('modifier')->default(0); // + for positive flips, - for negative
            $table->string('label', 60)->nullable(); // what the flip is for ("Attack duel", "Insight")
            $table->unsignedBigInteger('chosen_id')->nullable(); // the game_fate_cards row that is the result
            $table->json('cheaters')->nullable(); // players who have had their chance to cheat this flip
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['game_id', 'resolved_at'], 'fate_flips_open_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_fate_flips');
        Schema::dropIfExists('game_fate_cards');
    }
};
