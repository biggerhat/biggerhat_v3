<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // Competitors (players, or teams in team events) the venue can take; null = unlimited. RSVPs past it are waitlisted.
            $table->unsignedSmallInteger('max_players')->nullable();
            // After this moment new RSVPs are refused (cancelling stays possible).
            $table->timestamp('registration_closes_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['max_players', 'registration_closes_at']);
        });
    }
};
