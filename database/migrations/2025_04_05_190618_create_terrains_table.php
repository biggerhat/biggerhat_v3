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
        Schema::create('terrains', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->longText('description')->nullable();
            $table->timestamps();
        });

        Schema::create('marker_terrain', function (Blueprint $table) {
            $table->foreignId('marker_id')->constrained('markers')->cascadeOnDelete();
            $table->foreignId('terrain_id')->constrained('terrains')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marker_terrain');
        Schema::dropIfExists('terrains');
    }
};
