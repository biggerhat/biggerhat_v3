<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The turn sequence (CRG, Turn sequence): who has initiative this turn, whose activation it is, who activated last (the other player
        // chooses initiative next turn) and who passed last (the other may not pass straight back). Slots are 1 and 2; null means not yet known.
        Schema::table('games', function (Blueprint $table) {
            $table->unsignedTinyInteger('initiative_slot')->nullable();
            $table->unsignedTinyInteger('active_slot')->nullable();
            $table->unsignedTinyInteger('last_activated_slot')->nullable();
            $table->unsignedTinyInteger('passed_slot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['initiative_slot', 'active_slot', 'last_activated_slot', 'passed_slot']);
        });
    }
};
