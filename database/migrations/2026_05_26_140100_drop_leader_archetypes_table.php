<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the `leader_archetypes` catalog table. Stat baselines + cost caps
 * now live on `LeaderArchetypeEnum` directly (FactionEnum-style). The
 * `custom_characters.archetype` column already stores the enum value, so
 * no data migration is required.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('leader_archetypes');
    }

    public function down(): void
    {
        Schema::create('leader_archetypes', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->unsignedTinyInteger('df');
            $table->unsignedTinyInteger('wp');
            $table->unsignedTinyInteger('sp');
            $table->unsignedTinyInteger('health');
            $table->unsignedTinyInteger('attack_actions_count');
            $table->unsignedTinyInteger('attack_action_cost_cap');
            $table->boolean('attack_gets_trigger')->default(false);
            $table->unsignedTinyInteger('tactical_actions_count');
            $table->unsignedTinyInteger('tactical_action_cost_cap');
            $table->unsignedTinyInteger('abilities_count');
            $table->unsignedTinyInteger('ability_cost_cap');
            $table->text('special_notes')->nullable();
            $table->timestamps();
        });
    }
};
