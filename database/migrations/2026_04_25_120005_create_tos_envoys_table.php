<?php

use App\Enums\TOS\EnvoyRestrictionEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tos_envoys', function (Blueprint $table) {
            $table->id();
            // Points at the Syndicate (or Allegiance) that brings this Envoy
            // rule into play. A Syndicate's Envoy enables its units to be
            // hired into any Allegiance whose type matches `restriction`.
            $table->foreignId('allegiance_id')->constrained('tos_allegiances')->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('keyword')->nullable();
            $table->string('restriction')->default(EnvoyRestrictionEnum::Earth->value);
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

    public function down(): void
    {
        Schema::dropIfExists('tos_envoy_ability');
        Schema::dropIfExists('tos_envoys');
    }
};
