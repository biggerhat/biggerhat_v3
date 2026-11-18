<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a flip is for, as the board knows it (a model's id), so the result can be drawn beside that model.
        Schema::table('game_fate_flips', function (Blueprint $table) {
            $table->string('subject', 40)->nullable()->after('label');
        });

        // A request from one player to the other that only the other can answer: "discard N cards" (they choose which) or "show your
        // hand". A shown hand is stored here as a snapshot, and only the player who asked is ever given it.
        Schema::create('game_fate_prompts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('from_player_id')->constrained('game_players')->cascadeOnDelete();
            $table->foreignId('to_player_id')->constrained('game_players')->cascadeOnDelete();
            $table->string('kind', 16); // discard | reveal
            $table->unsignedTinyInteger('count')->nullable(); // cards to discard
            $table->string('note', 80)->nullable(); // which effect asked
            $table->string('status', 16)->default('pending'); // pending | done | declined
            $table->json('cards')->nullable(); // a shown hand: for the asker's eyes only
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();
            $table->index(['game_id', 'status'], 'fate_prompts_game_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_fate_prompts');
        Schema::table('game_fate_flips', function (Blueprint $table) {
            $table->dropColumn('subject');
        });
    }
};
