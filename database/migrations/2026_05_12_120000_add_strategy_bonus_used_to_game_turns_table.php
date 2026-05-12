<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_turns', function (Blueprint $table) {
            // Distinguishes a 1-VP strategy turn that used the once-per-game
            // bonus from a 1-VP turn that scored only the base. Inferring
            // from strategy_points > 1 alone misses the bonus-only case.
            $table->boolean('strategy_bonus_used')->default(false)->after('strategy_points');
        });
    }

    public function down(): void
    {
        Schema::table('game_turns', function (Blueprint $table) {
            $table->dropColumn('strategy_bonus_used');
        });
    }
};
