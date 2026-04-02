<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->json('scheme_notes')->nullable()->after('current_scheme_id');
        });

        Schema::table('game_turns', function (Blueprint $table) {
            $table->json('scheme_notes')->nullable()->after('scheme_id');
        });
    }

    public function down(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->dropColumn('scheme_notes');
        });

        Schema::table('game_turns', function (Blueprint $table) {
            $table->dropColumn('scheme_notes');
        });
    }
};
