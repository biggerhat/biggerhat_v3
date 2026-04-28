<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved searches now scope to a specific game system. Defaults existing rows
 * to 'malifaux' for back-compat — every saved search created before this
 * migration was a Malifaux search since TOS Advanced Search ships with this
 * change. The (user_id, game_system) index keeps the per-user-per-system
 * dropdown queries cheap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_searches', function (Blueprint $table) {
            $table->string('game_system', 16)->default('malifaux')->after('user_id');
            $table->index(['user_id', 'game_system']);
        });
    }

    public function down(): void
    {
        Schema::table('saved_searches', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'game_system']);
            $table->dropColumn('game_system');
        });
    }
};
