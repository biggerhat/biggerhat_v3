<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Tabletop positions: {"positions": {"<crew member id>": {"x": inches, "y": inches}}}. Kept as JSON until the shape settles.
            $table->json('board_state')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('board_state');
        });
    }
};
