<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // 1 = individual event. >1 = team event: each competitor (a tournament_players row) is a team
            // of this many people, who play one game together against another team.
            $table->unsignedTinyInteger('team_size')->default(1);
        });

        Schema::create('tournament_player_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_player_id')->constrained('tournament_players', 'id', 'tpm_player_fk')->cascadeOnDelete();
            $table->string('display_name');
            $table->foreignId('user_id')->nullable()->constrained('users', 'id', 'tpm_user_fk')->nullOnDelete();
            // What this person declared (same shape as an individual player's declaration).
            $table->string('faction')->nullable();
            $table->foreignId('allegiance_id')->nullable()->constrained('tos_allegiances', 'id', 'tpm_allegiance_fk')->nullOnDelete();
            $table->foreignId('garrison_id')->nullable()->constrained('tos_garrisons', 'id', 'tpm_garrison_fk')->nullOnDelete();
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->index(['tournament_player_id', 'position'], 'tpm_player_position_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_player_members');

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('team_size');
        });
    }
};
