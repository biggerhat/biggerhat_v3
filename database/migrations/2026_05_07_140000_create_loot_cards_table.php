<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bonanza Brawl Loot Deck catalog. 54 cards (52 + 2 jokers) — each card
     * has TWO benefits, one of which the player chooses on attach. The
     * effect text lives in `effect_a` / `effect_b` and is admin-managed,
     * since the Wyrd loot deck is bespoke and may evolve.
     */
    public function up(): void
    {
        Schema::create('loot_cards', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            // 'crow' | 'mask' | 'ram' | 'tome' | 'joker'. Jokers don't have a
            // suit on the physical card; we store 'joker' to keep filtering
            // simple.
            $table->string('suit');
            // Numeric value 1–13 for suited cards. Null for jokers (use
            // value_label to disambiguate red/black).
            $table->unsignedTinyInteger('value')->nullable();
            // 'A' | '2'..'10' | 'J' | 'Q' | 'K' | 'Red Joker' | 'Black Joker'.
            $table->string('value_label');
            $table->string('name');
            $table->longText('effect_a')->nullable();
            $table->longText('effect_b')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('suit');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loot_cards');
    }
};
