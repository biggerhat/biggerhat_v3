<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a player declares at check-in for rulesets that use TOS Allegiances
        // (Malifaux rulesets keep using `faction`). A Garrison is the player's public
        // pool for the event; both links null out if the referenced row is deleted.
        Schema::table('tournament_players', function (Blueprint $table) {
            $table->foreignId('allegiance_id')->nullable()->after('faction')->constrained('tos_allegiances')->nullOnDelete();
            $table->foreignId('garrison_id')->nullable()->after('allegiance_id')->constrained('tos_garrisons')->nullOnDelete();
        });

        Schema::table('tournament_rsvps', function (Blueprint $table) {
            $table->foreignId('allegiance_id')->nullable()->after('faction')->constrained('tos_allegiances')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tournament_rsvps', function (Blueprint $table) {
            $table->dropForeign(['allegiance_id']);
            $table->dropColumn('allegiance_id');
        });

        Schema::table('tournament_players', function (Blueprint $table) {
            $table->dropForeign(['allegiance_id']);
            $table->dropForeign(['garrison_id']);
            $table->dropColumn(['allegiance_id', 'garrison_id']);
        });
    }
};
