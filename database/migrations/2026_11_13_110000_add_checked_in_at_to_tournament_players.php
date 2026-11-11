<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_players', function (Blueprint $table) {
            // When the player arrived and confirmed they're playing (the packets ask players to check in).
            $table->timestamp('checked_in_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tournament_players', function (Blueprint $table) {
            $table->dropColumn('checked_in_at');
        });
    }
};
