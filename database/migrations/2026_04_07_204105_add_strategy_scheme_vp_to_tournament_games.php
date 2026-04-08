<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_games', function (Blueprint $table) {
            if (! Schema::hasColumn('tournament_games', 'player_one_strategy_vp')) {
                $table->unsignedTinyInteger('player_one_strategy_vp')->nullable()->after('player_one_vp');
                $table->unsignedTinyInteger('player_one_scheme_vp')->nullable()->after('player_one_strategy_vp');
            }
            if (! Schema::hasColumn('tournament_games', 'player_two_strategy_vp')) {
                $table->unsignedTinyInteger('player_two_strategy_vp')->nullable()->after('player_two_vp');
                $table->unsignedTinyInteger('player_two_scheme_vp')->nullable()->after('player_two_strategy_vp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournament_games', function (Blueprint $table) {
            $table->dropColumn(['player_one_strategy_vp', 'player_one_scheme_vp', 'player_two_strategy_vp', 'player_two_scheme_vp']);
        });
    }
};
