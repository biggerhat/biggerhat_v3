<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Mirrors user_unit_sculpts column-for-column — Adjunct-limit Assets
        // are physical swap-in models (rulebook p. 12), so they're tracked
        // in the collection the same way a Unit sculpt is.
        Schema::create('user_tos_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('tos_assets')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->boolean('is_built')->default(false);
            $table->boolean('is_painted')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'asset_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_tos_assets');
    }
};
