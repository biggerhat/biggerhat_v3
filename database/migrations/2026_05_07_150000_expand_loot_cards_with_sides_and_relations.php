<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expand the Loot Card schema for the full per-side rule structure.
     * Each card has two sides (A / B), one of which the player picks on attach.
     * Each side may grant: a title, an effect blurb, plus any number of
     * Actions, Abilities, and Triggers via three new pivot tables. The
     * `is_signature_action` flag on the action pivot mirrors the convention
     * already used by `characterables` and `upgradeables` pivots.
     */
    public function up(): void
    {
        Schema::table('loot_cards', function (Blueprint $table) {
            // Per-side titles (e.g. "Bag of Gold" vs "Hoard"). Effects already exist.
            $table->string('title_a')->nullable()->after('name');
            $table->string('title_b')->nullable()->after('effect_a');
            // Single image of the physical loot card.
            $table->string('image')->nullable()->after('effect_b');
        });

        Schema::create('loot_card_action', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loot_card_id')->constrained('loot_cards')->cascadeOnDelete();
            $table->foreignId('action_id')->constrained('actions')->cascadeOnDelete();
            // 'a' | 'b' — which side of the loot card grants this action.
            $table->string('side', 1);
            // Override flag — same shape as characterables/upgradeables, lets a
            // loot card grant a regular action AS a signature action.
            $table->boolean('is_signature_action')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->unique(['loot_card_id', 'action_id', 'side'], 'loot_card_action_unique');
            $table->index(['loot_card_id', 'side'], 'loot_card_action_side_idx');
        });

        Schema::create('loot_card_ability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loot_card_id')->constrained('loot_cards')->cascadeOnDelete();
            $table->foreignId('ability_id')->constrained('abilities')->cascadeOnDelete();
            $table->string('side', 1);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->unique(['loot_card_id', 'ability_id', 'side'], 'loot_card_ability_unique');
            $table->index(['loot_card_id', 'side'], 'loot_card_ability_side_idx');
        });

        Schema::create('loot_card_trigger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loot_card_id')->constrained('loot_cards')->cascadeOnDelete();
            $table->foreignId('trigger_id')->constrained('triggers')->cascadeOnDelete();
            $table->string('side', 1);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->unique(['loot_card_id', 'trigger_id', 'side'], 'loot_card_trigger_unique');
            $table->index(['loot_card_id', 'side'], 'loot_card_trigger_side_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loot_card_trigger');
        Schema::dropIfExists('loot_card_ability');
        Schema::dropIfExists('loot_card_action');

        Schema::table('loot_cards', function (Blueprint $table) {
            $table->dropColumn(['title_a', 'title_b', 'image']);
        });
    }
};
