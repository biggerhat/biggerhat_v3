<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_turns', function (Blueprint $table) {
            $table->unsignedSmallInteger('strategy_points')->default(0)->after('scheme_id');
            $table->unsignedSmallInteger('scheme_points')->default(0)->after('strategy_points');
        });
    }

    public function down(): void
    {
        Schema::table('game_turns', function (Blueprint $table) {
            $table->dropColumn(['strategy_points', 'scheme_points']);
        });
    }
};
