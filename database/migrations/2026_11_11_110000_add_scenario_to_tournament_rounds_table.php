<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_rounds', function (Blueprint $table) {
            // Ruleset-defined scenario components that have no dedicated column,
            // e.g. TOS {"operation_id": 1, "deployment_id": 2}. Malifaux keeps its
            // strategy_id / deployment / scheme_pool columns (the Game Tracker reads them).
            $table->json('scenario')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tournament_rounds', function (Blueprint $table) {
            $table->dropColumn('scenario');
        });
    }
};
