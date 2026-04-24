<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tos_special_unit_rules', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->longText('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tos_unit_special_rule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('tos_units')->cascadeOnDelete();
            $table->foreignId('special_unit_rule_id')->constrained('tos_special_unit_rules')->cascadeOnDelete();
            // Per-rule parameters (rulebook). JSON shape is rule-specific:
            //   Fireteam:      { base_mm, models_per_team, model_size_mm }
            //   Squad:         { fireteam_count }
            //   Combined Arms: { child_unit_slug }
            //   Reserves:      { x }
            //   Adjunct:       { size_mm }
            // Untyped rules (Unique, Titan, Champion, Commander) leave this null.
            $table->json('parameters')->nullable();

            $table->unique(['unit_id', 'special_unit_rule_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_unit_special_rule');
        Schema::dropIfExists('tos_special_unit_rules');
    }
};
