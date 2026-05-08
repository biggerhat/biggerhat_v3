<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Triggers gain `game_mode_type` to match the column already on actions
     * and abilities. Lets admins flag triggers as Bonanza-Brawl-only (or any
     * other mode) directly on the trigger row, rather than inferring through
     * the parent action. Default 'standard' keeps existing rows visible to
     * the standard listing.
     */
    public function up(): void
    {
        Schema::table('triggers', function (Blueprint $table) {
            $table->string('game_mode_type')->default('standard')->after('id');
            $table->index('game_mode_type');
        });
    }

    public function down(): void
    {
        Schema::table('triggers', function (Blueprint $table) {
            $table->dropIndex(['game_mode_type']);
            $table->dropColumn('game_mode_type');
        });
    }
};
