<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tos_abilities', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->longText('body')->nullable();
            // is_general = shared across allegiances (e.g. universal keywords
            // like "Fast" or "Tough"). When false, allegiance_id should be set.
            $table->boolean('is_general')->default(false);
            $table->foreignId('allegiance_id')->nullable()->constrained('tos_allegiances')->nullOnDelete();
            $table->timestamps();
        });

        // Pivots on the unit-side, not on the unit itself, because Standard
        // and Glory sides of the same Unit Card carry different ability lists
        // (rulebook p. 9: "Each side has different Acting Values, Abilities,
        // and Actions").
        Schema::create('tos_unit_side_ability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_side_id')->constrained('tos_unit_sides')->cascadeOnDelete();
            $table->foreignId('ability_id')->constrained('tos_abilities')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->unique(['unit_side_id', 'ability_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_unit_side_ability');
        Schema::dropIfExists('tos_abilities');
    }
};
