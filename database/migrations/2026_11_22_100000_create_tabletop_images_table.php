<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-managed art for the tabletop: table surfaces and terrain textures. The tabletop ships a default for each (see
 * App\Support\Tabletop\TabletopImages); an active image here takes the place of the default for its terrain kind, and table
 * surfaces are offered next to the built-in ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabletop_images', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // 'table' (the whole 36" surface) or 'terrain' (a texture that repeats across the pieces of one terrain kind).
            $table->string('kind', 20);
            // Which terrain kind a terrain texture is for (a preset key such as 'tower'); null for table surfaces.
            $table->string('terrain_key', 30)->nullable();
            $table->string('image_path');
            $table->string('credit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['kind', 'is_active', 'sort_order'], 'tabletop_images_kind_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabletop_images');
    }
};
