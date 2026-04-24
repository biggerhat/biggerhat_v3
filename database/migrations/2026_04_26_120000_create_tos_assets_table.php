<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tos_assets', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->integer('scrip_cost')->default(0);
            $table->unsignedSmallInteger('disable_count')->nullable();
            $table->unsignedSmallInteger('scrap_count')->nullable();
            $table->longText('body')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tos_allegiance_asset', function (Blueprint $table) {
            $table->foreignId('allegiance_id')->constrained('tos_allegiances')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('tos_assets')->cascadeOnDelete();

            $table->primary(['allegiance_id', 'asset_id']);
        });

        Schema::create('tos_asset_ability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('tos_assets')->cascadeOnDelete();
            $table->foreignId('ability_id')->constrained('tos_abilities')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->unique(['asset_id', 'ability_id']);
        });

        Schema::create('tos_asset_action', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('tos_assets')->cascadeOnDelete();
            $table->foreignId('action_id')->constrained('tos_actions')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->unique(['asset_id', 'action_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_asset_action');
        Schema::dropIfExists('tos_asset_ability');
        Schema::dropIfExists('tos_allegiance_asset');
        Schema::dropIfExists('tos_assets');
    }
};
