<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // When on, the organizer expects every player to attach a Crew Builder list before the event starts.
            $table->boolean('require_crew_lists')->default(false);
        });

        Schema::table('tournament_players', function (Blueprint $table) {
            // The player's submitted list. Kept if the build is later deleted (null), never deletes the player.
            $table->foreignId('crew_build_id')->nullable()->constrained('crew_builds')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tournament_players', function (Blueprint $table) {
            $table->dropForeignSafe(['crew_build_id']);
            $table->dropColumn('crew_build_id');
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('require_crew_lists');
        });
    }
};
