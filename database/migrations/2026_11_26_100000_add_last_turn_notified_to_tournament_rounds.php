<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_rounds', function (Blueprint $table) {
            // Set once the "time is nearly up" notification has gone out for this run of the round (cleared on reset).
            $table->timestamp('last_turn_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tournament_rounds', function (Blueprint $table) {
            $table->dropColumn('last_turn_notified_at');
        });
    }
};
