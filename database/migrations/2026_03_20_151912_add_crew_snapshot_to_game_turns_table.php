<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_turns', function (Blueprint $table) {
            $table->json('crew_snapshot')->nullable()->after('scheme_points');
        });
    }

    public function down(): void
    {
        Schema::table('game_turns', function (Blueprint $table) {
            $table->dropColumn('crew_snapshot');
        });
    }
};
