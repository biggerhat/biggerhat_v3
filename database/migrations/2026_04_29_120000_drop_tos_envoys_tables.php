<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Envoy Cards were superseded by the new Allegiance Card format that carries
 * the same cross-allegiance content via the Standard / Primary tier split.
 * Drop the orphaned tables, models, and routes — see the same-day
 * `apr-15-game-mode` branch removal commit for the corresponding code wipe.
 *
 * The `down()` recreates the table shape so a rollback restores the schema,
 * but the data is gone — Envoys aren't seeded any more.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('tos_envoy_ability');
        Schema::dropIfExists('tos_envoys');
    }

    public function down(): void
    {
        Schema::create('tos_envoys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('allegiance_id')->constrained('tos_allegiances')->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('keyword')->nullable();
            $table->string('restriction', 16);
            $table->longText('body')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tos_envoy_ability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('envoy_id')->constrained('tos_envoys')->cascadeOnDelete();
            $table->foreignId('ability_id')->constrained('tos_abilities')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unique(['envoy_id', 'ability_id']);
        });
    }
};
