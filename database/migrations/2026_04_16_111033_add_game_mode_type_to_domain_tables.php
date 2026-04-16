<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = ['characters', 'actions', 'abilities', 'keywords', 'upgrades'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('game_mode_type')->default('standard')->after('id');
                $table->index('game_mode_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['characters', 'actions', 'abilities', 'keywords', 'upgrades'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex(['game_mode_type']);
                $table->dropColumn('game_mode_type');
            });
        }
    }
};
