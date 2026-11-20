<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // null = made before this existed (or not asked); never guessed
            $table->string('play_mode')->nullable()->after('format');
            // the saved table the game started from; the game keeps its own copy of the terrain in board_state
            $table->foreignId('tabletop_layout_id')->nullable()->after('play_mode')
                ->constrained('tabletop_layouts', indexName: 'games_tabletop_layout_fk')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropForeignSafe('games_tabletop_layout_fk');
            $table->dropColumn(['tabletop_layout_id', 'play_mode']);
        });
    }
};
