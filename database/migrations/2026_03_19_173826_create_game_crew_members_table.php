<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_crew_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('game_player_id')->constrained('game_players')->cascadeOnDelete();
            $table->foreignId('character_id')->constrained('characters');
            $table->string('display_name');
            $table->unsignedSmallInteger('current_health')->nullable();
            $table->unsignedSmallInteger('max_health')->nullable();
            $table->boolean('is_killed')->default(false);
            $table->boolean('is_summoned')->default(false);
            $table->unsignedSmallInteger('cost')->nullable();
            $table->string('station')->nullable();
            $table->string('hiring_category')->nullable();
            $table->string('front_image')->nullable();
            $table->json('attached_upgrades')->nullable();
            $table->json('attached_tokens')->nullable();
            $table->json('attached_markers')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['game_id', 'game_player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_crew_members');
    }
};
