<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('slot'); // 1 or 2
            $table->string('faction')->nullable();
            $table->string('master_name')->nullable();
            $table->foreignId('master_id')->nullable()->constrained('characters')->nullOnDelete();
            $table->foreignId('crew_build_id')->nullable()->constrained('crew_builds')->nullOnDelete();
            $table->string('role')->nullable(); // attacker or defender
            $table->foreignId('current_scheme_id')->nullable()->constrained('schemes')->nullOnDelete();
            $table->unsignedSmallInteger('total_points')->default(0);
            $table->boolean('is_turn_complete')->default(false);
            $table->boolean('is_game_complete')->default(false);
            $table->timestamps();

            $table->unique(['game_id', 'user_id']);
            $table->unique(['game_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_players');
    }
};
