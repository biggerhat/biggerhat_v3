<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Abilities a Campaign hired model has permanently gained outside its
     * base Character catalog row — currently only via a Lucky Miss result
     * (pg 36) that names a real Ability. Additive: the model's base
     * Character abilities are untouched, this is purely extra.
     */
    public function up(): void
    {
        Schema::create('campaign_arsenal_model_abilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_arsenal_model_id')->constrained('campaign_arsenal_models')->cascadeOnDelete();
            $table->foreignId('ability_id')->constrained('abilities')->cascadeOnDelete();
            $table->string('source')->default('lucky_miss');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_arsenal_model_abilities');
    }
};
