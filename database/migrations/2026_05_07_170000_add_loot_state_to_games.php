<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-game Bonanza Brawl loot deck state. JSON because the shape is
     * coupled (deck / discard / dropped_markers all evolve together) and
     * normalizing it would mean three new tables for a feature that touches
     * one column inside a tight transaction. Standard-format games leave
     * this null.
     *
     * Shape: {
     *   "deck":     [card_id, card_id, ...],   // top is array tail (pop)
     *   "discard":  [card_id, card_id, ...],   // reshuffled into deck when empty
     *   "dropped_markers": [
     *     {
     *       "id": "uuid-string",                // unique within the game
     *       "card_id": int,
     *       "side": "a" | "b",                  // side originally chosen on attach
     *       "dropped_by_player_id": int|null    // null only if dropped via dealer override
     *     }
     *   ]
     * }
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->json('loot_state')->nullable()->after('scheme_pool');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('loot_state');
        });
    }
};
